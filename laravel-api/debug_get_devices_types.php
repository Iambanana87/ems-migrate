<?php
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxLCJ1c2VybmFtZSI6InBhcml0eV9zY2FuIiwicm9sZSI6ImFkbWluIiwiZXhwIjoxODA0MzA1MDU1fQ.21gelENb3clLpoPKLCSIxIsNUI5ltIPqpP5zcShOGpI";

function fetch($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Authorization: Bearer $token",
        "Accept: application/json"
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

$lar = fetch("http://127.0.0.1:8000/api/gateway?c=Device&m=getDevices", $token);
$leg = fetch("http://127.0.0.1:8081/backend/backend.php?action=get_devices", $token);

$larData = json_decode($lar, true);
$legData = json_decode($leg, true);

$fields = ['id', 'flex', 'target_limit', 'cavities', 'efficiency_lower_limit'];

echo "--- LARAVEL ---\n";
if (isset($larData['devices']['mold'][0])) {
    foreach ($fields as $f) {
        $val = $larData['devices']['mold'][0][$f] ?? 'N/A';
        echo "$f: " . gettype($val) . " (" . json_encode($val) . ")\n";
    }
}

echo "\n--- LEGACY ---\n";
if (isset($legData['devices']['mold'][0])) {
    foreach ($fields as $f) {
        $val = $legData['devices']['mold'][0][$f] ?? 'N/A';
        echo "$f: " . gettype($val) . " (" . json_encode($val) . ")\n";
    }
}
