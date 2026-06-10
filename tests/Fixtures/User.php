<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

final class User extends Model
{
    use SoftDeletes;

    protected $table = 'users';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
