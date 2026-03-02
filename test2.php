<?php
require __DIR__.'/laravel-api/vendor/autoload.php';
$app = require_once __DIR__.'/laravel-api/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

try {
    $sql = "
            SELECT 
                d.device_id             AS machine_id,
                d.capacity              AS capacity_per_hour
            FROM mold t
            JOIN devices d ON BINARY t.mold_id  = BINARY d.device_id
            WHERE d.display_type = 'mold'
              AND t.`datetime`   >= '2026-02-26 07:00:00'
              AND t.`datetime`   <  '2026-02-27 07:00:00'
              AND d.process IN ('Single','1st')
              AND t.cycle_time > 20
            GROUP BY d.device_id, d.capacity
            ORDER BY machine_id ASC
        ";
    $res = \Illuminate\Support\Facades\DB::select($sql);
    echo "SUCCESS, rows: " . count($res) . "\n";
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
}
