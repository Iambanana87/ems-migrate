<?php
// Probe the raw body from legacy
$ch = curl_init('http://localhost:8080/ems/api.php?view=mold');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
$body = curl_exec($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP Code: $code\n";
echo "Body length: " . strlen($body) . "\n";
echo "--- First 500 chars ---\n";
echo substr($body, 0, 500) . "\n";
echo "--- JSON decode ---\n";
$j = json_decode($body, true);
if ($j !== null) {
    if (isset($j['devices'])) {
        echo "Has 'devices' key. Count = " . count($j['devices']) . "\n";
        $first = reset($j['devices']);
        echo "First device keys: " . implode(', ', array_keys($first)) . "\n";
    } elseif (isset($j[0])) {
        echo "Flat array. Count = " . count($j) . "\n";
        $first = $j[0];
        echo "First device keys: " . implode(', ', array_keys($first)) . "\n";
    } elseif (isset($j['newTimestamp'])) {
        echo "Has 'newTimestamp' key.\n";
        echo "Other keys: " . implode(', ', array_keys($j)) . "\n";
        if (isset($j['devices'])) {
            $first = reset($j['devices']);
            echo "First device keys: " . implode(', ', array_keys($first)) . "\n";
        }
    } else {
        echo "Unknown structure. Top keys: " . implode(', ', array_keys($j)) . "\n";
    }
} else {
    echo "JSON decode FAILED. Error: " . json_last_error_msg() . "\n";
    echo "Raw body starts with: " . substr($body, 0, 100) . "\n";
}
