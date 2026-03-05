<?php
$legacyUrl = "http://localhost:8080/ems/api.php?action=tc_meta";
$laravelUrl = "http://localhost:8000/api/gateway?c=Report&m=getTcMeta";

function executeRequest($url) {
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);
    
    $start = microtime(true);
    $response = curl_exec($ch);
    $end = microtime(true);
    
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    $headerStr = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    $err = curl_error($ch);
    curl_close($ch);

    // Extract Content-Type
    $contentType = '';
    foreach (explode("\r\n", $headerStr) as $line) {
        if (stripos($line, 'Content-Type:') === 0) {
            $contentType = trim(substr($line, 13));
            break;
        }
    }

    return [
        'code' => $httpCode,
        'body' => $body,
        'err' => $err,
        'content_type' => $contentType,
        'time' => ($end - $start) * 1000 // ms
    ];
}

$totalMatches = 0;
$totalDrifts = 0;
$notes = [];

echo "=====================================\n";
echo "STRICT PARITY RUNTIME TEST - tc_meta\n";
echo "=====================================\n\n";

for ($i = 1; $i <= 10; $i++) {
    // We execute sequentially as requested
    $legacy = executeRequest($legacyUrl);
    usleep(100000); // 100ms gap
    $laravel = executeRequest($laravelUrl);

    $legData = json_decode($legacy['body'], true);
    $larData = json_decode($laravel['body'], true);
    
    $match = true;
    $caseNotes = [];

    if ($legacy['code'] !== $laravel['code']) {
        $caseNotes[] = "HTTP Code mismatch: Leg({$legacy['code']}) != Lar({$laravel['code']})";
        $match = false;
    }

    if ($legacy['content_type'] !== $laravel['content_type']) {
        $caseNotes[] = "Content-Type mismatch: Leg('{$legacy['content_type']}') != Lar('{$laravel['content_type']}')";
        $match = false;
    }

    if ($legData === null || $larData === null) {
        $caseNotes[] = "JSON Parse Failure";
        $match = false;
    } else {
        // Compare keys strictly
        $legKeys = array_keys($legData);
        $larKeys = array_keys($larData);
        if ($legKeys !== $larKeys) {
            $caseNotes[] = "Keys mismatch";
            $match = false;
        }

        // We expect the exact same 'db_updated_at'. 'server_now' might drift by 1s if we cross a second boundary.
        if ($legData['db_updated_at'] !== $larData['db_updated_at']) {
            $caseNotes[] = "db_updated_at drift: '{$legData['db_updated_at']}' vs '{$larData['db_updated_at']}'";
            $match = false;
        }
        
        $t1 = strtotime($legData['server_now']);
        $t2 = strtotime($larData['server_now']);
        if (abs($t1 - $t2) > 1) { // Tolerate up to 1 sec drift due to sequential execution timing
            $caseNotes[] = "server_now drift exceeded 1s: Leg('{$legData['server_now']}') != Lar('{$larData['server_now']}')";
            $match = false;
        }
        
        if ($legData['tz'] !== $larData['tz']) {
            $caseNotes[] = "Timezone drift: Leg('{$legData['tz']}') != Lar('{$larData['tz']}')";
            $match = false;
        }
    }

    if ($match) {
        $totalMatches++;
        echo "Run {$i}/10 ... PASS\n";
    } else {
        $totalDrifts++;
        file_put_contents('err_debug.txt', print_r(['legacy' => $legacy, 'lar' => $laravel], true));
        file_put_contents('lar_500.html', $laravel['body']);
        echo "Wrote raw outputs to err_debug.txt and lar_500.html\n";
        exit;
    }
}

$out .= "\n=====================================\n";
$out .= "REPORT SUMMARY\n";
$out .= "PASS: {$totalMatches}\n";
$out .= "FAIL: {$totalDrifts}\n";

if ($totalDrifts === 0) {
    $out .= "VERDICT: PARITY CONFIRMED — ZERO STRUCTURAL DRIFT\n";
} else {
    $out .= "VERDICT: PARITY FAILED — DRIFT DETECTED\n";
}
file_put_contents('tc_meta_result.txt', $out);
echo "Done.\n";
