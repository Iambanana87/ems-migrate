<?php
try {
    $pdo = new PDO('mysql:host=localhost;dbname=production;port=3306', 'device', 'BtyLX96qZ4nDL!0w');
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "Tables in production:\n";
    print_r($tables);
    $stmt = $pdo->query("SELECT COUNT(*) FROM mold");
    echo "Mold count: " . $stmt->fetchColumn() . "\n";
} catch (Exception $e) {
    echo "FAIL: " . $e->getMessage() . "\n";
}
