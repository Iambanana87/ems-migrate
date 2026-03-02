<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();
try {
    $res = \Illuminate\Support\Facades\DB::select('SELECT COUNT(*) as c FROM mold');
    echo 'SUCCESS - Mold count: ' . $res[0]->c . "\n";
} catch (\Exception $e) {
    echo 'FAILED: ' . $e->getMessage() . "\n";
}
