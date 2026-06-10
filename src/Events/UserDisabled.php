<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Events;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

final class UserDisabled
{
    use Dispatchable;

    public function __construct(
        public readonly Model $user,
        public readonly string $strategy,
    ) {}
}
