<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

try {
    $controller = app(\App\Http\Controllers\Api\ReportController::class);
    echo "ReportController instantiated successfully.\n";
} catch (\Throwable $e) {
    echo "FATAL ERROR:\n";
    echo $e->getMessage() . "\n";
    echo "In " . $e->getFile() . ":" . $e->getLine() . "\n";
}
