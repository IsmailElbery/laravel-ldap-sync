<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Facades;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Facade;
use IsmailElbery\LdapSync\Dto\LdapUserDto;
use IsmailElbery\LdapSync\Search\LdapUserQuery;
use IsmailElbery\LdapSync\Services\LdapUserSearchService;

/**
 * @method static LdapUserDto|null byUsername(string $username)
 * @method static LdapUserDto|null byEmail(string $email)
 * @method static Collection<int, LdapUserDto> byName(string $name)
 * @method static Collection<int, LdapUserDto> byDepartment(string $department)
 * @method static Collection<int, LdapUserDto> search(string $term)
 * @method static LdapUserQuery query()
 * @method static LdapUserDto|null directManagerOf(LdapUserDto|string $user)
 * @method static Collection<int, LdapUserDto> managersOf(LdapUserDto|string $user, int $maxDepth = 10)
 * @method static Collection<int, LdapUserDto> directReportsOf(LdapUserDto|string $user)
 */
final class LdapUserSearch extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return LdapUserSearchService::class;
    }
}
