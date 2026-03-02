<?php
$h = '127.0.0.1';
$u = 'it01';
$p = 'leUHgG9HBWEwSW!G';

try {
    $pdo = new PDO("mysql:host=$h;dbname=production", $u, $p);
    echo "SUCCESS: $u @ $h (production db)\n";
} catch (Exception $e) {
    echo "FAILED: production - " . $e->getMessage() . "\n";
}

try {
    $pdo = new PDO("mysql:host=$h;dbname=develop", $u, $p);
    echo "SUCCESS: $u @ $h (develop db)\n";
} catch (Exception $e) {
    echo "FAILED: develop - " . $e->getMessage() . "\n";
}
