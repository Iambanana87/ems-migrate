<?php
$files = glob('storage/logs/parity/*.json');
rsort($files);
$j = json_decode(file_get_contents($files[0]), true);
foreach($j['endpoints'] as $k => $v) {
    if ($v['status'] === 'ERROR') {
        echo str_pad($k, 20) . " | " . $v['error'] . "\n";
    }
}
