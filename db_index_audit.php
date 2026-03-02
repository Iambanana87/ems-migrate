<?php
$pdo = new PDO('mysql:host=127.0.0.1;dbname=production', 'root', '');
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// 1. Indexes Audit
echo "=== DATABASE INDEXES ===\n";
$tables = ['devices', 'device_actions', 'device_action_plans', 'mold', 'tuft', 'blister', 'injection', 'end_rounding', 'users'];
foreach ($tables as $t) {
    try {
        $stmt = $pdo->query("SHOW INDEX FROM `$t`");
        $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if ($indexes) {
            echo "Table: $t\n";
            foreach ($indexes as $idx) {
                echo " - " . $idx['Key_name'] . " (" . $idx['Column_name'] . ")\n";
            }
        } else {
            echo "Table: $t - NO INDEXES DEFINED\n";
        }
    } catch (Exception $e) {
        // Table mighty not exist, ignore
    }
}

// 2. Foreign Keys Audit
echo "\n=== FOREIGN KEYS ===\n";
$stmt = $pdo->query("
    SELECT TABLE_NAME, COLUMN_NAME, CONSTRAINT_NAME, REFERENCED_TABLE_NAME, REFERENCED_COLUMN_NAME
    FROM INFORMATION_SCHEMA.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_SCHEMA = 'production'
");
$fks = $stmt->fetchAll(PDO::FETCH_ASSOC);
if (empty($fks)) {
    echo "NO FOREIGN KEYS DEFINED IN SCHEMA.\n";
} else {
    foreach ($fks as $fk) {
        echo "Table: {$fk['TABLE_NAME']}.{$fk['COLUMN_NAME']} -> {$fk['REFERENCED_TABLE_NAME']}.{$fk['REFERENCED_COLUMN_NAME']} (Constraint: {$fk['CONSTRAINT_NAME']})\n";
    }
}
