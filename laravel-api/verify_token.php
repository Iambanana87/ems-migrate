<?php

require __DIR__ . '/vendor/autoload.php';

$app = require_once __DIR__ . '/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$tokenFromEnv = config('parity.auth_token');
$secret = config('ems.jwt_secret');

echo "Secret: " . $secret . "\n";
echo "Token from env: " . $tokenFromEnv . "\n";

$payload = [
    "sub" => "0",
    "name" => "ParityScan",
    "username" => "parity_scan",
    "role" => "admin",
    "exp" => 1804232008,
    "iat" => 1741416042
];

$jwtService = app(\App\Services\JwtService::class);
$generatedToken = $jwtService->encode($payload);

echo "Generated Token: " . $generatedToken . "\n";

if ($tokenFromEnv === $generatedToken) {
    echo "Tokens match exactly.\n";
} else {
    echo "TOKENS DO NOT MATCH.\n";
}

try {
    $claims = $jwtService->decode($tokenFromEnv);
    echo "Claims from env token: " . json_encode($claims) . "\n";
} catch (\Exception $e) {
    echo "Decode error: " . $e->getMessage() . "\n";
}
