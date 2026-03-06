<?php
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxLCJ1c2VybmFtZSI6InBhcml0eV9zY2FuIiwicm9sZSI6ImFkbWluIiwiZXhwIjoxODA0MzA1MDU1fQ.21gelENb3clLpoPKLCSIxIsNUI5ltIPqpP5zcShOGpI";

function fetch_post($url, $token, $data) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

$url = "http://127.0.0.1:8081/api.php?action=update_device_action_status";
$res = fetch_post($url, $token, ['action_id' => 100000, 'status' => 'done']);

echo "LEGACY RESPONSE: " . $res . "\n";
