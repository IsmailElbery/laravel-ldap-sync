<?php

declare(strict_types=1);

use IsmailElbery\LdapSync\Services\LdapUserSearchService;
use IsmailElbery\LdapSync\Services\OuResolver;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

// ──────────────────────────────────────────────
// Test 5 — Search by username/email exact, name/department partial
// ──────────────────────────────────────────────
it('finds a user by exact username', function (): void {
    seedUser('i.elbery', 'Ismail Elbery', 'i.elbery@example.com', 'IT');

    $result = makeSearchService()->byUsername('i.elbery');

    expect($result)->not->toBeNull()
        ->and($result->username)->toBe('i.elbery');
});

it('finds a user by exact email', function (): void {
    seedUser('m.ali', 'Mohammed Ali', 'm.ali@example.com', 'HR');

    $result = makeSearchService()->byEmail('m.ali@example.com');

    expect($result)->not->toBeNull()
        ->and($result->email)->toBe('m.ali@example.com');
});

it('finds users by partial name (English)', function (): void {
    seedUser('a.ahmed', 'Ahmed Saleh', 'a.ahmed@example.com', 'IT');
    seedUser('a.ali', 'Ahmed Ali', 'a.ali@example.com', 'HR');

    $results = makeSearchService()->byName('Ahmed');

    expect($results->count())->toBe(2);
});

it('finds users by partial name (Arabic)', function (): void {
    // Seed a user whose displayname is in Arabic to verify byName handles Unicode
    LdapUser::create([
        'cn' => 'إسماعيل البيري',
        'samaccountname' => 'i.elbery.ar',
        'mail' => 'i.elbery.ar@example.com',
        'displayname' => 'إسماعيل البيري',
        'objectguid' => 'cccccccc-cccc-cccc-cccc-cccccccccccc',
        'distinguishedname' => 'cn=إسماعيل البيري,ou=IT,dc=example,dc=com',
    ]);

    $results = makeSearchService()->byName('إسماعيل');

    expect($results->count())->toBeGreaterThanOrEqual(1);
});

it('finds users by department partial match', function (): void {
    seedUser('d1.user', 'Dept User 1', 'd1@example.com', 'Digitization');
    seedUser('d2.user', 'Dept User 2', 'd2@example.com', 'Digitization AI');

    $results = makeSearchService()->byDepartment('Digitization');

    expect($results->count())->toBe(2);
});

// ──────────────────────────────────────────────
// Test 6 — Regression: multi-OU search not polluted
// ──────────────────────────────────────────────
it('returns correct merged results when searching across 2+ OUs without builder pollution', function (): void {
    // Place one user in each OU
    LdapUser::create([
        'cn' => 'OU1 User',
        'samaccountname' => 'ou1.user',
        'mail' => 'ou1@example.com',
        'displayname' => 'OU1 User',
        'department' => 'IT',
        'objectguid' => 'aaaaaaaa-aaaa-aaaa-aaaa-aaaaaaaaaaaa',
        'distinguishedname' => 'cn=OU1 User,ou=IT,dc=example,dc=com',
    ]);

    LdapUser::create([
        'cn' => 'OU2 User',
        'samaccountname' => 'ou2.user',
        'mail' => 'ou2@example.com',
        'displayname' => 'OU2 User',
        'department' => 'HR',
        'objectguid' => 'bbbbbbbb-bbbb-bbbb-bbbb-bbbbbbbbbbbb',
        'distinguishedname' => 'cn=OU2 User,ou=HR,dc=example,dc=com',
    ]);

    // Two OUs in the resolver
    $resolver = new OuResolver('dc=example,dc=com', 'ou=IT,dc=example,dc=com;ou=HR,dc=example,dc=com');
    $service = new LdapUserSearchService($resolver, config('ldap-sync'));

    // byName searches both OUs — must return 2 distinct users, not duplicates or empty
    $results = $service->byName('User');

    expect($results->count())->toBe(2);

    $usernames = $results->pluck('username')->sort()->values()->all();
    expect($usernames)->toBe(['ou1.user', 'ou2.user']);
});

// ──────────────────────────────────────────────
// Test 7 — Smart search() routing
// ──────────────────────────────────────────────
it('smart search routes email term to byEmail', function (): void {
    seedUser('smart.email', 'Smart Email User', 'smart@example.com', 'IT');

    $results = makeSearchService()->search('smart@example.com');

    expect($results->count())->toBe(1)
        ->and($results->first()->email)->toBe('smart@example.com');
});

it('smart search routes username term to byUsername when hit found', function (): void {
    seedUser('smart.user', 'Smart User', 'smart.user@example.com', 'IT');

    $results = makeSearchService()->search('smart.user');

    expect($results->count())->toBe(1)
        ->and($results->first()->username)->toBe('smart.user');
});

it('smart search routes free text to byName', function (): void {
    seedUser('name.test', 'Unique Name Test', 'name.test@example.com', 'IT');

    $results = makeSearchService()->search('Unique Name Test');

    expect($results->count())->toBeGreaterThanOrEqual(1);
});

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────
function makeSearchService(): LdapUserSearchService
{
    $resolver = new OuResolver('dc=example,dc=com', 'ou=IT,dc=example,dc=com;ou=HR,dc=example,dc=com');

    return new LdapUserSearchService($resolver, config('ldap-sync'));
}

function seedUser(string $username, string $name, string $email, string $department, ?string $guid = null): void
{
    $guid ??= implode('-', [
        substr(md5($username), 0, 8),
        substr(md5($username), 8, 4),
        substr(md5($username), 12, 4),
        substr(md5($username), 16, 4),
        substr(md5($username), 20, 12),
    ]);

    $ou = str_contains($department, 'HR') ? 'ou=HR' : 'ou=IT';

    LdapUser::create([
        'cn' => $name,
        'samaccountname' => $username,
        'mail' => $email,
        'displayname' => $name,
        'department' => $department,
        'objectguid' => $guid,
        'distinguishedname' => "cn={$name},{$ou},dc=example,dc=com",
    ]);
}
