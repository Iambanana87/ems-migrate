<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ProductionReportingSeeder extends Seeder
{
    public function run()
    {
        // 1. Clean existing mocks
        DB::statement("DELETE FROM mold WHERE device_id LIKE 'MOLD-%'");
        DB::statement("DELETE FROM tuft WHERE device_id LIKE 'TUFT-%'");
        DB::statement("DELETE FROM blister WHERE device_id LIKE 'BLIST-%'");
        DB::statement("DELETE FROM device_status WHERE device_id LIKE 'MOLD-%' OR device_id LIKE 'TUFT-%' OR device_id LIKE 'BLIST-%'");
        DB::statement("DELETE FROM devices WHERE device_id LIKE 'MOLD-%' OR device_id LIKE 'TUFT-%' OR device_id LIKE 'BLIST-%'");

        $now = now();

        // 2. Insert Devices
        // Family A (Mold x 2, Tuft x 1, Blister x 1)
        // Family B (Mold x 1, Tuft x 1, Blister x 1)
        // Family C (Tuft x 1, Blister x 1) -> No Mold to test UNION ALL absence
        $devices = [
            // MOLD
            ['device_id' => 'MOLD-01', 'display_type' => 'mold', 'process' => 'Single', 'product' => 'Family A', 'capacity' => 100, 'efficiency_lower_limit' => 0],
            ['device_id' => 'MOLD-02', 'display_type' => 'mold', 'process' => 'Single', 'product' => 'Family A', 'capacity' => 100, 'efficiency_lower_limit' => 0],
            ['device_id' => 'MOLD-03', 'display_type' => 'mold', 'process' => 'Single', 'product' => 'Family B', 'capacity' => 100, 'efficiency_lower_limit' => 0],

            // TUFT
            ['device_id' => 'TUFT-01', 'display_type' => 'tuft', 'process' => 'Single', 'product' => 'Family A', 'capacity' => 100, 'efficiency_lower_limit' => 0],
            ['device_id' => 'TUFT-02', 'display_type' => 'tuft', 'process' => 'Single', 'product' => 'Family B', 'capacity' => 100, 'efficiency_lower_limit' => 0],
            ['device_id' => 'TUFT-03', 'display_type' => 'tuft', 'process' => 'Single', 'product' => 'Family C', 'capacity' => 100, 'efficiency_lower_limit' => 0],

            // BLISTER
            ['device_id' => 'BLIST-01', 'display_type' => 'blister', 'process' => 'Single', 'product' => 'Family A', 'capacity' => 100, 'efficiency_lower_limit' => 0],
            ['device_id' => 'BLIST-02', 'display_type' => 'blister', 'process' => 'Single', 'product' => 'Family B', 'capacity' => 100, 'efficiency_lower_limit' => 0],
            ['device_id' => 'BLIST-03', 'display_type' => 'blister', 'process' => 'Single', 'product' => 'Family C', 'capacity' => 100, 'efficiency_lower_limit' => 0],
        ];

        foreach ($devices as $d) {
            DB::table('devices')->insert($d);
        }

        // 3. Insert Device Status (Idle Breakdown Validation)
        $statuses = [
            ['device_id' => 'MOLD-01',  'connection_status' => 'DISCONNECTED', 'threshold_status' => 'NORMAL'],   // Breakdown
            ['device_id' => 'MOLD-02',  'connection_status' => 'CONNECTED',    'threshold_status' => 'NORMAL'],   // Running
            ['device_id' => 'MOLD-03',  'connection_status' => 'CONNECTED',    'threshold_status' => 'WARNING'],  // Warning (mapped to BREACHED in logic)
            
            ['device_id' => 'TUFT-01',  'connection_status' => 'CONNECTED',    'threshold_status' => 'NORMAL'],
            ['device_id' => 'TUFT-02',  'connection_status' => 'DISCONNECTED', 'threshold_status' => 'NORMAL'],
            ['device_id' => 'TUFT-03',  'connection_status' => 'CONNECTED',    'threshold_status' => 'WARNING'],

            ['device_id' => 'BLIST-01', 'connection_status' => 'DISCONNECTED', 'threshold_status' => 'NORMAL'],
            ['device_id' => 'BLIST-02', 'connection_status' => 'CONNECTED',    'threshold_status' => 'NORMAL'],
            ['device_id' => 'BLIST-03', 'connection_status' => 'CONNECTED',    'threshold_status' => 'NORMAL'],
        ];

        foreach ($statuses as $s) {
            DB::table('device_status')->insert($s);
        }

        // 4. Insert Output Data
        // Target Window: 2026-02-26 07:00:00 to 2026-02-27 07:00:00
        $dayTime   = '2026-02-26 10:00:00'; // Inside day shift 07:00 - 19:00
        $nightTime = '2026-02-26 22:00:00'; // Inside night shift 19:00 - 07:00
        $outTime   = '2026-02-25 10:00:00'; // Outside 24h window entirely

        // --- MOLD ---
        $molds = [
            // Out of bounds
            ['uuid' => uniqid(), 'device_id' => 'MOLD-01', 'datetime' => $outTime,   'cavities' => 9999, 'cycle_time' => 25],
            
            // Family A (Day Out target: 1200) -> MOLD-01 + MOLD-02
            ['uuid' => uniqid(), 'device_id' => 'MOLD-01', 'datetime' => $dayTime,   'cavities' => 600,  'cycle_time' => 25],
            ['uuid' => uniqid(), 'device_id' => 'MOLD-02', 'datetime' => $dayTime,   'cavities' => 600,  'cycle_time' => 25],
            // Night target: 600
            ['uuid' => uniqid(), 'device_id' => 'MOLD-01', 'datetime' => $nightTime, 'cavities' => 300,  'cycle_time' => 25],
            ['uuid' => uniqid(), 'device_id' => 'MOLD-02', 'datetime' => $nightTime, 'cavities' => 300,  'cycle_time' => 25],
            // Invalid Cycle Time (<=20 shouldn't count towards day_out natively)
            ['uuid' => uniqid(), 'device_id' => 'MOLD-01', 'datetime' => $dayTime,   'cavities' => 5000, 'cycle_time' => 15],

            // Family B (Day target: 800) -> MOLD-03
            ['uuid' => uniqid(), 'device_id' => 'MOLD-03', 'datetime' => $dayTime,   'cavities' => 800,  'cycle_time' => 25],
        ];
        DB::table('mold')->insert($molds);

        // --- TUFT ---
        $tufts = [
            // Family A (Day target: 1000)
            ['uuid' => uniqid(), 'device_id' => 'TUFT-01', 'datetime' => $dayTime,   'output' => 1000],
            // Night Target: 500
            ['uuid' => uniqid(), 'device_id' => 'TUFT-01', 'datetime' => $nightTime, 'output' => 500],

            // Family B (Day target: 800)
            ['uuid' => uniqid(), 'device_id' => 'TUFT-02', 'datetime' => $dayTime,   'output' => 800],

            // Family C (Day target: 1120) - 93.33% Efficiency Validation
            ['uuid' => uniqid(), 'device_id' => 'TUFT-03', 'datetime' => $dayTime,   'output' => 1120],
        ];
        DB::table('tuft')->insert($tufts);

        // --- BLISTER ---
        $blisters = [
            // Family A (Day target: 950)
            ['uuid' => uniqid('b_'), 'device_id' => 'BLIST-01', 'datetime' => $dayTime,   'output' => "950", 'cyclecount' => 1, 'product' => 'Family A'],
            // Night Target: 400
            ['uuid' => uniqid('b_'), 'device_id' => 'BLIST-01', 'datetime' => $nightTime, 'output' => "400", 'cyclecount' => 1, 'product' => 'Family A'],

            // Family B (Day target: 700)
            ['uuid' => uniqid('b_'), 'device_id' => 'BLIST-02', 'datetime' => $dayTime,   'output' => "700", 'cyclecount' => 1, 'product' => 'Family B'],

            // Family C (Day target: 1120) - exact copy to TUFT to yield 0 loss
            ['uuid' => uniqid('b_'), 'device_id' => 'BLIST-03', 'datetime' => $dayTime,   'output' => "1120", 'cyclecount' => 1, 'product' => 'Family C'],
        ];
        DB::table('blister')->insert($blisters);
    }
}
