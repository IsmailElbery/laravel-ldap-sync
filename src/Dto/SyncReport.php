<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Dto;

final class SyncReport
{
    public int $created = 0;

    public int $updated = 0;

    public int $disabled = 0;

    public int $skipped = 0;

    public float $durationSeconds = 0.0;

    /** @var array<string, int> */
    public array $ouCounts = [];

    public function increment(string $field): void
    {
        $this->$field++;
    }

    public function addOuCount(string $baseDn, int $count): void
    {
        $this->ouCounts[$baseDn] = $count;
    }

    public function total(): int
    {
        return $this->created + $this->updated + $this->disabled + $this->skipped;
    }
}
