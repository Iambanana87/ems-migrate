<?php
$base = 'http://localhost:8000/api/gateway';
$res1 = file_get_contents($base.'?c=Report&m=countDeviceStatus');
echo "Status: $res1\n";
$res2 = file_get_contents($base.'?c=Report&m=countFlexible');
echo "Flexible: $res2\n";
$res3 = file_get_contents($base.'?c=Report&m=countActions');
echo "Actions: $res3\n";
