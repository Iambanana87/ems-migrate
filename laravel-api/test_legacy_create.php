<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$token = config('parity.auth_token');
$response = \Illuminate\Support\Facades\Http::withToken($token)
    ->asForm()
    ->post('http://127.0.0.1:8081/api.php', [
        'action' => 'create_device_action',
        'device_id' => 'MOLD-01',
        'title' => 'Test Parity Action'
    ]);

echo "Status: " . $response->status() . "\n";
echo "Body:\n" . $response->body() . "\n";
