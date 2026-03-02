<?php
$legacyStr = file_get_contents('c:\xampp\htdocs\ems\legacy_summary.json');
$laravelStr = file_get_contents('c:\xampp\htdocs\ems\laravel_summary.json');

// Strip out BOM if present
$legacyStr = preg_replace('/^[\xef\xbb\xbf]+/', '', $legacyStr);
$laravelStr = preg_replace('/^[\xef\xbb\xbf]+/', '', $laravelStr);

$legacy = json_decode(trim($legacyStr), true);
$laravel = json_decode(trim($laravelStr), true);

echo "====================================\n";
echo "LEGACY JSON OUTPUT:\n";
echo "====================================\n";
echo json_encode($legacy, JSON_PRETTY_PRINT) . "\n\n";

echo "====================================\n";
echo "LARAVEL JSON OUTPUT:\n";
echo "====================================\n";
echo json_encode($laravel, JSON_PRETTY_PRINT) . "\n\n";

echo "====================================\n";
echo "COMPARISON RESULTS:\n";
echo "====================================\n";

if ($legacy === null) {
    echo "ERROR: Legacy JSON could not be parsed: " . json_last_error_msg() . "\n";
    echo "Raw legacy output:\n" . substr($legacyStr, 0, 500) . "\n";
}
if ($laravel === null) {
    echo "ERROR: Laravel JSON could not be parsed: " . json_last_error_msg() . "\n";
    echo "Raw laravel output:\n" . substr($laravelStr, 0, 500) . "\n";
}

if ($legacy !== null && $laravel !== null) {
    $matched = true;
    
    // Check Status Fields
    if (($legacy['status'] ?? 'null') !== ($laravel['status'] ?? 'null')) {
        echo "- Status field differs: Legacy({$legacy['status']}) vs Laravel({$laravel['status']})\n";
        $matched = false;
    }

    // Check Metrics length
    $legMetrics = call_user_func_array('array_merge', array_column($legacy['metrics'] ?? [], 'items'));
    $larMetrics = call_user_func_array('array_merge', array_column($laravel['metrics'] ?? [], 'items'));
    
    // Direct string comparison of the encoded arrays
    $encLegacy = json_encode($legacy);
    $encLaravel = json_encode($laravel);
    
    if ($encLegacy === $encLaravel) {
        echo "✅ SUCCESS: Exact 100% Structural and Data Parity Confirmed!\n";
    } else {
        echo "❌ DIFFERENCE DETECTED:\n";
        
        // Find specific differences
        foreach ($legacy as $key => $val) {
            if (!isset($laravel[$key])) {
                echo "  Missing Key in Laravel: '$key'\n";
            } elseif (is_array($val)) {
                $enc1 = json_encode($val);
                $enc2 = json_encode($laravel[$key]);
                if ($enc1 !== $enc2) {
                    echo "  Difference in Key '$key':\n";
                    // echo "    Legacy : $enc1\n";
                    // echo "    Laravel: $enc2\n";
                }
            } elseif ($val !== $laravel[$key]) {
                echo "  Value mismatch for '$key': Legacy($val) vs Laravel({$laravel[$key]})\n";
            }
        }
        foreach ($laravel as $key => $val) {
            if (!isset($legacy[$key])) {
                echo "  Extra Key in Laravel: '$key'\n";
            }
        }
    }
}
