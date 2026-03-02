<?php
$endpoints = [
    [
        'name' => 'get_summary_report',
        'legacy' => 'http://localhost:8080/ems/api.php?action=get_summary_report&from=2026-02-26T07:00:00&to=2026-02-27T07:00:00',
        'laravel' => 'http://localhost:8000/api/gateway?c=Report&m=summary&from=2026-02-26T07:00:00&to=2026-02-27T07:00:00'
    ],
    [
        'name' => 'get_efficiency_report_data (mold)',
        'legacy' => 'http://localhost:8080/ems/api.php?action=get_efficiency_report_data&process=mold&from=2026-02-26T07:00:00&to=2026-02-27T07:00:00',
        'laravel' => 'http://localhost:8000/api/gateway?c=Report&m=efficiency&process=mold&from=2026-02-26T07:00:00&to=2026-02-27T07:00:00'
    ],
    [
        'name' => 'get_hourly_report (MOLD-01)',
        'legacy' => 'http://localhost:8080/ems/api.php?action=get_hourly_report&device_id=MOLD-01&report_date=2026-02-26',
        'laravel' => 'http://localhost:8000/api/gateway?c=Report&m=hourly&device_id=MOLD-01&report_date=2026-02-26'
    ]
];

$context = stream_context_create([
    'http' => ['ignore_errors' => true]
]);

$all_success = true;

foreach ($endpoints as $ep) {
    echo "====================================\n";
    echo "TESTING: {$ep['name']}\n";
    
    $jsonLegacy = @file_get_contents($ep['legacy'], false, $context);
    $jsonLaravel = @file_get_contents($ep['laravel'], false, $context);
    
    if (!$jsonLegacy || (!$jsonLaravel && $jsonLaravel !== "")) {
        echo "Failed to fetch from one of the APIs\n";
        echo "Legacy: " . ($jsonLegacy ? "OK" : "FAILED") . "\n";
        echo "Laravel: " . ($jsonLaravel ? "OK" : "FAILED") . "\n";
        $all_success = false;
        continue;
    }
    
    $legacy = json_decode($jsonLegacy, true);
    $laravel = json_decode($jsonLaravel, true);
    
    $legacyStr = json_encode($legacy, JSON_PRETTY_PRINT);
    $laravelStr = json_encode($laravel, JSON_PRETTY_PRINT);
    
    if ($legacyStr === $laravelStr) {
        echo "✅ SUCCESS: Exact 100% Structural and Data Parity Confirmed!\n";
    } else {
        echo "❌ DIFFERENCE DETECTED in {$ep['name']}:\n";
        file_put_contents('c:/xampp/htdocs/ems/legacy_err.json', $legacyStr);
        file_put_contents('c:/xampp/htdocs/ems/laravel_err.json', $laravelStr);
        echo "Saved to legacy_err.json and laravel_err.json\n";
        $all_success = false;
    }
}
echo "====================================\n";
if ($all_success) echo "ALL REGRESSION TESTS PASSED.\n";
else echo "SOME REGRESSION TESTS FAILED.\n";
