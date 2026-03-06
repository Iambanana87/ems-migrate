<?php
$token = trim(file_get_contents('token_clean.txt'));

function fetch($url, $token) {
    echo "Fetching $url...\n";
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $res = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    if (curl_errno($ch)) {
        echo 'Curl error: ' . curl_error($ch) . "\n";
    }
    curl_close($ch);
    echo "HTTP Status: $httpCode\n";
    return $res;
}

$lar = fetch("http://127.0.0.1:8000/api/gateway?c=Device&m=getDevices", $token);
$leg = fetch("http://127.0.0.1:8081/backend/backend.php?action=get_devices", $token);

$larData = json_decode($lar, true);
$legData = json_decode($leg, true);

if (!$larData) { echo "Laravel Response not JSON: " . substr($lar, 0, 100) . "\n"; }
if (!$legData) { echo "Legacy Response not JSON: " . substr($leg, 0, 100) . "\n"; }

echo "LARAVEL MOLD[0]:\n";
if (isset($larData['devices']['mold'][0])) {
    echo json_encode($larData['devices']['mold'][0], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No mold[0] in Laravel\n";
}

echo "\nLEGACY MOLD[0]:\n";
if (isset($legData['devices']['mold'][0])) {
    echo json_encode($legData['devices']['mold'][0], JSON_PRETTY_PRINT) . "\n";
} else {
    echo "No mold[0] in Legacy\n";
}
