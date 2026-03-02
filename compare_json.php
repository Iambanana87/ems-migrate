<?php
function compare($f1, $f2) {
    if (!file_exists($f1)) { echo "$f1 missing\n"; return; }
    if (!file_exists($f2)) { echo "$f2 missing\n"; return; }
    $j1 = json_decode(file_get_contents($f1), true);
    $j2 = json_decode(file_get_contents($f2), true);
    
    if (isset($j1['status']) && $j1['status'] === 'error') {
        echo "[ERROR IN $f1]: " . json_encode($j1) . "\n";
    }
    if (isset($j2['status']) && $j2['status'] === 'error') {
        echo "[ERROR IN $f2]: " . json_encode($j2) . "\n";
    }

    $s1 = json_encode($j1);
    $s2 = json_encode($j2);
    if ($s1 === $s2) {
        echo "MATCH: $f1 vs $f2\n";
    } else {
        echo "DIFFERENCE: $f1 vs $f2\n";
        echo "LENS: " . strlen($s1) . " vs " . strlen($s2) . "\n";
    }
}

compare('legacy_summary.json', 'laravel_summary.json');
compare('legacy_efficiency.json', 'laravel_efficiency.json');
