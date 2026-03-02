<?php
$l = json_decode(file_get_contents('c:/xampp/htdocs/ems/legacy_err_out.json'), true);
$r = json_decode(file_get_contents('c:/xampp/htdocs/ems/laravel_err_out.json'), true);

function diff($a, $b, $path="") {
    $keys = array_unique(array_merge(array_keys($a ?: []), array_keys($b ?: [])));
    foreach ($keys as $k) {
        $p = $path . ($path?"->":"") . $k;
        if (!array_key_exists($k, $a)) {
            echo "Missing in Legacy: $p\n";
        } elseif (!array_key_exists($k, $b)) {
            echo "Missing in Laravel: $p\n";
        } elseif (is_array($a[$k]) && is_array($b[$k])) {
            diff($a[$k], $b[$k], $p);
        } elseif ($a[$k] !== $b[$k]) {
            echo "Mismatch at $p: Legacy=".json_encode($a[$k]).", Laravel=".json_encode($b[$k])."\n";
            echo "  Types: Legacy=".gettype($a[$k]).", Laravel=".gettype($b[$k])."\n";
        }
    }
}
diff($l, $r);
