<?php
$token = "eyJhbGciOiJIUzI1NiIsInR5cCI6IkpXVCJ9.eyJ1c2VyX2lkIjoxLCJ1c2VybmFtZSI6InBhcml0eV9zY2FuIiwicm9sZSI6ImFkbWluIiwiZXhwIjoxODA0MzA1MDU1fQ.21gelENb3clLpoPKLCSIxIsNUI5ltIPqpP5zcShOGpI";

function fetch($url, $token) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ["Authorization: Bearer $token"]);
    $res = curl_exec($ch);
    curl_close($ch);
    return $res;
}

$lar = fetch("http://127.0.0.1:8000/api/gateway?c=Device&m=getDevices", $token);
$leg = fetch("http://127.0.0.1:8081/backend/backend.php?action=get_devices", $token);

function getMold0Raw($json) {
    $data = json_decode($json, true);
    if (!isset($data['devices']['mold'][0])) return "N/A";
    $device = $data['devices']['mold'][0];
    // Find the device in the raw string to see its raw format
    // This is hard to do reliably with string search if multiple devices exist.
    // Let's just re-encode specifically.
    return json_encode($device, JSON_PRETTY_PRINT);
}

// Better: let's just look at the raw string of the first few hundred chars
echo "--- LARAVEL RAW START ---\n";
echo substr($lar, 0, 500) . "...\n";

echo "\n--- LEGACY RAW START ---\n";
echo substr($leg, 0, 500) . "...\n";
