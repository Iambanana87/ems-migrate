<?php
$files = glob('storage/logs/parity/*.json');
rsort($files);
$j = json_decode(file_get_contents($files[0]), true);

$counts = ['PASS' => 0, 'FAIL' => 0, 'ERROR' => 0, 'EXPECTED_STATE_MISMATCH' => 0];
foreach($j['endpoints'] as $k => $v) {
    if (isset($counts[$v['status']])) {
        $counts[$v['status']]++;
    } else {
        $counts[$v['status']] = 1;
    }
}
echo "PASS: " . $counts['PASS'] . "\n";
echo "DRIFT: " . $counts['FAIL'] . "\n";
echo "EXPECTED_STATE_MISMATCH: " . $counts['EXPECTED_STATE_MISMATCH'] . "\n";
echo "ERROR: " . $counts['ERROR'] . "\n";
