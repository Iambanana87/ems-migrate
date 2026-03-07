<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use Illuminate\Support\Facades\DB;

DB::statement("SET SQL_MODE='NO_AUTO_VALUE_ON_ZERO'");
try {
    DB::statement("INSERT IGNORE INTO users (id, username) VALUES (0, 'system_dummy')");
    echo "Inserted user 0 successfully.\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
