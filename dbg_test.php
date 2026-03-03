<?php
// Scenario 1: plan_id <= 0
// Using pure curl mapping directly against localhost
$ch = curl_init('http://localhost:8000/api/gateway?c=DeviceAction&m=deleteActionPlan');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['plan_id' => 0]));
$responseLaravel = curl_exec($ch);
$laravelCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "1) plan_id <= 0\n";
echo "Expected: 400\n";
echo "Got Code: {$laravelCode}\n";
echo "Body: {$responseLaravel}\n";
