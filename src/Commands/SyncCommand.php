<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Commands;

use Illuminate\Console\Command;
use IsmailElbery\LdapSync\Dto\SyncReport;
use IsmailElbery\LdapSync\Services\LdapSyncService;

final class SyncCommand extends Command
{
    protected $signature = 'ldap:sync
        {--ou= : Sync a specific OU only}
        {--dry-run : Preview changes without writing to the database}';

    protected $description = 'Synchronize users from Active Directory into the local database';

    public function handle(LdapSyncService $service): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $ou = $this->option('ou');

        if ($dryRun) {
            $this->components->info('Running in DRY-RUN mode — no changes will be saved.');
        }

        $report = $service->run(dryRun: $dryRun, ouFilter: $ou ?: null);

        $this->renderOuTable($report);
        $this->renderSummary($report, $dryRun);

        return self::SUCCESS;
    }

    private function renderOuTable(SyncReport $report): void
    {
        $this->newLine();

        foreach ($report->ouCounts as $baseDn => $count) {
            $this->components->twoColumnDetail(
                " ✔ {$baseDn}",
                str_pad((string) $count, 4, ' ', STR_PAD_LEFT).' found',
            );
        }
    }

    private function renderSummary(SyncReport $report, bool $dryRun): void
    {
        $this->newLine();

        $label = $dryRun ? ' (dry-run)' : '';

        $this->table([], [
            [' Created'.$label, $report->created],
            [' Updated'.$label, $report->updated],
            [' Disabled'.$label, $report->disabled],
            [' Skipped'.$label, $report->skipped],
        ]);

        $this->components->info("Sync completed in {$report->durationSeconds}s");
    }
}
