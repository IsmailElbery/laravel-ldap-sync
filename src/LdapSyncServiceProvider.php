<?php

declare(strict_types=1);

namespace IsmailElbery\LdapSync;

use IsmailElbery\LdapSync\Commands\SearchCommand;
use IsmailElbery\LdapSync\Commands\SyncCommand;
use IsmailElbery\LdapSync\Services\LdapSyncService;
use IsmailElbery\LdapSync\Services\LdapUserSearchService;
use IsmailElbery\LdapSync\Services\OuResolver;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class LdapSyncServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-ldap-sync')
            ->hasConfigFile('ldap-sync')
            ->hasMigration('add_ldap_columns_to_users_table')
            ->hasCommands([
                SyncCommand::class,
                SearchCommand::class,
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->singleton(OuResolver::class, function ($app): OuResolver {
            $baseDn = $app['config']->get('ldap.connections.default.base_dn', '');
            $ouList = env('LDAP_SYNC_OUS', '');

            return new OuResolver($baseDn, $ouList);
        });

        $this->app->singleton(LdapSyncService::class, function ($app): LdapSyncService {
            return new LdapSyncService(
                $app->make(OuResolver::class),
                $app['config']->get('ldap-sync', []),
            );
        });

        $this->app->singleton(LdapUserSearchService::class, function ($app): LdapUserSearchService {
            return new LdapUserSearchService(
                $app->make(OuResolver::class),
                $app['config']->get('ldap-sync', []),
            );
        });
    }
}
