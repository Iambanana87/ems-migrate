<?php
$pdo = new PDO('mysql:host=localhost;dbname=production', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
$stmt = $pdo->query("DESCRIBE audit_trail");
$cols = $stmt->fetchAll(PDO::FETCH_COLUMN);
print_r($cols);
