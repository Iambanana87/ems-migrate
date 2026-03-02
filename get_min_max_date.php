<?php
$pdo = new PDO('mysql:host=localhost;port=3306;dbname=production;charset=utf8mb4', 'device', 'BtyLX96qZ4nDL!0w');
$r = $pdo->query('SELECT MIN(datetime) as mi, MAX(datetime) as ma FROM mold');
print_r($r->fetchAll(PDO::FETCH_ASSOC));
