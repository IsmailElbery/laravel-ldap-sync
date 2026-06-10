<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Services;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use IsmailElbery\LdapSync\Dto\SyncReport;
use IsmailElbery\LdapSync\Events\SyncCompleted;
use IsmailElbery\LdapSync\Events\UserDisabled;
use IsmailElbery\LdapSync\Events\UserSynced;
use LdapRecord\Models\ActiveDirectory\User;
use LdapRecord\Models\Collection;

final class LdapSyncService
{
    public function __construct(
        private readonly OuResolver $ouResolver,
        private readonly array $config,
    ) {}

    public function run(bool $dryRun = false, ?string $ouFilter = null): SyncReport
    {
        $start = microtime(true);
        $report = new SyncReport;
        $seenGuids = [];

        $baseDns = $ouFilter !== null ? [$ouFilter] : $this->ouResolver->all();

        $perform = function () use ($baseDns, $report, &$seenGuids): void {
            foreach ($baseDns as $baseDn) {
                try {
                    $this->syncOu($baseDn, $report, $seenGuids);
                } catch (\Throwable $e) {
                    Log::error("ldap-sync: failed syncing OU [{$baseDn}]: {$e->getMessage()}");
                }
            }

            $this->handleStaleUsers($seenGuids, $report);
        };

        if ($dryRun) {
            DB::beginTransaction();
            try {
                $perform();
            } finally {
                DB::rollBack();
            }
        } else {
            $perform();
        }

        $report->durationSeconds = round(microtime(true) - $start, 2);

        if (! $dryRun) {
            SyncCompleted::dispatch($report);
        }

        return $report;
    }

    /** @param array<string, bool> $seenGuids */
    private function syncOu(string $baseDn, SyncReport $report, array &$seenGuids): void
    {
        $count = 0;

        /** @var Collection $entries */
        $entries = User::query()->in($baseDn)->paginate(500);

        foreach ($entries as $entry) {
            $guid = (string) $entry->getConvertedGuid();

            if ($guid === '') {
                $report->increment('skipped');

                continue;
            }

            $seenGuids[$guid] = true;
            $count++;

            $this->upsertUser($entry, $guid, $report);
        }

        $report->addOuCount($baseDn, $count);
    }

    private function upsertUser(User $entry, string $guid, SyncReport $report): void
    {
        /** @var class-string<Model> $modelClass */
        $modelClass = $this->config['model'];
        $uniqueKey = $this->config['unique_key']['local'] ?? 'ldap_guid';
        $attributeMap = $this->config['attributes'] ?? [];
        $transformers = $this->config['transformers'] ?? [];

        $data = [];

        foreach ($attributeMap as $ldapAttr => $localColumn) {
            $value = $entry->getFirstAttribute($ldapAttr);
            $value = ($value !== null && $value !== '') ? $value : null;

            if (isset($transformers[$localColumn]) && is_callable($transformers[$localColumn])) {
                $value = ($transformers[$localColumn])($value);
            }

            $data[$localColumn] = $value;
        }

        /** @var Model $user */
        $user = $modelClass::firstOrNew([$uniqueKey => $guid]);
        $wasNew = ! $user->exists;

        foreach ($data as $column => $value) {
            $user->$column = $value;
        }

        $user->save();

        $report->increment($wasNew ? 'created' : 'updated');

        UserSynced::dispatch($user, $wasNew);
    }

    /** @param array<string, bool> $seenGuids */
    private function handleStaleUsers(array $seenGuids, SyncReport $report): void
    {
        $strategy = $this->config['stale_users']['strategy'] ?? 'ignore';

        if ($strategy === 'ignore') {
            return;
        }

        /** @var class-string<Model> $modelClass */
        $modelClass = $this->config['model'];
        $uniqueKey = $this->config['unique_key']['local'] ?? 'ldap_guid';
        $activeColumn = $this->config['stale_users']['column'] ?? 'is_active';

        $staleQuery = $modelClass::query()->whereNotNull($uniqueKey);

        if (! empty($seenGuids)) {
            $staleQuery->whereNotIn($uniqueKey, array_keys($seenGuids));
        }

        $staleUsers = $staleQuery->get();

        foreach ($staleUsers as $user) {
            match ($strategy) {
                'disable' => $this->applyDisable($user, $activeColumn),
                'delete' => $user->delete(),
                'flag' => $user->forceFill(['ldap_stale_at' => now()])->save(),
                default => null,
            };

            $report->increment('disabled');
            UserDisabled::dispatch($user, $strategy);
        }
    }

    private function applyDisable(Model $user, string $column): void
    {
        $user->$column = false;
        $user->save();
    }
}
