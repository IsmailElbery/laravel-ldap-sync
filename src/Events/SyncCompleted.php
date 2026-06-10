<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Events;

use Illuminate\Foundation\Events\Dispatchable;
use IsmailElbery\LdapSync\Dto\SyncReport;

final class SyncCompleted
{
    use Dispatchable;

    public function __construct(
        public readonly SyncReport $report,
    ) {}
}
