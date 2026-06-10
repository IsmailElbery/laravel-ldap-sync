<?php

declare(strict_types=1);

use IsmailElbery\LdapSync\Services\LdapUserSearchService;
use IsmailElbery\LdapSync\Services\OuResolver;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

// ──────────────────────────────────────────────
// Test 8 — Direct manager resolution
// ──────────────────────────────────────────────
it('resolves the direct manager of a user', function (): void {
    $managerDn = 'cn=Ahmed Saleh,ou=IT,dc=example,dc=com';

    LdapUser::create([
        'cn' => 'Ahmed Saleh',
        'samaccountname' => 'a.saleh',
        'mail' => 'a.saleh@example.com',
        'displayname' => 'Ahmed Saleh',
        'objectguid' => '00000001-0001-0001-0001-000000000001',
        'distinguishedname' => $managerDn,
    ]);

    LdapUser::create([
        'cn' => 'Ismail Elbery',
        'samaccountname' => 'i.elbery',
        'mail' => 'i.elbery@example.com',
        'displayname' => 'Ismail Elbery',
        'manager' => $managerDn,
        'objectguid' => '00000002-0002-0002-0002-000000000002',
        'distinguishedname' => 'cn=Ismail Elbery,ou=IT,dc=example,dc=com',
    ]);

    $manager = makeManagerService()->directManagerOf('i.elbery');

    expect($manager)->not->toBeNull()
        ->and($manager->username)->toBe('a.saleh');
});

// Test chain stops at top (no manager attribute)
it('resolves the full manager chain up to the top', function (): void {
    $level2Dn = 'cn=Khalid Omar,ou=IT,dc=example,dc=com';
    $level1Dn = 'cn=Ahmed Saleh,ou=IT,dc=example,dc=com';

    LdapUser::create([
        'cn' => 'Khalid Omar',
        'samaccountname' => 'k.omar',
        'displayname' => 'Khalid Omar',
        'objectguid' => '00000003-0003-0003-0003-000000000003',
        'distinguishedname' => $level2Dn,
        // No manager — top of chain
    ]);

    LdapUser::create([
        'cn' => 'Ahmed Saleh',
        'samaccountname' => 'a.saleh',
        'displayname' => 'Ahmed Saleh',
        'manager' => $level2Dn,
        'objectguid' => '00000004-0004-0004-0004-000000000004',
        'distinguishedname' => $level1Dn,
    ]);

    LdapUser::create([
        'cn' => 'Ismail Elbery',
        'samaccountname' => 'i.elbery2',
        'displayname' => 'Ismail Elbery',
        'manager' => $level1Dn,
        'objectguid' => '00000005-0005-0005-0005-000000000005',
        'distinguishedname' => 'cn=Ismail Elbery,ou=IT,dc=example,dc=com',
    ]);

    $chain = makeManagerService()->managersOf('i.elbery2');

    expect($chain->count())->toBe(2)
        ->and($chain->get(0)->username)->toBe('a.saleh')   // direct manager
        ->and($chain->get(1)->username)->toBe('k.omar');   // top
});

// ──────────────────────────────────────────────
// Test 9 — Circular manager reference terminates
// ──────────────────────────────────────────────
it('terminates chain resolution on circular manager reference', function (): void {
    $dnA = 'cn=User A,ou=IT,dc=example,dc=com';
    $dnB = 'cn=User B,ou=IT,dc=example,dc=com';

    LdapUser::create([
        'cn' => 'User A',
        'samaccountname' => 'user.a',
        'displayname' => 'User A',
        'manager' => $dnB,
        'objectguid' => '00000006-0006-0006-0006-000000000006',
        'distinguishedname' => $dnA,
    ]);

    LdapUser::create([
        'cn' => 'User B',
        'samaccountname' => 'user.b',
        'displayname' => 'User B',
        'manager' => $dnA,  // points back to User A → cycle
        'objectguid' => '00000007-0007-0007-0007-000000000007',
        'distinguishedname' => $dnB,
    ]);

    // Should not throw or loop forever; returns partial chain
    $chain = makeManagerService()->managersOf('user.a');

    expect($chain->count())->toBe(1)
        ->and($chain->first()->username)->toBe('user.b');
});

// ──────────────────────────────────────────────
// Test 10 — directReportsOf returns all and only direct reports
// ──────────────────────────────────────────────
it('returns all and only direct reports of a user', function (): void {
    $managerDn = 'cn=K Omar,ou=IT,dc=example,dc=com';

    LdapUser::create([
        'cn' => 'K Omar',
        'samaccountname' => 'k.omar2',
        'displayname' => 'K Omar',
        'objectguid' => '00000008-0008-0008-0008-000000000008',
        'distinguishedname' => $managerDn,
    ]);

    // Two reports
    $reportGuids = [
        '00000009-0009-0009-0009-000000000009',
        '00000010-0010-0010-0010-000000000010',
    ];
    foreach (['report.one', 'report.two'] as $i => $sAM) {
        LdapUser::create([
            'cn' => "Report {$i}",
            'samaccountname' => $sAM,
            'displayname' => "Report {$i}",
            'manager' => $managerDn,
            'objectguid' => $reportGuids[$i],
            'distinguishedname' => "cn=Report {$i},ou=IT,dc=example,dc=com",
        ]);
    }

    // One user that is NOT a report
    LdapUser::create([
        'cn' => 'Other User',
        'samaccountname' => 'other.user',
        'displayname' => 'Other User',
        'objectguid' => '00000011-0011-0011-0011-000000000011',
        'distinguishedname' => 'cn=Other User,ou=IT,dc=example,dc=com',
    ]);

    $reports = makeManagerService()->directReportsOf('k.omar2');

    expect($reports->count())->toBe(2);
    $usernames = $reports->pluck('username')->sort()->values()->all();
    expect($usernames)->toBe(['report.one', 'report.two']);
});

// ──────────────────────────────────────────────
// Helper
// ──────────────────────────────────────────────
function makeManagerService(): LdapUserSearchService
{
    $resolver = new OuResolver('dc=example,dc=com', 'ou=IT,dc=example,dc=com');

    return new LdapUserSearchService($resolver, config('ldap-sync'));
}
