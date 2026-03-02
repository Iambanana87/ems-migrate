<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Config;

function runQuery($label, $sql) {
    echo "--- $label ---\n";
    try {
        $results = DB::select($sql);
        print_r($results);
    } catch (\Exception $e) {
        echo "ERROR: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "====================================\n";
echo "LARAVEL DATABASE DIAGNOSTICS\n";
echo "====================================\n\n";

// STEP 1: SELECT DATABASE()
runQuery("STEP 1: Current Database", "SELECT DATABASE() as current_db");

// STEP 2: SHOW DATABASES
runQuery("STEP 2: Available Databases", "SHOW DATABASES");

// STEP 3: SHOW TABLES IN production
echo "--- STEP 3: Tables in 'production' ---\n";
try {
    // Explicitly use production if not connected to it
    $tables = DB::select("SHOW TABLES FROM production");
    print_r($tables);
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// STEP 4: SELECT COUNT(*) FROM mold
runQuery("STEP 4: Mold Table Count", "SELECT COUNT(*) as total FROM mold");

// STEP 5: Connection details
echo "--- STEP 5: Connection Details ---\n";
$connName = DB::getDefaultConnection();
$config = Config::get("database.connections.$connName");

echo "DB Connection Name: $connName\n";
echo "DB Host: " . ($config['host'] ?? 'N/A') . "\n";
echo "DB Port: " . ($config['port'] ?? 'N/A') . "\n";
echo "DB Database: " . ($config['database'] ?? 'N/A') . "\n";
echo "DB Username: " . ($config['username'] ?? 'N/A') . "\n";

try {
    $pdo = DB::connection()->getPdo();
    $dsn = $pdo->getAttribute(\PDO::ATTR_CONNECTION_STATUS);
    echo "PDO Connection Status (DSN-like info): $dsn\n";
    
    // Determine TCP vs Socket
    if (strpos($dsn, 'via TCP/IP') !== false) {
        echo "Connection Type: TCP/IP\n";
    } elseif (strpos($dsn, 'via Unix socket') !== false || strpos($dsn, '.sock') !== false || strpos($dsn, 'localhost') !== false) {
        // On Windows, 'localhost' often implies a named pipe or specialized local connection if not explicitly TCP
        echo "Connection Type: Localhost (Likely Socket/Named Pipe or loopback)\n";
    } else {
        echo "Connection Type: Could not explicitly determine from status string\n";
    }
} catch (\Exception $e) {
    echo "PDO ERROR: " . $e->getMessage() . "\n";
}
