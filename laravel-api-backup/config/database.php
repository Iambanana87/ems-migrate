<?php

use Illuminate\Support\Str;

return [

    /*
    |--------------------------------------------------------------------------
    | Default Database Connection
    |--------------------------------------------------------------------------
    | Points to "production" — the main EMS database.
    */
    'default' => env('DB_CONNECTION', 'production'),

    'connections' => [

        /*
        |----------------------------------------------------------------------
        | PRIMARY — "production" database
        |----------------------------------------------------------------------
        | Stores: devices, mold, tuft, blister, device_actions, device_status,
        |          audit_trail, factory_layouts, users, live_device_data, etc.
        |
        | Legacy config.php constants:
        |   DB_HOST=localhost | DB_NAME=production
        |   DB_USER=device    | DB_PASS=BtyLX96qZ4nDL!0w
        */
        'production' => [
            'driver'         => 'mysql',
            'url'            => env('DB_URL'),
            'host'           => env('DB_HOST',     '127.0.0.1'),
            'port'           => env('DB_PORT',     '3306'),
            'database'       => env('DB_DATABASE', 'production'),
            'username'       => env('DB_USERNAME', 'device'),
            'password'       => env('DB_PASSWORD', ''),
            'unix_socket'    => env('DB_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => null,
            'options'        => extension_loaded('pdo_mysql') ? array_filter([
                PDO::MYSQL_ATTR_SSL_CA => env('MYSQL_ATTR_SSL_CA'),
            ]) : [],
        ],

        /*
        |----------------------------------------------------------------------
        | SECONDARY — "central" database
        |----------------------------------------------------------------------
        | Stores: ap_logs (Access Point status logs from ESP32 devices)
        |
        | This is a SEPARATE database on the same MySQL server.
        | Legacy used a hardcoded connection directly in ap_status_api.php
        | and log_data.php (bypassing config.php — a tech debt now resolved).
        |
        | Env vars: DB_CENTRAL_HOST, DB_CENTRAL_DATABASE, DB_CENTRAL_USERNAME,
        |            DB_CENTRAL_PASSWORD
        | Migration: php artisan migrate --database=central
        */
        'central' => [
            'driver'         => 'mysql',
            'url'            => env('DB_CENTRAL_URL'),
            'host'           => env('DB_CENTRAL_HOST',     '127.0.0.1'),
            'port'           => env('DB_CENTRAL_PORT',     '3306'),
            'database'       => env('DB_CENTRAL_DATABASE', 'central'),
            'username'       => env('DB_CENTRAL_USERNAME', 'device'),
            'password'       => env('DB_CENTRAL_PASSWORD', ''),
            'unix_socket'    => env('DB_CENTRAL_SOCKET', ''),
            'charset'        => 'utf8mb4',
            'collation'      => 'utf8mb4_unicode_ci',
            'prefix'         => '',
            'prefix_indexes' => true,
            'strict'         => true,
            'engine'         => null,
        ],

        // SQLite — kept for local unit tests only
        'sqlite' => [
            'driver'                  => 'sqlite',
            'url'                     => env('DB_URL'),
            'database'                => env('DB_DATABASE', database_path('database.sqlite')),
            'prefix'                  => '',
            'foreign_key_constraints' => env('DB_FOREIGN_KEYS', true),
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Migration Repository Table
    |--------------------------------------------------------------------------
    */
    'migrations' => [
        'table'               => 'migrations',
        'update_date_on_publish' => true,
    ],

    /*
    |--------------------------------------------------------------------------
    | Redis Databases
    |--------------------------------------------------------------------------
    | Used for cache / queues if configured. Defaults left for completeness.
    */
    'redis' => [
        'client' => env('REDIS_CLIENT', 'phpredis'),
        'options' => [
            'cluster' => env('REDIS_CLUSTER', 'redis'),
            'prefix'  => env('REDIS_PREFIX', Str::slug(env('APP_NAME', 'laravel'), '_') . '_database_'),
        ],
        'default' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_DB', '0'),
        ],
        'cache' => [
            'url'      => env('REDIS_URL'),
            'host'     => env('REDIS_HOST', '127.0.0.1'),
            'username' => env('REDIS_USERNAME'),
            'password' => env('REDIS_PASSWORD'),
            'port'     => env('REDIS_PORT', '6379'),
            'database' => env('REDIS_CACHE_DB', '1'),
        ],
    ],

];
