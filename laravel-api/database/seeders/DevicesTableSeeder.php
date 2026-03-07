<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DevicesTableSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $devices = [];
        $deviceStatuses = [];
        $liveDeviceData = [];
        
        $moldCount = 80;
        $tuftCount = 70;
        $blisterCount = 60;
        
        $connectionStatuses = ['Connected', 'Connected', 'Connected', 'Connected', 'Disconnected'];
        $thresholdStatuses = ['Normal', 'Normal', 'Normal', 'Breached'];

        $generateDeviceData = function($index, $type) use (&$devices, &$deviceStatuses, &$liveDeviceData, $connectionStatuses, $thresholdStatuses) {
            $prefix = strtoupper(substr($type, 0, 1));
            $deviceId = $prefix . str_pad($index, 3, '0', STR_PAD_LEFT);
            
            $connStatus = $connectionStatuses[array_rand($connectionStatuses)];
            $threshStatus = ($connStatus === 'Connected') ? $thresholdStatuses[array_rand($thresholdStatuses)] : 'Normal';
            
            // 1. Devices Table - Ensure all keys are present for batch insert
            $deviceRow = [
                'device_id' => $deviceId,
                'display_type' => $type,
                'product' => 'Product ' . ucfirst($type) . ' ' . $index,
                'process' => 'Line ' . rand(1, 5),
                'lower_limit' => rand(10, 20),
                'target_limit' => rand(25, 30),
                'upper_limit' => rand(35, 50),
                'efficiency_lower_limit' => 60,
                'flex' => rand(0, 1) === 1 ? '1' : '0',
                'client' => 'Client ' . chr(rand(65, 70)),
                'frequency' => 60,
                'unit' => ($type === 'mold' ? 's' : ($type === 'tuft' ? 'pcs' : 'cycles')),
                'cavities' => null,
                'hole_per_brush' => null,
                'brushes_per_cycle' => null,
                'mold_type' => null,
            ];

            if ($type === 'mold') {
                $deviceRow['cavities'] = rand(4, 32);
            } elseif ($type === 'tuft') {
                $deviceRow['hole_per_brush'] = rand(24, 60);
            } elseif ($type === 'blister') {
                $deviceRow['brushes_per_cycle'] = rand(10, 20);
            }

            $devices[] = $deviceRow;

            // 2. Device Status Table
            $deviceStatuses[] = [
                'device_id' => $deviceId,
                'connection_status' => $connStatus,
                'threshold_status' => $threshStatus,
                'last_heartbeat' => $connStatus === 'Connected' ? Carbon::now()->toDateTimeString() : Carbon::now()->subMinutes(rand(10, 60))->toDateTimeString(),
            ];

            // 3. Live Device Data Table (JSON)
            $liveMetrics = [
                'datetime' => Carbon::now()->toDateTimeString(),
                'efficiency' => rand(40, 99) . '.' . rand(0, 9),
            ];

            if ($type === 'mold') {
                $liveMetrics['cycle_time'] = rand(8, 55) . '.' . rand(0, 9);
                $liveMetrics['cavities'] = rand(4, 32);
            } elseif ($type === 'tuft') {
                $liveMetrics['output'] = rand(5, 60);
                $liveMetrics['rpm'] = rand(100, 300);
            } elseif ($type === 'blister') {
                $liveMetrics['cyclecount'] = rand(5, 60);
                $liveMetrics['BrushesperCycle'] = rand(10, 20);
                $liveMetrics['output'] = rand(50, 200);
            }

            $liveDeviceData[] = [
                'device_id' => $deviceId,
                'display_type' => $type,
                'live_data' => json_encode($liveMetrics),
                'last_updated' => Carbon::now()->toDateTimeString(),
            ];
        };

        // Generate data in memory
        for ($i = 1; $i <= $moldCount; $i++) $generateDeviceData($i, 'mold');
        for ($i = 1; $i <= $tuftCount; $i++) $generateDeviceData($i, 'tuft');
        for ($i = 1; $i <= $blisterCount; $i++) $generateDeviceData($i, 'blister');

        // Execute DB inserts
        DB::transaction(function() use ($devices, $deviceStatuses, $liveDeviceData) {
            DB::table('device_status')->delete();
            DB::table('live_device_data')->delete();
            DB::table('devices')->delete();

            foreach (array_chunk($devices, 50) as $chunk) DB::table('devices')->insert($chunk);
            foreach (array_chunk($deviceStatuses, 50) as $chunk) DB::table('device_status')->insert($chunk);
            foreach (array_chunk($liveDeviceData, 50) as $chunk) DB::table('live_device_data')->insert($chunk);
        });
    }
}
