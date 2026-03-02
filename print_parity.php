<?php
$files = [
    'legacy_summary.json',
    'laravel_summary.json',
    'legacy_efficiency.json',
    'laravel_efficiency.json'
];
foreach($files as $file) {
    if (file_exists($file)) {
        echo "===============\n$file\n===============\n";
        echo file_get_contents($file) . "\n\n";
    }
}
