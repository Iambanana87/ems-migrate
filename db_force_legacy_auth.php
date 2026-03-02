<?php
try {
    $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
    $pdo->exec("ALTER USER 'device'@'127.0.0.1' IDENTIFIED VIA mysql_native_password USING PASSWORD('BtyLX96qZ4nDL!0w');");
    $pdo->exec("ALTER USER 'device'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD('BtyLX96qZ4nDL!0w');");
    $pdo->exec("FLUSH PRIVILEGES;");
    echo "Password altered.\n";
} catch (\Exception $e) {
    try {
        $pdo = new PDO('mysql:host=127.0.0.1;port=3306', 'root', '');
        $pdo->exec("ALTER USER 'device'@'127.0.0.1' IDENTIFIED WITH mysql_native_password BY 'BtyLX96qZ4nDL!0w';");
        $pdo->exec("ALTER USER 'device'@'localhost' IDENTIFIED WITH mysql_native_password BY 'BtyLX96qZ4nDL!0w';");
        $pdo->exec("FLUSH PRIVILEGES;");
        echo "Password altered (WITH).\n";
    } catch (\Exception $e2) {
        echo "Failed: " . $e2->getMessage() . "\n";
    }
}
