<?php
$ch = curl_init('http://localhost/ems/api.php?action=get_output_report&from=2026-02-27T07:00:00&to=2026-02-28T07:00:00&process=mold');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
$res = curl_exec($ch);
echo "Raw Response: " . $res . "\n";
echo "HTTP Code: " . curl_getinfo($ch, CURLINFO_HTTP_CODE) . "\n";
