<?php
require 'backend/config.php';
$pdo = new PDO('mysql:host='.DB_HOST.';dbname='.DB_NAME, DB_USER, DB_PASS);
$stmt = $pdo->query('DESCRIBE device_actions');
$cols = [];
foreach($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    if (isset($row['Field'])) $cols[] = $row['Field'];
}
file_put_contents('schema_out.txt', implode("\n", $cols));
