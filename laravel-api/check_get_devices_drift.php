<?php
$file = 'get_devices_final.json';
if (!file_exists($file)) die("File not found\n");
$content = file_get_contents($file);
// Try to handle UTF-16 if it's still there
if (substr($content, 0, 2) === "\xFF\xFE" || substr($content, 0, 2) === "\xFE\xFF") {
    $content = mb_convert_encoding($content, 'UTF-8', 'UTF-16');
}

$start = strpos($content, '{');
if ($start !== false) {
    $content = substr($content, $start);
}

$data = json_decode($content, true);
if (!$data) {
    echo "Invalid JSON at start: " . substr($content, 0, 50) . "...\n";
    die();
}

$drifts = $data['endpoints']['get_devices']['drifts'] ?? [];
echo "Drift Count: " . count($drifts) . "\n";
foreach ($drifts as $d) {
    echo "[" . $d['type'] . "] " . $d['path'] . ": " . $d['detail'] . "\n";
}
