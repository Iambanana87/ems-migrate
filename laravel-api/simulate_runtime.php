<?php
require __DIR__ . '/vendor/autoload.php';
$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

function hitApi($kernel, $method, $query, $body = []) {
    $server = ['CONTENT_TYPE' => 'application/json', 'HTTP_ACCEPT' => 'application/json'];
    // For GET add query to uri
    $uri = '/api/gateway?' . http_build_query($query);
    $req = Request::create($uri, $method, $body, [], [], $server, json_encode($body));
    $start = microtime(true);
    $res = $kernel->handle($req);
    $time = microtime(true) - $start;
    return ['status' => $res->getStatusCode(), 'time' => $time, 'body' => json_decode($res->getContent(), true)];
}

echo "=== REAL BACKEND RUNTIME TEST ===\n";

// 1. GET DEVICES
$res1 = hitApi($kernel, 'GET', ['c'=>'Device', 'm'=>'getDevices']);
echo "1. GET Devices: HTTP {$res1['status']} in " . round($res1['time']*1000, 2) . "ms\n";
$structCheck = isset($res1['body']['status']) && isset($res1['body']['devices']['mold']);
echo "   Structure valid: " . ($structCheck ? "YES" : "NO") . "\n";

// 2. CREATE DEVICE (Success)
$mockDeviceId = 'TEST-SIM-' . uniqid();
$createPayload = [
    'c'=>'Device', 'm'=>'store', 
    'device_id' => $mockDeviceId,
    'display_type' => 'mold',
    'target_limit' => 20.5,
    'cavities' => 4
];
$res2 = hitApi($kernel, 'POST', [], $createPayload);
echo "2. POST Create Device: HTTP {$res2['status']} - " . ($res2['body']['status'] ?? '') . "\n";
if ($res2['status'] !== 200) {
    echo "   Error: " . json_encode($res2['body']) . "\n";
}

// 3. CREATE DEVICE (Missing Field)
$badPayload = [
    'c'=>'Device', 'm'=>'store', 
    'device_id' => $mockDeviceId.'-bad',
    'display_type' => 'mold'
    // missing target_limit and cavities
];
$res3 = hitApi($kernel, 'POST', [], $badPayload);
echo "3. POST Create Invalid: HTTP {$res3['status']} - " . ($res3['body']['message'] ?? 'No message') . "\n";

// 4. UPDATE DEVICE
$updatePayload = [
    'c'=>'Device', 'm'=>'update',
    'device_id' => $mockDeviceId,
    'target_limit' => 25.0,
    'reason' => 'Runtime simulation test update'
];
$res4 = hitApi($kernel, 'POST', [], $updatePayload);
echo "4. POST Update Device: HTTP {$res4['status']} - " . ($res4['body']['status'] ?? '') . "\n";
$checkDb = DB::table('devices')->where('device_id', $mockDeviceId)->first();
echo "   DB updated limit: " . ($checkDb->target_limit ?? 'N/A') . "\n";

// 5. GET DEVICES LATENCY WITH 200+ MACHINES
echo "\n--- Injecting 200 Dummy Devices ---\n";
$inserts = [];
for($i=0; $i<200; $i++) {
    $inserts[] = [
        'device_id' => "SIM-LARGE-$i",
        'display_type' => 'tuft',
        'target_limit' => 10,
        'hole_per_brush' => 40,
        'efficiency_lower_limit' => 0
    ];
}
DB::table('devices')->insert($inserts);

$res5 = hitApi($kernel, 'GET', ['c'=>'Device', 'm'=>'getDevices']);
echo "5. GET 200+ Devices: HTTP {$res5['status']} in " . round($res5['time']*1000, 2) . "ms\n";
$totalInResponse = count($res5['body']['devices']['mold'] ?? []) + count($res5['body']['devices']['tuft'] ?? []) + count($res5['body']['devices']['blister'] ?? []);
echo "   Total grouped in payload: $totalInResponse\n";

// Cleanup 200 dummies + test device
DB::table('devices')->where('device_id', 'like', 'SIM-LARGE-%')->delete();

// 6. DELETE DEVICE
$delPayload = [
    'c'=>'Device', 'm'=>'destroy',
    'device_id' => $mockDeviceId
];
$res6 = hitApi($kernel, 'POST', [], $delPayload);
echo "6. POST Delete Device: HTTP {$res6['status']} - " . ($res6['body']['status'] ?? '') . "\n";
$checkDeleted = DB::table('devices')->where('device_id', $mockDeviceId)->count();
echo "   Remains in DB: $checkDeleted\n";

echo "=== TEST END ===\n";
