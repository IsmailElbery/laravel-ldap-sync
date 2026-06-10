<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

final class UserSynced
{
    use Dispatchable;

    public function __construct(
        public readonly Model $user,
        public readonly bool $wasRecentlyCreated,
    ) {}
}
