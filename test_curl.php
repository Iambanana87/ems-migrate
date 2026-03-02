<?php
$ch = curl_init('http://localhost:8080/ems/api.php');
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(["action"=>"update_action_plan_status"]));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
echo "LEGACY: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . " " . $res . "\n";

$ch2 = curl_init('http://localhost:8000/api/gateway?c=DeviceAction&m=updateActionPlanStatus');
curl_setopt($ch2, CURLOPT_POSTFIELDS, http_build_query([]));
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
$res2 = curl_exec($ch2);
echo "LARAVEL: " . curl_getinfo($ch2, CURLINFO_HTTP_CODE) . " " . $res2 . "\n";
