<?php
$ch = curl_init("http://localhost:8000/api/gateway?c=Report&m=getTcMeta");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
echo "RESPONSE FROM LARAVEL:\n";
echo $res;
echo "\nERROR:\n";
echo curl_error($ch);
