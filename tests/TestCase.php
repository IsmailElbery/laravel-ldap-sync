<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync\Tests;

use IsmailElbery\LdapSync\LdapSyncServiceProvider;
use IsmailElbery\LdapSync\Tests\Fixtures\CreateUsersTable;
use IsmailElbery\LdapSync\Tests\Fixtures\User;
use LdapRecord\Laravel\LdapServiceProvider;
use LdapRecord\Laravel\Testing\DirectoryEmulator;
use Orchestra\Testbench\TestCase as Orchestra;

abstract class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        DirectoryEmulator::setup('default');

        (new CreateUsersTable)->up();
    }

    protected function tearDown(): void
    {
        DirectoryEmulator::teardown();

        parent::tearDown();
    }

    protected function getPackageProviders($app): array
    {
        return [
            LdapServiceProvider::class,
            LdapSyncServiceProvider::class,
        ];
    }

    protected function getEnvironmentSetUp($app): void
    {
        config()->set('database.default', 'testing');
        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
        ]);

        config()->set('ldap.default', 'default');
        config()->set('ldap.connections.default', [
            'hosts' => ['127.0.0.1'],
            'port' => 389,
            'base_dn' => 'dc=example,dc=com',
            'username' => 'cn=admin,dc=example,dc=com',
            'password' => 'secret',
        ]);

        config()->set('ldap-sync', [
            'model' => User::class,
            'unique_key' => [
                'ldap' => 'objectguid',
                'local' => 'ldap_guid',
            ],
            'attributes' => [
                'samaccountname' => 'username',
                'mail' => 'email',
                'displayname' => 'name',
                'displayname_ar' => 'name_ar',
                'department' => 'department_name',
                'title' => 'job_title',
                'telephonenumber' => 'phone',
            ],
            'transformers' => [],
            'department_map' => [],
            'stale_users' => [
                'strategy' => 'disable',
                'column' => 'is_active',
            ],
            'search' => [
                'default_limit' => 50,
                'arabic_name_attribute' => 'displayname_ar',
            ],
            'manager' => [
                'max_depth' => 10,
            ],
        ]);

        config()->set('ldap-sync.model', User::class);
    }
}
