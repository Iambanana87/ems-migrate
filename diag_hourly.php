<?php
$url = "http://localhost:8080/ems/api.php?action=get_hourly_report&device_id=MOLD-01&report_date=2026-02-26";
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$result = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "HTTP CODE: $http_code\n";
echo "BODY:\n$result\n";
