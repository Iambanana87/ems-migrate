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

$leg = fetch("http://127.0.0.1:8081/backend/backend.php?action=get_devices", $token);
$data = json_decode($leg, true);

foreach($data['devices']['mold'] as $i => $d) {
    if ($i > 5) break;
    echo "Device " . $d['device_id'] . ":\n";
    // We need to find this device in the raw string to see the raw precision
    $search = '"device_id":"' . $d['device_id'] . '"';
    $pos = strpos($leg, $search);
    if ($pos !== false) {
        // Snippet around this device
        echo substr($leg, $pos, 200) . "\n\n";
    }
}
