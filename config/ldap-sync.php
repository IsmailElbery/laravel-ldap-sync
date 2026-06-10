<?php

declare(strict_types=1);
use App\Models\User;

return [

    /*
    |--------------------------------------------------------------------------
    | Eloquent model to sync users into
    |--------------------------------------------------------------------------
    */
    'model' => User::class,

    /*
    |--------------------------------------------------------------------------
    | Unique key linking an AD entry to a local row
    |--------------------------------------------------------------------------
    */
    'unique_key' => [
        'ldap' => 'objectguid',
        'local' => 'ldap_guid',
    ],

    /*
    |--------------------------------------------------------------------------
    | Attribute mapping: ldap_attribute => local_column
    |--------------------------------------------------------------------------
    */
    'attributes' => [
        'samaccountname' => 'username',
        'mail' => 'email',
        'displayname' => 'name',
        'displayname_ar' => 'name_ar',
        'telephonenumber' => 'phone',
        'department' => 'department_name',
        'title' => 'job_title',
    ],

    /*
    |--------------------------------------------------------------------------
    | Value transformers applied after attribute mapping
    |--------------------------------------------------------------------------
    */
    'transformers' => [
        // 'phone' => fn ($value) => preg_replace('/^\+?966/', '0', $value ?? ''),
    ],

    /*
    |--------------------------------------------------------------------------
    | Map OUs to local department names
    |--------------------------------------------------------------------------
    */
    'department_map' => [
        // 'ou=Digitization' => 'General Department of Digitization & AI',
        // 'ou=HR'           => 'Human Resources',
    ],

    /*
    |--------------------------------------------------------------------------
    | Stale user handling
    | strategy: disable | delete | flag | ignore
    |--------------------------------------------------------------------------
    */
    'stale_users' => [
        'strategy' => env('LDAP_SYNC_STALE_STRATEGY', 'disable'),
        'column' => 'is_active',
    ],

    /*
    |--------------------------------------------------------------------------
    | Search settings
    |--------------------------------------------------------------------------
    */
    'search' => [
        'default_limit' => 50,
        'arabic_name_attribute' => env('LDAP_AR_NAME_ATTR', 'displayname'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Manager chain settings
    |--------------------------------------------------------------------------
    */
    'manager' => [
        'max_depth' => 10,
    ],

];
