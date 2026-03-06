<?php
$files = glob('storage/logs/parity/*.json');
rsort($files);
$j = json_decode(file_get_contents($files[0]), true);
$out = [];
foreach($j['endpoints'] as $k => $v) {
    if ($v['status'] === 'ERROR') {
        $out[$k] = $v['error'];
    }
}
file_put_contents('exact_mismatches.json', json_encode($out, JSON_PRETTY_PRINT));
