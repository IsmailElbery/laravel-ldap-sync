# Laravel LDAP Sync

[![Latest Version on Packagist](https://img.shields.io/packagist/v/ismailelbery/laravel-ldap-sync.svg?style=flat-square)](https://packagist.org/packages/ismailelbery/laravel-ldap-sync)
[![Tests](https://img.shields.io/github/actions/workflow/status/IsmailElbery/laravel-ldap-sync/run-tests.yml?branch=main&label=tests&style=flat-square)](https://github.com/IsmailElbery/laravel-ldap-sync/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/ismailelbery/laravel-ldap-sync.svg?style=flat-square)](https://packagist.org/packages/ismailelbery/laravel-ldap-sync)
[![License](https://img.shields.io/packagist/l/ismailelbery/laravel-ldap-sync.svg?style=flat-square)](LICENSE.md)

An opinionated LDAP / Active Directory user synchronization package for Laravel. Sync users from **multiple Organizational Units (OUs)** into your local database on a schedule, with department mapping, attribute transformation, and full sync reporting — built on top of [LdapRecord](https://ldaprecord.com).

Designed for enterprise and government environments where Active Directory is the source of truth and your Laravel application needs a reliable, queryable local copy of users.

---

## ✨ Features

- 🔁 **Scheduled synchronization** — run full or incremental syncs via Laravel's scheduler
- 🏢 **Multi-OU support** — search and sync across multiple base DNs / OUs in a single run
- 🔍 **User search** — search LDAP directly by username, name (EN/AR), email, or department with a fluent API
- 👔 **Manager resolution** — get a user's direct manager or the full management chain up to the top
- 🗂️ **Department mapping** — map LDAP OUs or attributes to local departments/teams
- 🔧 **Attribute mapping** — declarative config to map LDAP attributes to Eloquent columns (with custom transformers)
- 🌐 **Arabic / UTF-8 safe** — correct handling of Arabic display names and attributes
- 🧹 **Stale user handling** — disable, soft-delete, or flag users removed from AD
- 📊 **Sync reports** — created / updated / disabled counts, with optional logging and notifications
- 🧪 **Dry-run mode** — preview changes before touching your database
- 🪝 **Events** — hook into `UserSynced`, `UserDisabled`, `SyncCompleted` for custom logic

---

## 📦 Installation

```bash
composer require ismailelbery/laravel-ldap-sync
```

Publish the config and migrations:

```bash
php artisan vendor:publish --tag="ldap-sync-config"
php artisan vendor:publish --tag="ldap-sync-migrations"
php artisan migrate
```

---

## ⚙️ Configuration

All connection settings live in your `.env`:

```env
LDAP_HOST=ad.example.gov.sa
LDAP_PORT=389
LDAP_USERNAME="cn=svc-laravel,ou=ServiceAccounts,dc=example,dc=gov,dc=sa"
LDAP_PASSWORD=secret
LDAP_BASE_DN="dc=example,dc=gov,dc=sa"

# Semicolon-separated list of OUs to sync (relative or absolute DNs)
LDAP_SYNC_OUS="ou=IT,ou=Departments;ou=HR,ou=Departments;ou=Digitization"

LDAP_SYNC_SCHEDULE="0 2 * * *"   # nightly at 2 AM
LDAP_SYNC_STALE_STRATEGY=disable # disable | delete | flag | ignore
```

The published `config/ldap-sync.php` lets you define attribute mapping:

```php
return [
    'model' => App\Models\User::class,

    'unique_key' => [
        'ldap'  => 'objectguid',
        'local' => 'ldap_guid',
    ],

    'attributes' => [
        'samaccountname'   => 'username',
        'mail'             => 'email',
        'displayname'      => 'name',
        'displayname_ar'   => 'name_ar',          // custom AD attribute
        'telephonenumber'  => 'phone',
        'department'       => 'department_name',
        'title'            => 'job_title',
    ],

    // Optional: transform values before saving
    'transformers' => [
        'phone' => fn ($value) => preg_replace('/^\+?966/', '0', $value ?? ''),
    ],

    // Map OUs to local department IDs
    'department_map' => [
        'ou=Digitization' => 'General Department of Digitization & AI',
        'ou=HR'           => 'Human Resources',
    ],

    'stale_users' => [
        'strategy' => env('LDAP_SYNC_STALE_STRATEGY', 'disable'),
        'column'   => 'is_active',
    ],
];
```

---

## 🚀 Usage

### Run a sync manually

```bash
php artisan ldap:sync
```

### Sync a specific OU only

```bash
php artisan ldap:sync --ou="ou=IT,ou=Departments"
```

### Preview changes without writing (dry run)

```bash
php artisan ldap:sync --dry-run
```

Example output:

```
Syncing 3 OUs from ad.example.gov.sa...

 ✔ ou=IT,ou=Departments ............ 142 found
 ✔ ou=HR,ou=Departments ............  38 found
 ✔ ou=Digitization .................  27 found

 Created:   12
 Updated:  189
 Disabled:   6
 Skipped:    0

Sync completed in 4.2s
```

### Schedule it

In `routes/console.php` (Laravel 11):

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('ldap:sync')->dailyAt('02:00');
```

### Listen to events

```php
use IsmailElbery\LdapSync\Events\UserSynced;
use IsmailElbery\LdapSync\Events\SyncCompleted;

Event::listen(UserSynced::class, function (UserSynced $event) {
    // $event->user, $event->wasRecentlyCreated
});

Event::listen(SyncCompleted::class, function (SyncCompleted $event) {
    // $event->report->created, ->updated, ->disabled
});
```

---

## 🔍 User Search

Search Active Directory directly (live, not the local table) using the `LdapUserSearch` facade. All searches run across **all configured OUs** and merge + deduplicate results by `objectguid`.

```php
use IsmailElbery\LdapSync\Facades\LdapUserSearch;

// By username (sAMAccountName) — exact match
$user = LdapUserSearch::byUsername('i.elbery');

// By email — exact match
$user = LdapUserSearch::byEmail('i.elbery@example.gov.sa');

// By name — partial match, searches displayName (EN and AR attributes)
$users = LdapUserSearch::byName('Ismail');      // returns Collection
$users = LdapUserSearch::byName('إسماعيل');     // Arabic works too

// By department
$users = LdapUserSearch::byDepartment('Digitization');

// Smart search — detects whether the term looks like an email,
// username, or name, and searches the right attributes
$users = LdapUserSearch::search('i.elbery@example.gov.sa');
$users = LdapUserSearch::search('Ismail');

// Fluent combination
$users = LdapUserSearch::query()
    ->inOu('ou=IT,ou=Departments')
    ->department('Infrastructure')
    ->name('Ahmed')
    ->limit(20)
    ->get();
```

Each result is returned as an `LdapUserDto` with a consistent shape regardless of AD schema quirks:

```php
$user->username;      // sAMAccountName
$user->name;          // displayName
$user->nameAr;        // Arabic display name (if configured)
$user->email;
$user->department;
$user->title;
$user->phone;
$user->dn;            // distinguished name
$user->guid;          // objectGUID
$user->managerDn;     // raw manager attribute
```

### Search command

A quick CLI lookup is also included:

```bash
php artisan ldap:search "Ismail"
php artisan ldap:search --email=i.elbery@example.gov.sa
php artisan ldap:search --department=Digitization
```

---

## 👔 Manager Resolution

Resolve reporting lines straight from the AD `manager` attribute.

```php
use IsmailElbery\LdapSync\Facades\LdapUserSearch;

$user = LdapUserSearch::byUsername('i.elbery');

// Direct manager — single LdapUserDto (or null)
$manager = LdapUserSearch::directManagerOf($user);
$manager = LdapUserSearch::directManagerOf('i.elbery');   // username works too

// Full management chain — ordered Collection from direct manager
// up to the top of the hierarchy (cycle-safe)
$chain = LdapUserSearch::managersOf('i.elbery');

foreach ($chain as $level => $manager) {
    echo "{$level}: {$manager->name} ({$manager->title})";
}
// 0: Ahmed Saleh (Section Head)
// 1: Khalid Omar (Department Director)
// 2: Fahad Al-Otaibi (Deputy Governor)

// Limit chain depth (e.g. for approval workflows needing only 2 levels)
$chain = LdapUserSearch::managersOf('i.elbery', maxDepth: 2);

// Inverse: who reports directly to this user?
$reports = LdapUserSearch::directReportsOf('k.omar');
```

This is particularly useful for **approval workflows** — route a request to a user's direct manager, or escalate up the chain automatically:

```php
$approver = LdapUserSearch::directManagerOf(auth()->user()->username);

ApprovalRequest::create([
    'requester_id' => auth()->id(),
    'approver_dn'  => $approver?->dn,
]);
```

> **Note:** Manager resolution requires the `manager` attribute to be populated in your AD. The chain resolver guards against circular references and missing managers, returning the chain built so far rather than throwing.

---

## 🧱 How It Works

1. Connects to your LDAP/AD server using LdapRecord with the credentials in `config/ldap.php`.
2. Iterates each configured OU, paginating results to safely handle large directories.
3. For each entry, resolves the local user via the configured unique key (`objectguid` by default — immune to renames and OU moves).
4. Applies attribute mapping and transformers, then creates or updates the Eloquent model.
5. After all OUs are processed, applies the stale-user strategy to local users no longer present in AD.
6. Fires events and writes a sync report.

---

## 🧪 Testing

```bash
composer test
```

The test suite uses LdapRecord's built-in directory emulator, so no real LDAP server is required.

---

## 🗺️ Roadmap

- [ ] Group → role synchronization (Spatie permissions bridge)
- [ ] Incremental sync via `whenChanged` / USN tracking
- [ ] Photo (`thumbnailPhoto`) sync to local storage
- [ ] Web dashboard for sync history

---

## 🤝 Contributing

Contributions are welcome! Please open an issue first to discuss what you would like to change, and make sure tests pass before submitting a PR.

## 🔒 Security

If you discover a security vulnerability, please email **ismail.bery@gmail.com** instead of using the issue tracker.

## 📄 License

The MIT License (MIT). See [LICENSE.md](LICENSE.md) for details.

---

Built with ☕ by [Ismail Elbery](https://github.com/IsmailElbery) — born from years of syncing Active Directory users in Laravel government systems.
