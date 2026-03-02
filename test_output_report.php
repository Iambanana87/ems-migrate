<?php
$endpoints = [
    [
        'name' => 'get_output_report (mold)',
        'legacy' => 'http://localhost:8080/ems/api.php?action=get_output_report&process=mold&from=2026-02-26T07:00:00&to=2026-02-27T07:00:00',
        'laravel' => 'http://localhost:8000/api/gateway?c=Report&m=output&process=mold&from=2026-02-26T07:00:00&to=2026-02-27T07:00:00'
    ],
    [
        'name' => 'get_output_report (tuft)',
        'legacy' => 'http://localhost:8080/ems/api.php?action=get_output_report&process=tuft&from=2026-02-26T07:00:00&to=2026-02-27T07:00:00',
        'laravel' => 'http://localhost:8000/api/gateway?c=Report&m=output&process=tuft&from=2026-02-26T07:00:00&to=2026-02-27T07:00:00'
    ],
    [
        'name' => 'get_output_report (blister)',
        'legacy' => 'http://localhost:8080/ems/api.php?action=get_output_report&process=blister&from=2026-02-26T07:00:00&to=2026-02-27T07:00:00',
        'laravel' => 'http://localhost:8000/api/gateway?c=Report&m=output&process=blister&from=2026-02-26T07:00:00&to=2026-02-27T07:00:00'
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
        file_put_contents('c:/xampp/htdocs/ems/legacy_err_out.json', $legacyStr);
        file_put_contents('c:/xampp/htdocs/ems/laravel_err_out.json', $laravelStr);
        echo "Saved to legacy_err_out.json and laravel_err_out.json\n";
        $all_success = false;
    }
}
echo "====================================\n";
if ($all_success) echo "ALL TESTS PASSED.\n";
else echo "SOME TESTS FAILED.\n";
