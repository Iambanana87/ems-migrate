<?php

$endpoints = [
    [
        'name' => 'list_action_plans (Valid ISSUE)',
        'legacy' => "http://localhost:8080/ems/api.php?action=list_action_plans&action_id=1",
        'laravel' => "http://localhost:8000/api/gateway?c=DeviceAction&m=listActionPlans&action_id=1"
    ],
    [
        'name' => 'list_action_plans (Missing ID -> 0)',
        'legacy' => "http://localhost:8080/ems/api.php?action=list_action_plans",
        'laravel' => "http://localhost:8000/api/gateway?c=DeviceAction&m=listActionPlans"
    ],
    [
        'name' => 'list_action_plans (Negative ID)',
        'legacy' => "http://localhost:8080/ems/api.php?action=list_action_plans&action_id=-5",
        'laravel' => "http://localhost:8000/api/gateway?c=DeviceAction&m=listActionPlans&action_id=-5"
    ]
];

$allPassed = true;

foreach ($endpoints as $ep) {
    echo "====================================\n";
    echo "TESTING: {$ep['name']}\n";

    // Call legacy
    $chLegacy = curl_init($ep['legacy']);
    curl_setopt($chLegacy, CURLOPT_RETURNTRANSFER, true);
    $responseLegacy = curl_exec($chLegacy);
    $legacyCode = curl_getinfo($chLegacy, CURLINFO_HTTP_CODE);
    curl_close($chLegacy);

    // Call laravel
    $chLaravel = curl_init($ep['laravel']);
    curl_setopt($chLaravel, CURLOPT_RETURNTRANSFER, true);
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
             file_put_contents('c:/xampp/htdocs/ems/legacy_list_ap.json', $legacyJson);
             file_put_contents('c:/xampp/htdocs/ems/laravel_list_ap.json', $laravelJson);
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
