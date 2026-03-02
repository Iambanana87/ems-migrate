<?php
try {
    $pdo = new PDO('mysql:host=localhost;port=3306;dbname=production;charset=utf8mb4', 'device', 'BtyLX96qZ4nDL!0w');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $stmt = $pdo->query('DESCRIBE mold');
    file_put_contents('c:/xampp/htdocs/ems/mold_schema.json', json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_PRETTY_PRINT));
} catch (Exception $e) {
    echo $e->getMessage();
}
