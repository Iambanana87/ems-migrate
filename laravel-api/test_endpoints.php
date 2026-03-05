<?php
require 'vendor/autoload.php';
$app = require_once 'bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

$endpoints = config('parity.endpoints');
foreach ($endpoints as $name => $config) {
    if (!isset($config['laravel_params'])) continue;
    
    echo "Testing {$name}... ";
    $url = config('parity.laravel_base') . '?' . http_build_query($config['laravel_params']);
    
    $token = config('parity.auth_token', '');
    $http = $token ? \Illuminate\Support\Facades\Http::withToken($token) : \Illuminate\Support\Facades\Http::withHeaders([]);
    $res = $http->get($url);
    if (!$res->successful() && $res->status() !== 400) {
        echo "FAIL [HTTP " . $res->status() . "]\n";
        echo "URL: $url\n\n";
        echo $res->body() . "\n";
        exit(1);
    }
    echo "OK\n";
}
echo "All endpoints passed connection check.\n";
