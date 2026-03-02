<?php
$leg = json_decode(file_get_contents('c:/xampp/htdocs/ems/legacy_err_out.json'), true);
$lar = json_decode(file_get_contents('c:/xampp/htdocs/ems/laravel_err_out.json'), true);

function diff($a, $b, $path="") {
    $keys = array_unique(array_merge(array_keys($a ?: []), array_keys($b ?: [])));
    foreach ($keys as $k) {
        $p = $path ? "$path.$k" : $k;
        if (!isset($a[$k]) && isset($b[$k])) {
            echo "Missing in Legacy: $p (Laravel has " . json_encode($b[$k]) . ")\n";
        } elseif (isset($a[$k]) && !isset($b[$k])) {
            echo "Missing in Laravel: $p (Legacy has " . json_encode($a[$k]) . ")\n";
        } elseif (is_array($a[$k]) && is_array($b[$k])) {
            diff($a[$k], $b[$k], $p);
        } elseif ($a[$k] !== $b[$k]) {
            echo "Value mismatch at $p:\n  Legacy:  " . json_encode($a[$k]) . "\n  Laravel: " . json_encode($b[$k]) . "\n";
            // if strings, maybe numerical cast discrepancy?
            if ($a[$k] == $b[$k] && gettype($a[$k]) !== gettype($b[$k])) {
                echo "    -> Type mismatch only. Legacy: " . gettype($a[$k]) . ", Laravel: " . gettype($b[$k]) . "\n";
            }
        }
    }
}

diff($leg, $lar);
