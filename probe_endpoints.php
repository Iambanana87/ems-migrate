<?php
function fetchBody(string $url): ?array {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $body = curl_exec($ch);
    curl_close($ch);
    return $body !== false ? json_decode($body, true) : null;
}
$leg = fetchBody('http://localhost:8080/api.php?view=mold');
$lar = fetchBody('http://localhost:8000/api/gateway?c=Device&m=machineDetails&process=mold');

echo "=== LEGACY ===\n";
if (is_array($leg)) {
    // Check if it has `devices` key or is flat
    if (isset($leg['devices'])) {
        $arr = $leg['devices'];
        echo "Has 'devices' key. Count = " . count($arr) . "\n";
    } else {
        $arr = array_values($leg);
        echo "Flat array. Count = " . count($arr) . "\n";
    }
    if (!empty($arr)) {
        $first = $arr[0];
        echo "First row keys: " . implode(', ', array_keys($first)) . "\n";
        $live = is_string($first['live_data'] ?? null) 
            ? json_decode($first['live_data'], true) 
            : ($first['live_data'] ?? ($first['json_live_data'] ?? null));
        if ($live) {
            echo "Live data keys: " . implode(', ', array_keys($live)) . "\n";
        } else {
            echo "Live data: null/empty\n";
        }
    }
} else {
    echo "FAILED to parse JSON (last error=" . json_last_error() . ")\n";
}

echo "\n=== LARAVEL ===\n";
if (is_array($lar)) {
    if (isset($lar['data'])) {
        $arr = $lar['data'];
        echo "Has 'data' key. Count = " . count($arr) . "\n";
    } elseif (isset($lar[0])) {
        $arr = $lar;
        echo "Flat array. Count = " . count($arr) . "\n";
    } else {
        $arr = [];
        echo "No data key or array. Keys: " . implode(', ', array_keys($lar)) . "\n";
    }
    if (!empty($arr)) {
        $first = $arr[0];
        echo "First row keys: " . implode(', ', array_keys($first)) . "\n";
    }
} else {
    echo "FAILED to parse Laravel JSON (last error=" . json_last_error() . ")\n";
}
