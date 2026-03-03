<?php
echo "====================================\n";
echo "SCENARIO 1: plan_id <= 0\n";

$ch = curl_init('http://localhost:8000/api/gateway?c=DeviceAction&m=deleteActionPlan');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['plan_id' => 0]));
$responseLaravel = curl_exec($ch);
$laravelCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP CODE: {$laravelCode}\n";
echo "RESPONSE: " . $responseLaravel . "\n";
