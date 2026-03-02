<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

$host = config('database.connections.production.host');
$db   = config('database.connections.production.database');
$user = config('database.connections.production.username');
$pass = config('database.connections.production.password');

echo "LARAVEL CONFIG:\n";
echo "Host: '$host'\n";
echo "DB:   '$db'\n";
echo "User: '$user'\n";
echo "Pass: '$pass'\n\n";

try {
    echo "TRYING RAW PDO...\n";
    $pdo = new \PDO("mysql:host=$host;dbname=$db;port=3306", $user, $pass);
    echo "RAW PDO SUCCESS\n\n";
} catch (\Exception $e) {
    echo "RAW PDO FAIL: " . $e->getMessage() . "\n\n";
}

try {
    echo "TRYING LARAVEL DB::select...\n";
    $res = \Illuminate\Support\Facades\DB::select('SELECT COUNT(*) as c FROM mold');
    echo "LARAVEL DB SUCCESS: " . $res[0]->c . "\n";
} catch (\Exception $e) {
    echo "LARAVEL DB FAIL: " . $e->getMessage() . "\n";
}
