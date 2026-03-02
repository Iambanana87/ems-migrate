<?php
$pdo = new PDO('mysql:host=localhost;port=3306;dbname=production;charset=utf8mb4', 'device', 'BtyLX96qZ4nDL!0w');
$stmt = $pdo->query('DESCRIBE devices');
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $c) {
    if ($c['Null'] == 'NO' && $c['Default'] === null && $c['Extra'] !== 'auto_increment') {
        echo $c['Field']."\n";
    }
}
