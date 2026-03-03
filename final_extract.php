<?php
$content = file_get_contents('c:/xampp/htdocs/ems/api.php');

// Collect all strings compared with things that look like action variables
// common patterns: $action === '...', $_GET['action'] === '...', etc.
$patterns = [
    '/\$action\s*==\s*\'([a-z0-9_]+)\'/',
    '/\$action\s*===\s*\'([a-z0-9_]+)\'/',
    '/\[\'action\'\]\s*==\s*\'([a-z0-9_]+)\'/',
    '/\[\'action\'\]\s*===\s*\'([a-z0-9_]+)\'/',
    '/in_array\(\s*\(string\)\$action\s*,\s*\[(.*?)\]\s*,\s*true\)/s'
];

$allActions = [];
foreach ($patterns as $pattern) {
    if (preg_match_all($pattern, $content, $m)) {
        if (isset($m[2])) { // matched array content
             preg_match_all('/\'([a-z0-9_]+)\'/', $m[2][0], $matches);
             foreach ($matches[1] as $a) $allActions[] = $a;
        } else {
             foreach ($m[1] as $a) $allActions[] = $a;
        }
    }
}

// Special case for $PUBLIC_ACTIONS
if (preg_match('/\$PUBLIC_ACTIONS\s*=\s*\[(.*?)\];/s', $content, $m)) {
    preg_match_all('/\'([a-z0-9_]+)\'/', $m[1], $matches);
    foreach ($matches[1] as $a) $allActions[] = $a;
}

$allActions = array_unique($allActions);
sort($allActions);

$migrated = [
    'get_summary_report',
    'get_efficiency_report_data',
    'get_output_report',
    'get_output_report_bulk',
    'get_hourly_report',
    'actions_board',
    'devices_action_table',
    'create_action_public',
    'list_device_actions_v2',
    'list_action_plans',
    'update_action_plan_status',
    'delete_action_plan',
    'get_machine_details',
    'search_device',
    'get_families'
];

$result = [];
foreach ($allActions as $a) {
    $result[] = [
        'name' => $a,
        'migrated' => in_array($a, $migrated)
    ];
}

file_put_contents('c:/xampp/htdocs/ems/final_actions.json', json_encode($result, JSON_PRETTY_PRINT));
echo "DONE";
