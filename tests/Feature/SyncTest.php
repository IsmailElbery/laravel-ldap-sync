<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use IsmailElbery\LdapSync\Events\SyncCompleted;
use IsmailElbery\LdapSync\Events\UserDisabled;
use IsmailElbery\LdapSync\Events\UserSynced;
use IsmailElbery\LdapSync\Services\LdapSyncService;
use IsmailElbery\LdapSync\Services\OuResolver;
use IsmailElbery\LdapSync\Tests\Fixtures\User;
use LdapRecord\Models\ActiveDirectory\User as LdapUser;

// ──────────────────────────────────────────────
// Test 1 — Sync creates new users with mapped attributes
// ──────────────────────────────────────────────
it('creates new users with mapped and transformed attributes', function (): void {
    LdapUser::create([
        'cn' => 'Ismail Elbery',
        'samaccountname' => 'i.elbery',
        'mail' => 'i.elbery@example.com',
        'displayname' => 'Ismail Elbery',
        'department' => 'IT',
        'title' => 'Engineer',
        'objectguid' => '11111111-1111-1111-1111-111111111111',
        'distinguishedname' => 'cn=Ismail Elbery,ou=IT,dc=example,dc=com',
    ]);

    $service = makeService();
    $report = $service->run();

    expect(User::where('username', 'i.elbery')->exists())->toBeTrue()
        ->and($report->created)->toBe(1)
        ->and($report->updated)->toBe(0);

    $user = User::where('username', 'i.elbery')->first();
    expect($user->email)->toBe('i.elbery@example.com')
        ->and($user->department_name)->toBe('IT')
        ->and($user->job_title)->toBe('Engineer');
});

// ──────────────────────────────────────────────
// Test 2 — Sync updates users matched by GUID after username change
// ──────────────────────────────────────────────
it('updates existing user matched by GUID even after username change', function (): void {
    $guid = '22222222-2222-2222-2222-222222222222';

    User::create([
        'ldap_guid' => $guid,
        'username' => 'old.name',
        'name' => 'Old Name',
        'email' => 'old@example.com',
        'is_active' => true,
    ]);

    LdapUser::create([
        'cn' => 'New Name',
        'samaccountname' => 'new.name',
        'mail' => 'new@example.com',
        'displayname' => 'New Name',
        'objectguid' => $guid,
        'distinguishedname' => 'cn=New Name,ou=IT,dc=example,dc=com',
    ]);

    $service = makeService();
    $report = $service->run();

    expect($report->created)->toBe(0)
        ->and($report->updated)->toBe(1);

    $user = User::where('ldap_guid', $guid)->first();
    expect($user->username)->toBe('new.name')
        ->and($user->email)->toBe('new@example.com');
});

// ──────────────────────────────────────────────
// Test 3 — Each stale strategy
// ──────────────────────────────────────────────
it('disables stale users when strategy is disable', function (): void {
    User::create(['ldap_guid' => 'stale-guid-1', 'username' => 'stale', 'is_active' => true]);

    $service = makeService(['stale_users' => ['strategy' => 'disable', 'column' => 'is_active']]);
    $service->run();

    expect(User::where('ldap_guid', 'stale-guid-1')->first()->is_active)->toBeFalse();
});

it('soft-deletes stale users when strategy is delete', function (): void {
    User::create(['ldap_guid' => 'stale-guid-2', 'username' => 'stale2', 'is_active' => true]);

    $service = makeService(['stale_users' => ['strategy' => 'delete', 'column' => 'is_active']]);
    $service->run();

    expect(User::withTrashed()->where('ldap_guid', 'stale-guid-2')->first()->trashed())->toBeTrue();
});

it('flags stale users when strategy is flag', function (): void {
    User::create(['ldap_guid' => 'stale-guid-3', 'username' => 'stale3', 'is_active' => true]);

    $service = makeService(['stale_users' => ['strategy' => 'flag', 'column' => 'is_active']]);
    $service->run();

    expect(User::where('ldap_guid', 'stale-guid-3')->first()->ldap_stale_at)->not->toBeNull();
});

it('does nothing to stale users when strategy is ignore', function (): void {
    User::create(['ldap_guid' => 'stale-guid-4', 'username' => 'stale4', 'is_active' => true]);

    $service = makeService(['stale_users' => ['strategy' => 'ignore', 'column' => 'is_active']]);
    $service->run();

    $user = User::where('ldap_guid', 'stale-guid-4')->first();
    expect($user->is_active)->toBeTrue()
        ->and($user->ldap_stale_at)->toBeNull();
});

// ──────────────────────────────────────────────
// Test 4 — Dry-run makes zero DB changes
// ──────────────────────────────────────────────
it('dry-run makes zero DB changes but reports correct counts', function (): void {
    LdapUser::create([
        'cn' => 'Dry Run User',
        'samaccountname' => 'dry.user',
        'mail' => 'dry@example.com',
        'displayname' => 'Dry Run User',
        'objectguid' => '33333333-3333-3333-3333-333333333333',
        'distinguishedname' => 'cn=Dry Run User,ou=IT,dc=example,dc=com',
    ]);

    $service = makeService();
    $report = $service->run(dryRun: true);

    expect($report->created)->toBe(1)
        ->and(User::count())->toBe(0); // nothing persisted
});

// ──────────────────────────────────────────────
// Test 11 — Events fire with correct payloads
// ──────────────────────────────────────────────
it('fires UserSynced and SyncCompleted events', function (): void {
    Event::fake([UserSynced::class, SyncCompleted::class]);

    LdapUser::create([
        'cn' => 'Event User',
        'samaccountname' => 'event.user',
        'mail' => 'event@example.com',
        'displayname' => 'Event User',
        'objectguid' => '44444444-4444-4444-4444-444444444444',
        'distinguishedname' => 'cn=Event User,ou=IT,dc=example,dc=com',
    ]);

    makeService()->run();

    Event::assertDispatched(UserSynced::class, fn ($e) => $e->wasRecentlyCreated === true);
    Event::assertDispatched(SyncCompleted::class);
});

it('fires UserDisabled event for stale users', function (): void {
    Event::fake([UserDisabled::class]);

    User::create(['ldap_guid' => 'stale-event-guid', 'username' => 'stale.event', 'is_active' => true]);

    makeService()->run();

    Event::assertDispatched(UserDisabled::class);
});

// ──────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────
function makeService(array $overrides = []): LdapSyncService
{
    $config = array_merge(config('ldap-sync'), $overrides);
    $resolver = new OuResolver(
        'dc=example,dc=com',
        'ou=IT,dc=example,dc=com',
    );

    return new LdapSyncService($resolver, $config);
}
