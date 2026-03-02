<?php
$pdo = new PDO('mysql:host=localhost;port=3306;dbname=production;charset=utf8mb4', 'device', 'BtyLX96qZ4nDL!0w');
foreach(['mold','tuft','blister','devices'] as $t) {
    try {
        $r = $pdo->query("SELECT COUNT(*) FROM $t");
        echo "$t: " . $r->fetchColumn() . "\n";
        
        if ($t !== 'devices') {
            $r2 = $pdo->query("SELECT MIN(datetime) as mi, MAX(datetime) as ma FROM $t");
            $dates = $r2->fetch(PDO::FETCH_ASSOC);
            echo "  Dates: " . ($dates['mi'] ?? 'NULL') . " to " . ($dates['ma'] ?? 'NULL') . "\n";
        }
    } catch (Exception $e) {
        echo "$t: ERR - " . $e->getMessage() . "\n";
    }
}
