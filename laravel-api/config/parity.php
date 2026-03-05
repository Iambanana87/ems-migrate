<?php

declare(strict_types=1);

/**
 * parity.php — Configuration for the parity:scan Artisan command.
 *
 * Used by: app/Console/Commands/ParityScan.php
 *          app/Services/Parity/
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Base URLs
    |--------------------------------------------------------------------------
    | legacy_base : The root URL of the legacy api.php endpoint.
    | laravel_base : The root URL of the Laravel gateway (this instance).
    |
    */
    'legacy_base'  => env('PARITY_LEGACY_BASE',  'http://localhost:8080/ems/api.php'),
    'laravel_base' => env('PARITY_LARAVEL_BASE', 'http://localhost:8000/api/gateway'),

    /*
    |--------------------------------------------------------------------------
    | Authentication
    |--------------------------------------------------------------------------
    | A Bearer token sent with every request. Leave empty for public endpoints.
    |
    */
    'auth_token' => env('PARITY_AUTH_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | HTTP Client
    |--------------------------------------------------------------------------
    */
    'timeout_seconds' => (int) env('PARITY_TIMEOUT', 10),
    'retry_times'     => (int) env('PARITY_RETRY',   2),
    'retry_sleep_ms'  => (int) env('PARITY_RETRY_SLEEP', 300),

    /*
    |--------------------------------------------------------------------------
    | Float Comparison
    |--------------------------------------------------------------------------
    | tolerance : Maximum absolute difference allowed between float values
    |             before it is reported as drift. Set to 0 for strict mode.
    |
    */
    'float_tolerance' => (float) env('PARITY_FLOAT_TOLERANCE', 0.001),

    /*
    |--------------------------------------------------------------------------
    | Endpoints
    |--------------------------------------------------------------------------
    | Each entry maps a human-readable name to the legacy and Laravel
    | query-string parameters that produce equivalent responses.
    |
    | legacy_params  : Appended to PARITY_LEGACY_BASE as query string.
    | laravel_params : Appended to PARITY_LARAVEL_BASE as query string.
    | sample_key     : Dot-notation path into the response that holds
    |                  a list to sample from (leave null for scalar responses).
    |
    */
    'endpoints' => [

        // --- CERTIFIED (Phase 0/A/B) ---
        'machine_details.mold' => [
            'legacy_params'  => ['action' => 'get_machine_details', 'process' => 'mold'],
            'laravel_params' => ['c' => 'Device', 'm' => 'machineDetails', 'process' => 'mold'],
            'sample_key'     => null,
        ],
        'machine_details.tuft' => [
            'legacy_params'  => ['action' => 'get_machine_details', 'process' => 'tuft'],
            'laravel_params' => ['c' => 'Device', 'm' => 'machineDetails', 'process' => 'tuft'],
            'sample_key'     => null,
        ],
        'machine_details.blister' => [
            'legacy_params'  => ['action' => 'get_machine_details', 'process' => 'blister'],
            'laravel_params' => ['c' => 'Device', 'm' => 'machineDetails', 'process' => 'blister'],
            'sample_key'     => null,
        ],
        'tc_meta' => [
            'legacy_params'  => ['action' => 'tc_meta'],
            'laravel_params' => ['c' => 'Report', 'm' => 'getTcMeta'],
            'sample_key'     => null,
        ],
        'count_actions' => [
            'legacy_params'  => ['action' => 'count_actions'],
            'laravel_params' => ['c' => 'Report', 'm' => 'countActions'],
            'sample_key'     => null,
        ],
        'count_device_status' => [
            'legacy_params'  => ['action' => 'count_device_status'],
            'laravel_params' => ['c' => 'Report', 'm' => 'countDeviceStatus'],
            'sample_key'     => null,
        ],
        'count_flexible' => [
            'legacy_params'  => ['action' => 'count_flexible'],
            'laravel_params' => ['c' => 'Report', 'm' => 'countFlexible'],
            'sample_key'     => null,
        ],
        'get_families' => [
            'legacy_params'  => ['action' => 'get_families', 'process' => 'mold'],
            'laravel_params' => ['c' => 'Family', 'm' => 'index', 'process' => 'mold'],
            'sample_key'     => null,
        ],

        // --- API SCOPE (Uncertified) ---
        'search_device' => [
            'legacy_params'  => ['action' => 'search_device', 'device_id' => 'MOLD-01', 'from' => '2025-01-01', 'to' => '2025-01-07'],
            'laravel_params' => ['c' => 'Device', 'm' => 'history', 'device_id' => 'MOLD-01', 'from' => '2025-01-01', 'to' => '2025-01-07'],
            'sample_key'     => null,
        ],
        'get_summary_report' => [
            'legacy_params'  => ['action' => 'get_summary_report'],
            'laravel_params' => ['c' => 'Report', 'm' => 'summary'],
            'sample_key'     => null,
        ],
        'get_output_report' => [
            'legacy_params'  => ['action' => 'get_output_report'],
            'laravel_params' => ['c' => 'Report', 'm' => 'output'],
            'sample_key'     => null,
        ],
        'get_output_report_bulk' => [
            'legacy_params'  => ['action' => 'get_output_report_bulk', 'from' => '2025-01-01', 'to' => '2025-01-02'],
            'laravel_params' => ['c' => 'Report', 'm' => 'outputBulk', 'from' => '2025-01-01', 'to' => '2025-01-02'],
            'sample_key'     => null,
        ],
        'get_efficiency_report_data' => [
            'legacy_params'  => ['action' => 'get_efficiency_report_data', 'process' => 'mold', 'from' => '2025-01-01', 'to' => '2025-01-02'],
            'laravel_params' => ['c' => 'Report', 'm' => 'efficiency', 'process' => 'mold', 'from' => '2025-01-01', 'to' => '2025-01-02'],
            'sample_key'     => null,
        ],
        'get_hourly_report' => [
            'legacy_params'  => ['action' => 'get_hourly_report', 'process' => 'mold', 'from' => '2025-01-01', 'to' => '2025-01-02', 'device_id' => 'MOLD-01'],
            'laravel_params' => ['c' => 'Report', 'm' => 'hourly', 'process' => 'mold', 'from' => '2025-01-01', 'to' => '2025-01-02', 'device_id' => 'MOLD-01'],
            'sample_key'     => null,
        ],
        'actions_board' => [
            'legacy_params'  => ['action' => 'actions_board'],
            'laravel_params' => ['c' => 'Report', 'm' => 'actionsBoard'],
            'sample_key'     => null,
        ],
        'devices_action_table' => [
            'legacy_params'  => ['action' => 'devices_action_table'],
            'laravel_params' => ['c' => 'Report', 'm' => 'devicesActionTable'],
            'sample_key'     => null,
        ],
        'list_users' => [
            'legacy_params'  => ['action' => 'list_users'],
            'laravel_params' => ['c' => 'User', 'm' => 'listUsers'],
            'sample_key'     => null,
        ],
        'list_device_actions_v2' => [
            'legacy_params'  => ['action' => 'list_device_actions_v2', 'device_id' => 'MOLD-01'],
            'laravel_params' => ['c' => 'DeviceAction', 'm' => 'listV2', 'device_id' => 'MOLD-01'],
            'sample_key'     => null,
        ],
        'create_device_action' => [
            'legacy_params'  => ['action' => 'create_device_action', 'device_id' => 'MOLD-01', 'title' => 'Test Parity Action'],
            'laravel_params' => ['c' => 'DeviceAction', 'm' => 'store', 'device_id' => 'MOLD-01', 'title' => 'Test Parity Action'],
            'sample_key'     => null,
        ],
        'update_device_action_status' => [
            'legacy_params'  => ['action' => 'update_device_action_status', 'action_id' => '99999', 'status' => 'done'],
            'laravel_params' => ['c' => 'DeviceAction', 'm' => 'update', 'action_id' => '99999', 'status' => 'done'],
            'sample_key'     => null,
        ],
        'list_action_plans' => [
            'legacy_params'  => ['action' => 'list_action_plans', 'action_id' => '99999'],
            'laravel_params' => ['c' => 'DeviceAction', 'm' => 'listActionPlans', 'action_id' => '99999'],
            'sample_key'     => null,
        ],
        'get_total_count' => [
            'legacy_params'  => ['action' => 'get_total_count'],
            'laravel_params' => ['c' => 'Report', 'm' => 'getTotalCount'],
            'sample_key'     => null,
        ],

        // --- BACKEND SCOPE (Uncertified) ---
        'add' => [
            'legacy_params'  => ['action' => 'add', 'device_id' => 'PARITY-TEST-01', 'display_type' => 'mold'],
            'laravel_params' => ['c' => 'Device', 'm' => 'store', 'device_id' => 'PARITY-TEST-01', 'display_type' => 'mold'],
            'sample_key'     => null,
        ],
        'update' => [
            'legacy_params'  => ['action' => 'update', 'device_id' => 'MOLD-01', 'capacity' => '100'],
            'laravel_params' => ['c' => 'Device', 'm' => 'update', 'device_id' => 'MOLD-01', 'capacity' => '100'],
            'sample_key'     => null,
        ],
        'delete' => [
            'legacy_params'  => ['action' => 'delete', 'device_id' => 'PARITY-TEST-01'],
            'laravel_params' => ['c' => 'Device', 'm' => 'destroy', 'device_id' => 'PARITY-TEST-01'],
            'sample_key'     => null,
        ],
        'get_devices' => [
            'legacy_params'  => ['action' => 'get_devices'],
            'laravel_params' => ['c' => 'Device', 'm' => 'getDevices'],
            'sample_key'     => null,
        ],
        'action_create' => [
            'legacy_params'  => ['action' => 'action_create', 'device_id' => 'MOLD-01', 'title' => 'Test Task'],
            'laravel_params' => ['c' => 'DeviceAction', 'm' => 'storeBackend', 'device_id' => 'MOLD-01', 'title' => 'Test Task'],
            'sample_key'     => null,
        ],
        'action_update' => [
            'legacy_params'  => ['action' => 'action_update', 'action_id' => '99999'],
            'laravel_params' => ['c' => 'DeviceAction', 'm' => 'updateBackend', 'action_id' => '99999'],
            'sample_key'     => null,
        ],
        'action_delete' => [
            'legacy_params'  => ['action' => 'action_delete', 'action_id' => '99999'],
            'laravel_params' => ['c' => 'DeviceAction', 'm' => 'destroy', 'id' => '99999'],
            'sample_key'     => null,
        ],
        'approve_action' => [
            'legacy_params'  => ['action' => 'approve_action', 'action_id' => '99999'],
            'laravel_params' => ['c' => 'DeviceAction', 'm' => 'approve', 'id' => '99999'],
            'sample_key'     => null,
        ],
        'reject_action' => [
            'legacy_params'  => ['action' => 'reject_action', 'action_id' => '99999'],
            'laravel_params' => ['c' => 'DeviceAction', 'm' => 'reject', 'id' => '99999'],
            'sample_key'     => null,
        ],
        'actions_by_device' => [
            'legacy_params'  => ['action' => 'actions_by_device', 'device_id' => 'MOLD-01'],
            'laravel_params' => ['c' => 'DeviceAction', 'm' => 'listByDevice', 'device_id' => 'MOLD-01'],
            'sample_key'     => null,
        ],

        // --- AUTH / OTHER ---
        'whoami' => [
            'legacy_params'  => ['action' => 'whoami'],
            'laravel_params' => ['c' => 'Auth', 'm' => 'me'],
            'sample_key'     => null,
        ],
        'preview_next_codes' => [
            'legacy_params'  => ['action' => 'preview_next_codes'],
            'laravel_params' => ['c' => 'DeviceAction', 'm' => 'previewNextCodes'],
            'sample_key'     => null,
        ],

    ],

    /*
    |--------------------------------------------------------------------------
    | Whitelist
    |--------------------------------------------------------------------------
    | Keys listed here are IGNORED during comparison, globally or per-endpoint.
    |
    | global  : Ignored on every endpoint.
    | per_endpoint : Keyed by endpoint name (same keys as 'endpoints' above).
    |
    | Each value is a dot-notation path relative to a single list item,
    | or a top-level key for scalar responses.
    |
    */
    'whitelist' => [
        'global' => [
            'timestamp',      // Server timestamp — always differs
            'newTimestamp',   // Same
        ],
        'per_endpoint' => [
            // 'machine_details.mold' => ['live_data.datetime'],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Report Output
    |--------------------------------------------------------------------------
    | log_channel : Laravel log channel to write scan results to.
    | report_path : Absolute path where JSON report files are written.
    |               Set to null to disable file output.
    |
    */
    'log_channel' => env('PARITY_LOG_CHANNEL', 'daily'),
    'report_path' => env('PARITY_REPORT_PATH', storage_path('logs/parity')),

];
