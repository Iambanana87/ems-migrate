<?php
$l = json_decode(file_get_contents('c:/xampp/htdocs/ems/legacy_err_out.json'), true);
$r = json_decode(file_get_contents('c:/xampp/htdocs/ems/laravel_err_out.json'), true);

if ($l === $r) {
    echo "ARRAYS ARE 100% IDENTICAL IN PHP.\n";
} else {
    echo "ARRAYS DIFFER.\n";
    $diff = [];
    foreach ($l as $k => $v) {
        if (!isset($r[$k])) {
            echo "Key missing in R: $k\n";
        } elseif ($v !== $r[$k]) {
            echo "Mismatch at $k\n";
            file_put_contents("c:/xampp/htdocs/ems/debug_$k.txt", "L:\n" . print_r($v, true) . "\nR:\n" . print_r($r[$k], true));
        }
    }
}
