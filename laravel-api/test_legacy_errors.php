<?php
$env = file_get_contents('.env');
preg_match('/PARITY_AUTH_TOKEN="?(.*?)"?\n/', $env, $matches);
$token = $matches[1] ?? '';

$out = "";
function ping($action, $params, $method = 'POST') {
    global $token, $out;
    $url = "http://127.0.0.1:8081/backend/backend.php" . ($method === 'GET' ? "?action=" . $action . "&" . http_build_query($params) : "");
    $opts = [
        'http' => [
            'method' => $method,
            'header' => "Content-type: application/x-www-form-urlencoded\r\n" .
                        "Authorization: Bearer " . trim($token) . "\r\n",
            'ignore_errors' => true
        ]
    ];
    if ($method === 'POST') {
        $opts['http']['content'] = http_build_query(array_merge(['action' => $action], $params));
    }
    $ctx = stream_context_create($opts);
    $res = file_get_contents($url, false, $ctx);
    $out .= "===== $action =====\n$res\n\n";
}

ping('update', ['device_id' => 'MOLD-01', 'capacity' => '100']);
ping('action_create', ['device_id' => 'MOLD-01', 'title' => 'Test Task']);
ping('actions_by_device', ['device_id' => 'MOLD-01'], 'GET');

file_put_contents('test_outputs.txt', $out);
