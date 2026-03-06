<?php
$files = glob('storage/logs/parity/*.json');
rsort($files);
$jsonStr = file_get_contents($files[0]);
$json = json_decode($jsonStr, true);
$p=0;$f=0;$e=0;
$errors = [];
foreach ($json['endpoints'] as $epName => $ep) {
    if ($ep['status'] === 'PASS') $p++;
    elseif ($ep['status'] === 'FAIL') $f++;
    elseif ($ep['status'] === 'ERROR') { $e++; $errors[$epName] = $ep['error']; }
}
echo "PASS: $p\nDRIFT: $f\nERROR: $e\n";
if ($e > 0) {
    echo "Errors:\n";
    print_r($errors);
}
