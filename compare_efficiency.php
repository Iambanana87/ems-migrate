<?php
$legacyStr = file_get_contents('c:\xampp\htdocs\ems\legacy_efficiency.json');
$laravelStr = file_get_contents('c:\xampp\htdocs\ems\laravel_efficiency.json');

$legacyStr = preg_replace('/^[\xef\xbb\xbf]+/', '', $legacyStr);
$laravelStr = preg_replace('/^[\xef\xbb\xbf]+/', '', $laravelStr);

$legacy = json_decode(trim($legacyStr), true);
$laravel = json_decode(trim($laravelStr), true);

$out = "====================================\n";
$out .= "LEGACY JSON OUTPUT:\n";
$out .= "====================================\n";
$out .= json_encode($legacy, JSON_PRETTY_PRINT) . "\n\n";

$out .= "====================================\n";
$out .= "LARAVEL JSON OUTPUT:\n";
$out .= "====================================\n";
$out .= json_encode($laravel, JSON_PRETTY_PRINT) . "\n\n";

$out .= "====================================\n";
$out .= "COMPARISON RESULTS:\n";
$out .= "====================================\n";

if ($legacy === null) $out .= "ERROR: Legacy JSON could not be parsed: " . json_last_error_msg() . "\n" . substr($legacyStr, 0, 500) . "\n";
if ($laravel === null) $out .= "ERROR: Laravel JSON could not be parsed: " . json_last_error_msg() . "\n" . substr($laravelStr, 0, 500) . "\n";

if ($legacy !== null && $laravel !== null) {
    if (($legacy['status'] ?? 'null') !== ($laravel['status'] ?? 'null')) {
        $out .= "- Status field differs: Legacy({$legacy['status']}) vs Laravel({$laravel['status']})\n";
    }

    $encLegacy = json_encode($legacy);
    $encLaravel = json_encode($laravel);
    
    if ($encLegacy === $encLaravel) {
        $out .= "✅ SUCCESS: Exact 100% Structural and Data Parity Confirmed!\n";
    } else {
        $out .= "❌ DIFFERENCE DETECTED:\n";
        
        foreach ($legacy as $key => $val) {
            if (!isset($laravel[$key])) {
                $out .= "  Missing Key in Laravel: '$key'\n";
            } elseif ($val !== $laravel[$key]) {
                $out .= "  Difference in Key '$key'\n";
            }
        }
        foreach ($laravel as $key => $val) {
            if (!isset($legacy[$key])) {
                $out .= "  Extra Key in Laravel: '$key'\n";
            }
        }
    }
}
file_put_contents('c:\xampp\htdocs\ems\eff_comparison_report.txt', $out);
echo "DONE";
