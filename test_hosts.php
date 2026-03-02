<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;dbname=production;port=3306', 'root', '');
    echo "127.0.0.1 root works\n";
} catch (Exception $e) { echo "127.0.0.1 root fails: " . $e->getMessage() . "\n"; }

try {
    $pdo = new PDO('mysql:host=localhost;dbname=production;port=3306', 'root', '');
    echo "localhost root works\n";
} catch (Exception $e) { echo "localhost root fails: " . $e->getMessage() . "\n"; }
