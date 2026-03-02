<?php
$hosts = ['127.0.0.1', 'localhost', '::1'];
$users = [
    ['root', ''],
    ['device', 'BtyLX96qZ4nDL!0w']
];

foreach($hosts as $h) {
    foreach($users as $u) {
        try {
            $pdo = new PDO("mysql:host=$h;dbname=production", $u[0], $u[1]);
            echo "SUCCESS: $u[0] @ $h\n";
        } catch (Exception $e) {
            echo "FAILED: $u[0] @ $h - " . $e->getMessage() . "\n";
        }
    }
}
