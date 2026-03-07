<?php
$base = 'http://localhost:8000/api/gateway';
$tests = [
  ['c' => 'Device', 'm' => 'getDevices'],
  ['c' => 'Report', 'm' => 'summary'],
  ['c' => 'Family', 'm' => 'index'],
  ['c' => 'Report', 'm' => 'actionsBoard'],
];
foreach ($tests as $t) {
  $url = $base . '?' . http_build_query($t);
  echo "Testing: $url\n";
  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 5);
  $res = curl_exec($ch);
  $http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
  echo "Status: $http\n";
  echo "Response start: " . substr($res, 0, 150) . "\n\n";
}
