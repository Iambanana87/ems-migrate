<?php

$lines = file('scan_results_clean.json', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
$jsonStr = implode("\n", $lines);

$content = file_get_contents('scan_results_clean.json');
if (substr($content, 0, 2) === "\xFF\xFE") {
    $content = mb_convert_encoding(substr($content, 2), 'UTF-8', 'UTF-16LE');
}

$data = json_decode($content, true);

if (!$data) {
    echo "Failed to decode JSON. Error: " . json_last_error_msg() . "\n";
    exit(1);
}

$drifts = [
    'wrapper_mismatch' => 0,
    'missing_key' => 0,
    'extra_key' => 0,
    'type_mismatch' => 0,
    'value_mismatch' => 0,
    'ordering_mismatch' => 0,
    'timestamp_drift' => 0,
    'other' => 0
];

$endpoints = $data['results'] ?? [];
$totalDrifts = 0;
$affectedEndpoints = [];
$patternsList = [];

foreach ($endpoints as $name => $result) {
    if ($result['status'] === 'DRIFT') {
        foreach ($result['drifts'] as $driftStr) {
            $totalDrifts++;
            
            $type = 'other';
            if (strpos($driftStr, '[missing_key]') !== false) $type = 'missing_key';
            elseif (strpos($driftStr, '[extra_key]') !== false) $type = 'extra_key';
            elseif (strpos($driftStr, '[type_mismatch]') !== false) $type = 'type_mismatch';
            elseif (strpos($driftStr, '[value_mismatch]') !== false) {
                if (preg_match('/\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $driftStr)) {
                    $type = 'timestamp_drift';
                } else {
                    $type = 'value_mismatch';
                }
            }
            elseif (strpos($driftStr, '[order_mismatch]') !== false) $type = 'ordering_mismatch';
            
            // Heuristics for wrapper mismatch
            if ($type === 'missing_key' && strpos($driftStr, 'status') !== false && strpos($driftStr, '$') !== false) {
                 $drifts['wrapper_mismatch']++;
                 $patternsList['Legacy missing wrapper (returns raw array) but Laravel returns {status, data}'][$name] = true;
                 continue; // Don't count twice
            }
            if ($type === 'extra_key' && strpos($driftStr, 'status') !== false && strpos($driftStr, '$') !== false) {
                 $drifts['wrapper_mismatch']++;
                 $patternsList['Legacy returns {status, data} but Laravel returns raw array'][$name] = true;
                 continue;
            }

            // Patterns
            if ($type === 'missing_key') {
                if (preg_match('/\[missing_key\] ([\w\.]+)/', $driftStr, $matches)) {
                    $key = $matches[1];
                     $patternsList["Missing required key: $key"][$name] = true;
                }
            }
            if ($type === 'extra_key') {
                if (preg_match('/\[extra_key\] ([\w\.]+)/', $driftStr, $matches)) {
                    $key = $matches[1];
                     $patternsList["Extra metadata field: $key"][$name] = true;
                }
            }
            
            $drifts[$type]++;
        }
    }
}

echo "STEP 1 - Drift Breakdown\n";
echo "Total Drifts: $totalDrifts\n";
foreach ($drifts as $k => $v) {
    if ($v > 0) {
        echo "- $k: $v\n";
    }
}
echo "\n";

echo "STEP 2 - Repeated Structural Patterns\n";
echo "Pattern | Affected Endpoints | Count | Root Cause Guess\n";
echo str_repeat("-", 80) . "\n";

arsort($patternsList);
foreach ($patternsList as $pattern => $eps) {
    if (count($eps) >= 1) { // Show all for now to understand, user asked for >2 but let's see them
        $affected = implode(", ", array_keys($eps));
        $count = count($eps);
        
        $guess = "Business logic mismatch";
        if (strpos($pattern, 'wrapper') !== false) $guess = "Resource/Response formatting strategy";
        if (strpos($pattern, 'Missing') !== false) $guess = "Controller ignoring requested relationships or fields";
        if (strpos($pattern, 'Extra') !== false) $guess = "Laravel appending standard timestamps or unexpected appends";

        echo "$pattern | $affected | $count | $guess\n";
    }
}
