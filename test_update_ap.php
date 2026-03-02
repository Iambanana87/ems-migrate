<?php

$endpoints = [
    [
        'name' => 'update_action_plan_status (Missing ID -> 400 test)',
        'legacy' => "http://localhost:8080/ems/api.php?action=update_action_plan_status",
        'laravel' => "http://localhost:8000/api/gateway?c=DeviceAction&m=updateActionPlanStatus",
        'payload' => []
    ],
    // Given that modifying live DB states during test runs might cause false negatives / structural drift if we randomly assert open/done, 
    // We strictly verify the fallback error condition (plan_id <= 0) which exercises the custom mapping.
];

$allPassed = true;

foreach ($endpoints as $ep) {
    echo "====================================\n";
    echo "TESTING: {$ep['name']}\n";

    // Call legacy
    $chLegacy = curl_init($ep['legacy']);
    curl_setopt($chLegacy, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chLegacy, CURLOPT_POST, true);
    curl_setopt($chLegacy, CURLOPT_POSTFIELDS, http_build_query($ep['payload']));
    $responseLegacy = curl_exec($chLegacy);
    $legacyCode = curl_getinfo($chLegacy, CURLINFO_HTTP_CODE);
    curl_close($chLegacy);

    // Call laravel
    $chLaravel = curl_init($ep['laravel']);
    curl_setopt($chLaravel, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($chLaravel, CURLOPT_POST, true);
    curl_setopt($chLaravel, CURLOPT_POSTFIELDS, http_build_query($ep['payload']));
    $responseLaravel = curl_exec($chLaravel);
    $laravelCode = curl_getinfo($chLaravel, CURLINFO_HTTP_CODE);
    curl_close($chLaravel);

    $legacyData = json_decode($responseLegacy, true);
    $laravelData = json_decode($responseLaravel, true);

    $legacyJson = json_encode($legacyData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
    $laravelJson = json_encode($laravelData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);

    if ($legacyCode === $laravelCode) {
        if ($legacyJson === $laravelJson) {
             echo "✅ SUCCESS: Exact 100% Structural & Numeric Parity!\n";
        } else {
             echo "❌ DIFFERENCE DETECTED in JSON payload\n";
             file_put_contents('c:/xampp/htdocs/ems/legacy_update_ap.json', $legacyJson);
             file_put_contents('c:/xampp/htdocs/ems/laravel_update_ap.json', $laravelJson);
             $allPassed = false;
        }
    } else {
        echo "❌ Status Codes: Legacy {$legacyCode} / Laravel {$laravelCode}\n";
        echo "Legacy response: " . $responseLegacy . "\n";
        echo "Laravel response: " . $responseLaravel . "\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "====================================\n";
    echo "ALL TESTS PASSED.\n";
    exit(0);
} else {
    echo "====================================\n";
    echo "SOME TESTS FAILED.\n";
    exit(1);
}
