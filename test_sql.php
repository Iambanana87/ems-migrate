<?php
require __DIR__.'/laravel-api/vendor/autoload.php';
$app = require_once __DIR__.'/laravel-api/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    app(\App\Services\ReportService::class)->getEfficiencyReport('mold', '2026-02-26T07:00:00', '2026-02-27T07:00:00', 'machine_id', 'ASC');
    echo "SUCCESS\n";
} catch (\Exception $e) {
    echo $e->getMessage() . "\n";
}
