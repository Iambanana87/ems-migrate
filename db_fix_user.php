<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    $pdo->exec("CREATE USER IF NOT EXISTS 'device'@'127.0.0.1' IDENTIFIED BY 'BtyLX96qZ4nDL!0w';");
    $pdo->exec("CREATE USER IF NOT EXISTS 'device'@'localhost' IDENTIFIED BY 'BtyLX96qZ4nDL!0w';");
    $pdo->exec("GRANT ALL PRIVILEGES ON *.* TO 'device'@'127.0.0.1';");
    $pdo->exec("GRANT ALL PRIVILEGES ON *.* TO 'device'@'localhost';");
    $pdo->exec("FLUSH PRIVILEGES;");
    echo "Privileges granted successfully.\n";
} catch (\Exception $e) {
    echo "Failed: " . $e->getMessage() . "\n";
}
