<?php
$env = file_get_contents('.env');
preg_match('/PARITY_AUTH_TOKEN="?(.*?)"?\n/', $env, $matches);
$token = $matches[1] ?? '';

$url = "http://127.0.0.1:8081/backend/backend.php";
$data = http_build_query([
    'action' => 'add',
    'device_id' => 'PARITY-TEST-01',
    'display_type' => 'mold',
    'product' => 'Family A',
    'target_limit' => 0,
    'cavities' => 1
]);
$opts = [
    'http' => [
        'method' => 'POST',
        'header' => "Content-type: application/x-www-form-urlencoded\r\n" .
                    "Authorization: Bearer " . trim($token) . "\r\n",
        'content' => $data,
        'ignore_errors' => true
    ]
];
$ctx = stream_context_create($opts);
$res = file_get_contents($url, false, $ctx);
echo "Headers:\n";
print_r($http_response_header);
echo "\nBody:\n";
echo $res;
