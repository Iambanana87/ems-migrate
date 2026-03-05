<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

$jwt = app(App\Services\JwtService::class);
$token = $jwt->encode([
    'user_id' => 1,
    'username' => 'admin',
    'role' => 'admin',
    'exp' => time() + 31536000
]);

echo "PARITY_AUTH_TOKEN=" . $token . "\n";
