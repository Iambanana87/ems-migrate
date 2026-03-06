<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

use App\Services\JwtService;

$secret = env('EMS_JWT_SECRET');
$token = env('PARITY_AUTH_TOKEN');

echo "Secret: $secret\n";
echo "Token: $token\n";

$jwtService = new JwtService($secret);

$payload = [
    'sub' => '0',
    'name' => 'ParityScan',
    'username' => 'parity_scan',
    'role' => 'admin',
    'exp' => time() + 3600000,
    'iat' => time()
];

$newToken = $jwtService->encode($payload);
echo "New Valid Token:\n$newToken\n";

try {
    $claims = $jwtService->decode($newToken);
    echo "Decoded Successfully:\n";
    print_r($claims);
} catch (\Exception $e) {
    echo "Decode Failed: " . $e->getMessage() . "\n";
}
