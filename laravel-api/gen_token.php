<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$payload = [
    'sub' => '0',
    'name' => 'ParityScan',
    'username' => 'parity_scan',
    'role' => 'admin',
    'exp' => time() + 3600000,
    'iat' => time()
];

echo (new App\Services\JwtService(env('EMS_JWT_SECRET')))->encode($payload);
