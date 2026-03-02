<?php
$tests = [
    ['root', ''],
    ['root', 'root'],
    ['device', 'BtyLX96qZ4nDL!0w'],
    ['it01', 'leUHgG9HBWEwSW!G']
];

foreach ($tests as $t) {
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;dbname=production;port=3306', $t[0], $t[1]);
        echo "SUCCESS: {$t[0]}\n";
    } catch(PDOException $e) {
        echo "FAIL 127.0.0.1: {$t[0]} - " . $e->getMessage() . "\n";
    }
}
