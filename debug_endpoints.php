<?php
// Debug: Check raw response from both endpoints
$legUrl = 'http://localhost:8080/api.php?view=mold';
$larUrl = 'http://localhost:8000/api/gateway?c=Device&m=machineDetails&process=mold';

foreach ([['legacy', $legUrl], ['laravel', $larUrl]] as [$name, $url]) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_HEADER, true);
    $raw = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);

    $body = substr($raw, $hdrSize);
    $json = json_decode($body, true);

    echo "=== $name ===\n";
    echo "URL  : $url\n";
    echo "Code : $code\n";
    echo "JSON valid: " . ($json !== null ? 'YES' : 'NO (json_last_error=' . json_last_error() . ')') . "\n";
    echo "Body (first 300 chars):\n" . substr($body, 0, 300) . "\n";

    if ($json !== null) {
        // Examine top-level keys
        $keys = is_array($json) ? array_keys($json) : ['(not an array)'];
        echo "Top-level keys: " . implode(', ', $keys) . "\n";

        // Count devices
        if (isset($json['devices'])) {
            echo "Device count (legacy): " . count($json['devices']) . "\n";
        } elseif (is_array($json) && isset($json[0])) {
            echo "Device count (laravel array): " . count($json) . "\n";
        } elseif (isset($json['data'])) {
            echo "Device count (laravel data): " . count($json['data']) . "\n";
        }
    }
    echo "\n";
}
