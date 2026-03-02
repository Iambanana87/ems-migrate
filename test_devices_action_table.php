<?php

$endpoints = [
    [
        'name' => 'devices_action_table (all)',
        'legacy' => "http://localhost:8080/ems/api.php?action=devices_action_table&display_type=all",
        'laravel' => "http://localhost:8000/api/gateway?c=Report&m=devicesActionTable&display_type=all"
    ],
    [
        'name' => 'devices_action_table (mold)',
        'legacy' => "http://localhost:8080/ems/api.php?action=devices_action_table&display_type=mold",
        'laravel' => "http://localhost:8000/api/gateway?c=Report&m=devicesActionTable&display_type=mold"
    ],
    [
        'name' => 'devices_action_table (missing format, defaults all)',
        'legacy' => "http://localhost:8080/ems/api.php?action=devices_action_table",
        'laravel' => "http://localhost:8000/api/gateway?c=Report&m=devicesActionTable"
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

    if ($legacyJson === $laravelJson) {
         echo "✅ SUCCESS: Exact 100% Structural & Numeric Parity!\n";
         echo "Status Codes: Legacy {$legacyCode} / Laravel {$laravelCode}\n";
    } else {
        echo "❌ DIFFERENCE DETECTED in {$ep['name']}:\n";
        file_put_contents(__DIR__ . '/legacy_devices_action_out.json', $legacyJson);
        file_put_contents(__DIR__ . '/laravel_devices_action_out.json', $laravelJson);
        echo "Saved to legacy_devices_action_out.json and laravel_devices_action_out.json\n";
        $allPassed = false;
        
        file_put_contents(__DIR__ . '/json_diff_devices_action.php', '<?php
            $leg = json_decode(file_get_contents(__DIR__ . "/legacy_devices_action_out.json"), true);
            $lar = json_decode(file_get_contents(__DIR__ . "/laravel_devices_action_out.json"), true);
            
            function compare($path, $v1, $v2) {
                if (is_array($v1) && is_array($v2)) {
                    $keys = array_unique(array_merge(array_keys($v1), array_keys($v2)));
                    foreach ($keys as $k) {
                        if (!array_key_exists($k, $v1)) echo "Missing in legacy: $path.$k\n";
                        elseif (!array_key_exists($k, $v2)) echo "Missing in Laravel: $path.$k\n";
                        else compare("$path.$k", $v1[$k], $v2[$k]);
                    }
                } else {
                    if ($v1 !== $v2) {
                        echo "Mismatch at $path: Legacy=".json_encode($v1).", Laravel=".json_encode($v2)."\n";
                        echo "  Types: Legacy=".gettype($v1).", Laravel=".gettype($v2)."\n\n";
                    }
                }
            }
            compare("root", $leg, $lar);
        ');
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
