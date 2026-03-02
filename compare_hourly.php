<?php
$devices = ['MOLD-01', 'TUFT-01', 'BLIST-01'];
$date = '2026-02-26';

foreach ($devices as $dev) {
    echo "====================================\n";
    echo "TESTING DEVICE: $dev\n";
    
    $urlLegacy = "http://localhost:8080/ems/api.php?action=get_hourly_report&device_id=$dev&report_date=$date";
    $urlLaravel = "http://localhost:8000/api/gateway?c=Report&m=hourly&device_id=$dev&report_date=$date";
    
    $context = stream_context_create([
        'http' => ['ignore_errors' => true]
    ]);
    
    $jsonLegacy = @file_get_contents($urlLegacy, false, $context);
    $jsonLaravel = @file_get_contents($urlLaravel, false, $context);
    
    if (!$jsonLegacy || !$jsonLaravel) {
        echo "Failed to fetch from one of the APIs\n";
        echo "Legacy: " . ($jsonLegacy ? "OK" : "FAILED") . "\n";
        echo "Laravel: " . ($jsonLaravel ? "OK" : "FAILED") . "\n";
        continue;
    }
    
    $legacy = json_decode($jsonLegacy, true);
    $laravel = json_decode($jsonLaravel, true);
    
    $legacyStr = json_encode($legacy, JSON_PRETTY_PRINT);
    $laravelStr = json_encode($laravel, JSON_PRETTY_PRINT);
    
    if ($legacyStr === $laravelStr) {
        echo "✅ SUCCESS: Exact 100% Structural and Data Parity Confirmed!\n";
    } else {
        echo "❌ DIFFERENCE DETECTED:\n";
        // Simple top level key check
        $allKeys = array_unique(array_merge(array_keys($legacy), array_keys($laravel)));
        foreach ($allKeys as $key) {
            if (!isset($legacy[$key])) echo "  Missing KEY in legacy: $key\n";
            elseif (!isset($laravel[$key])) echo "  Missing KEY in laravel: $key\n";
            elseif (json_encode($legacy[$key]) !== json_encode($laravel[$key])) {
                echo "  Difference in Key '$key'\n";
                if ($key === 'hourly_data') {
                    for ($i=0; $i<24; $i++) {
                        if (json_encode($legacy[$key][$i]) !== json_encode($laravel[$key][$i])) {
                            echo "    Diff at HOUR index $i:\n";
                            echo "      Legacy:  " . json_encode($legacy[$key][$i]) . "\n";
                            echo "      Laravel: " . json_encode($laravel[$key][$i]) . "\n";
                        }
                    }
                }
            }
        }
        
        file_put_contents("c:/xampp/htdocs/ems/{$dev}_legacy.json", $legacyStr);
        file_put_contents("c:/xampp/htdocs/ems/{$dev}_laravel.json", $laravelStr);
    }
    echo "====================================\n\n";
}
