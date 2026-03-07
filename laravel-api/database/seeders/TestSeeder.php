<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class TestSeeder extends Seeder
{
    public function run()
    {
        DB::table('device_status')->delete();
        DB::table('live_device_data')->delete();
        DB::table('devices')->delete();

        DB::table('devices')->insert([
            'device_id' => 'M001',
            'display_type' => 'mold',
            'product' => 'Test Product',
            'process' => 'Line 1',
            'lower_limit' => 10,
            'target_limit' => 20,
            'upper_limit' => 30,
            'efficiency_lower_limit' => 60,
            'flex' => '0',
            'client' => 'Test Client',
            'frequency' => 60,
            'unit' => 's'
        ]);

        DB::table('device_status')->insert([
            'device_id' => 'M001',
            'connection_status' => 'Connected',
            'threshold_status' => 'Normal',
            'last_heartbeat' => now()
        ]);

        DB::table('live_device_data')->insert([
            'device_id' => 'M001',
            'display_type' => 'mold',
            'live_data' => json_encode(['efficiency' => '85.5', 'cycle_time' => '12.4']),
            'last_updated' => now()
        ]);
        
        echo "Successfully inserted one complete device set." . PHP_EOL;
    }
}
