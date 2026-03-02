<?php
try {
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=production;charset=utf8mb4', 'device', 'BtyLX96qZ4nDL!0w');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query('DESCRIBE mold');
    print_r($stmt->fetchAll(PDO::FETCH_ASSOC));
} catch (Exception $e) {
    echo $e->getMessage();
}
