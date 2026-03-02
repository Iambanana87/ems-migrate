<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | JWT Configuration
    |--------------------------------------------------------------------------
    | Mirrors the legacy JWT_SECRET constant defined in backend/config.php.
    | The same secret is shared between this app and the IAM service (HS256).
    */
    'jwt_secret'      => env('EMS_JWT_SECRET', 'change_this_super_secret_key_32+chars'),
    'jwt_cookie_name' => env('EMS_JWT_COOKIE',  'ems_token'),
    'jwt_ttl'         => (int) env('EMS_JWT_TTL', 3600), // seconds

    /*
    |--------------------------------------------------------------------------
    | IAM (Identity & Access Management) Service
    |--------------------------------------------------------------------------
    | External authentication service called by AuthService.
    | Legacy: http://192.168.110.2/web_develop/iam/cip3/index.php
    */
    'iam' => [
        'base_url' => env('EMS_IAM_URL', 'http://192.168.110.2/web_develop/iam/cip3/index.php'),
        'timeout'  => (int) env('EMS_IAM_TIMEOUT', 10), // seconds
    ],

    /*
    |--------------------------------------------------------------------------
    | Primary Database (production)
    |--------------------------------------------------------------------------
    | Referenced by most controllers. Driven by standard Laravel DB_* env vars.
    | Declared here for documentation parity with legacy config.php.
    */
    'db' => [
        'name' => env('DB_DATABASE', 'production'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Secondary Database (central — AP logs)
    |--------------------------------------------------------------------------
    | Used by ap_status_api.php and log_data.php only.
    | Mapped to the `central` Laravel DB connection.
    */
    'db_central' => [
        'name' => env('DB_CENTRAL_DATABASE', 'central'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Discord Webhook Notifications
    |--------------------------------------------------------------------------
    | Per-device-type webhooks. Mirrors DISCORD_WEBHOOK_URLS constant.
    */
    'discord' => [
        'webhooks' => [
            'mold'    => env('DISCORD_WEBHOOK_MOLD',    ''),
            'tuft'    => env('DISCORD_WEBHOOK_TUFT',    ''),
            'blister' => env('DISCORD_WEBHOOK_BLISTER', ''),
            'default' => env('DISCORD_WEBHOOK_DEFAULT', ''),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Role Hierarchy
    |--------------------------------------------------------------------------
    | Used by AuthService::getPrimaryRole(). First match wins.
    */
    'role_hierarchy' => ['admin', 'manager', 'viewer', 'user'],

];
