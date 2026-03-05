<?php

// verify_mutation_update_ap.php

$dbHost = 'localhost';
$dbName = 'production';
$dbUser = 'root';
$dbPass = '';

try {
    $pdo = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8", $dbUser, $dbPass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("DB Connection failed: " . $e->getMessage() . "\n");
}

$legacyUrl = "http://localhost/ems/api.php";
$laravelUrl = "http://localhost/ems/laravel-api/public/api/gateway";

function setupFixture($pdo, $caseName) {
    try {
        $pdo->exec("SET FOREIGN_KEY_CHECKS=0");
        $pdo->exec("DELETE FROM device_action_plans WHERE id >= 99000");
        $pdo->exec("DELETE FROM device_actions WHERE id = 99999");
        $pdo->exec("DELETE FROM audit_trail WHERE reason LIKE '%device_action_plans:status#9900%' OR reason LIKE 'device_actions:auto_status_recalc#99999'");
        $pdo->exec("SET FOREIGN_KEY_CHECKS=1");
    } catch (PDOException $e) {
        file_put_contents('pdo_err.txt', $e->getMessage());
        die("Wrote error to pdo_err.txt\n");
    }

    $stmt = $pdo->prepare("INSERT INTO device_actions (id, device_id, title, status, approval_status) VALUES (?, 'TEST', 'Test Issue', ?, ?)");
    $stmt->execute([99999, 'open', 'pending']);
    $actionId = 99999;

    $stmtPlan = $pdo->prepare("INSERT INTO device_action_plans (id, action_id, plan_text, status) VALUES (?, ?, 'Test Plan', ?)");
    
    $plans = [];
    if (strpos($caseName, 'parent_cascade') !== false) {
        $stmtPlan->execute([99001, $actionId, 'done']);
        $plans[] = 99001;
        $stmtPlan->execute([99002, $actionId, 'open']);
        $plans[] = 99002;
    } else {
        $stmtPlan->execute([99001, $actionId, 'open']);
        $plans[] = 99001;
        $stmtPlan->execute([99002, $actionId, 'open']);
        $plans[] = 99002;
    }
    
    return ['action_id' => $actionId, 'plans' => $plans];
}

function captureDbState($pdo, $actionId, $planId) {
    $state = [];
    $st = $pdo->prepare("SELECT status, approval_status FROM device_actions WHERE id = ?");
    $st->execute([$actionId]);
    $state['action'] = $st->fetch(PDO::FETCH_ASSOC);

    if ($planId) {
        $st = $pdo->prepare("SELECT status FROM device_action_plans WHERE id = ?");
        $st->execute([$planId]);
        $state['plan'] = $st->fetch(PDO::FETCH_ASSOC);
        
        $st = $pdo->prepare("SELECT type, reason, diff FROM audit_trail WHERE reason = ? ORDER BY id DESC LIMIT 1");
        $st->execute(["device_action_plans:status#" . $planId]);
        $audit = $st->fetch(PDO::FETCH_ASSOC);
        if ($audit) {
            if (isset($audit['diff'])) $audit['diff'] = json_decode($audit['diff'], true);
            $state['audit'] = $audit;
        } else {
            $state['audit'] = null;
        }
    }
    return $state;
}

function executeRequest($url, $postData, $isLegacy) {
    $ch = curl_init();
    
    // Explicit headers map for testing
    $headers = [];
    
    if ($isLegacy) {
        $postData['action'] = 'update_action_plan_status';
        curl_setopt($ch, CURLOPT_URL, $url);
    } else {
        $urlParams = http_build_query(['c' => 'DeviceAction', 'm' => 'update_action_plan_status']);
        curl_setopt($ch, CURLOPT_URL, $url . '?' . $urlParams);
    }
    
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($postData));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HEADER, true);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    
    $headerStr = substr($response, 0, $headerSize);
    $body = substr($response, $headerSize);
    
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
        'content_type' => $contentType
    ];
}

$testCases = [
    [
        'name' => 'valid_update',
        'params' => ['status' => 'done', 'reason' => 'Fixing test unit'],
    ],
    [
        'name' => 'invalid_plan_id',
        'params' => ['plan_id' => -99, 'status' => 'done'],
        'override_plan_id' => true 
    ],
    [
        'name' => 'missing_plan_id',
        'params' => ['status' => 'done'],
        'omit_plan_id' => true
    ],
    [
        'name' => 'invalid_status',
        'params' => ['status' => 'INVALID_STATUS'], // Should fallback to 'open'
    ],
    [
        'name' => 'parent_cascade_trigger',
        'params' => ['status' => 'done'],
    ],
];

$results = [];
$totalParity = true;

foreach ($testCases as $case) {
    // =========== LEGACY EXECUTION ===========
    $fixtureLegacy = setupFixture($pdo, $case['name']);
    $planIdLegacy = $fixtureLegacy['plans'][count($fixtureLegacy['plans']) - 1];
    
    $postLegacy = $case['params'];
    if (empty($case['omit_plan_id']) && empty($case['override_plan_id'])) {
        $postLegacy['plan_id'] = $planIdLegacy;
    }
    
    $respLegacy = executeRequest($legacyUrl, $postLegacy, true);
    $dbLegacy = captureDbState($pdo, $fixtureLegacy['action_id'], $planIdLegacy);

    // =========== LARAVEL EXECUTION ===========
    $fixtureLaravel = setupFixture($pdo, $case['name']);
    $planIdLaravel = $fixtureLaravel['plans'][count($fixtureLaravel['plans']) - 1];
    
    $postLaravel = $case['params'];
    if (empty($case['omit_plan_id']) && empty($case['override_plan_id'])) {
        $postLaravel['plan_id'] = $planIdLaravel;
    }

    $respLaravel = executeRequest($laravelUrl, $postLaravel, false);
    $dbLaravel = captureDbState($pdo, $fixtureLaravel['action_id'], $planIdLaravel);

    // =========== COMPARISON ===========
    $notes = [];
    $isMatch = true;

    if ($respLegacy['code'] !== $respLaravel['code']) {
        $notes[] = "HTTP Code mismatch: " . $respLegacy['code'] . " vs " . $respLaravel['code'];
        $isMatch = false;
    }

    if ($respLegacy['content_type'] !== $respLaravel['content_type']) {
        $notes[] = "Content-Type mismatch: '" . $respLegacy['content_type'] . "' vs '" . $respLaravel['content_type'] . "'";
        $isMatch = false;
    }

    $jsonLeg = json_decode($respLegacy['body'], true);
    $jsonLar = json_decode($respLaravel['body'], true);
    
    if ($jsonLeg !== $jsonLar) {
        // Exclude specific dynamic fields in error traces
        if (isset($jsonLeg['file'], $jsonLar['file'])) {
            unset($jsonLeg['file'], $jsonLar['file'], $jsonLeg['line'], $jsonLar['line']);
        }
        if ($jsonLeg !== $jsonLar) {
            $notes[] = "JSON Body mismatch: " . json_encode($jsonLeg) . " vs " . json_encode($jsonLar);
            $isMatch = false;
        } else {
            $notes[] = "(Exception file/line traces ignored as expected)";
        }
    }

    // Abstract DB IDs from audit snapshot for comparison
    if (isset($dbLegacy['audit'], $dbLaravel['audit'])) {
        foreach (['old_data', 'new_data'] as $k) {
            if (isset($dbLegacy['audit'][$k]['__plan_snapshot']['plan_id'])) {
                $dbLegacy['audit'][$k]['__plan_snapshot']['plan_id'] = 'ID_MASKED';
            }
            if (isset($dbLaravel['audit'][$k]['__plan_snapshot']['plan_id'])) {
                $dbLaravel['audit'][$k]['__plan_snapshot']['plan_id'] = 'ID_MASKED';
            }
            // Abstract explicit table ID keys
            if (isset($dbLegacy['audit'][$k]['id'])) $dbLegacy['audit'][$k]['id'] = 'ID_MASKED';
            if (isset($dbLaravel['audit'][$k]['id'])) $dbLaravel['audit'][$k]['id'] = 'ID_MASKED';
            if (isset($dbLegacy['audit'][$k]['action_id'])) $dbLegacy['audit'][$k]['action_id'] = 'ID_MASKED';
            if (isset($dbLaravel['audit'][$k]['action_id'])) $dbLaravel['audit'][$k]['action_id'] = 'ID_MASKED';
        }
    }

    if ($dbLegacy !== $dbLaravel) {
        $notes[] = "DB State mismatch: " . json_encode($dbLegacy) . " vs " . json_encode($dbLaravel);
        $isMatch = false;
    }

    if (!$isMatch) $totalParity = false;

    $results[] = [
        'name' => $case['name'],
        'match' => $isMatch,
        'notes' => implode(" | ", $notes)
    ];
}

// ==== CONCURRENCY TEST ====
$fixtureL = setupFixture($pdo, 'concurrency_legacy');
$planL = $fixtureL['plans'][1];
$mhL = curl_multi_init();
$ch1L = curl_init($legacyUrl);
curl_setopt($ch1L, CURLOPT_POST, 1);
curl_setopt($ch1L, CURLOPT_POSTFIELDS, http_build_query(['action'=>'update_action_plan_status','plan_id'=>$planL,'status'=>'done']));
curl_setopt($ch1L, CURLOPT_RETURNTRANSFER, true);
$ch2L = curl_init($legacyUrl);
curl_setopt($ch2L, CURLOPT_POST, 1);
curl_setopt($ch2L, CURLOPT_POSTFIELDS, http_build_query(['action'=>'update_action_plan_status','plan_id'=>$planL,'status'=>'done']));
curl_setopt($ch2L, CURLOPT_RETURNTRANSFER, true);
curl_multi_add_handle($mhL, $ch1L);
curl_multi_add_handle($mhL, $ch2L);
$running = null;
do { curl_multi_exec($mhL, $running); } while ($running);
curl_multi_remove_handle($mhL, $ch1L);
curl_multi_remove_handle($mhL, $ch2L);
curl_multi_close($mhL);
$dbLegacyC = captureDbState($pdo, $fixtureL['action_id'], $planL);

$fixtureR = setupFixture($pdo, 'concurrency_laravel');
$planR = $fixtureR['plans'][1];
$urlParams = http_build_query(['c' => 'DeviceAction', 'm' => 'update_action_plan_status']);
$mhR = curl_multi_init();
$ch1R = curl_init($laravelUrl . '?' . $urlParams);
curl_setopt($ch1R, CURLOPT_POST, 1);
curl_setopt($ch1R, CURLOPT_POSTFIELDS, http_build_query(['plan_id'=>$planR,'status'=>'done']));
curl_setopt($ch1R, CURLOPT_RETURNTRANSFER, true);
$ch2R = curl_init($laravelUrl . '?' . $urlParams);
curl_setopt($ch2R, CURLOPT_POST, 1);
curl_setopt($ch2R, CURLOPT_POSTFIELDS, http_build_query(['plan_id'=>$planR,'status'=>'done']));
curl_setopt($ch2R, CURLOPT_RETURNTRANSFER, true);
curl_multi_add_handle($mhR, $ch1R);
curl_multi_add_handle($mhR, $ch2R);
$running = null;
do { curl_multi_exec($mhR, $running); } while ($running);
curl_multi_remove_handle($mhR, $ch1R);
curl_multi_remove_handle($mhR, $ch2R);
curl_multi_close($mhR);
$dbLaravelC = captureDbState($pdo, $fixtureR['action_id'], $planR);

// Abstract concurrency IDs for check
foreach (['detail'] as $k) {
  if (isset($dbLegacyC['audit'][$k]['__plan_snapshot']['plan_id'])) $dbLegacyC['audit'][$k]['__plan_snapshot']['plan_id'] = 'ID_MASKED';
  if (isset($dbLaravelC['audit'][$k]['__plan_snapshot']['plan_id'])) $dbLaravelC['audit'][$k]['__plan_snapshot']['plan_id'] = 'ID_MASKED';
  if (isset($dbLegacyC['audit'][$k]['id'])) $dbLegacyC['audit'][$k]['id'] = 'ID_MASKED';
  if (isset($dbLaravelC['audit'][$k]['id'])) $dbLaravelC['audit'][$k]['id'] = 'ID_MASKED';
  if (isset($dbLegacyC['audit'][$k]['action_id'])) $dbLegacyC['audit'][$k]['action_id'] = 'ID_MASKED';
  if (isset($dbLaravelC['audit'][$k]['action_id'])) $dbLaravelC['audit'][$k]['action_id'] = 'ID_MASKED';
}

$cMatch = ($dbLegacyC == $dbLaravelC);
if (!$cMatch) $totalParity = false;

$results[] = [
    'name' => 'concurrency_simulation',
    'match' => $cMatch,
    'notes' => $cMatch ? 'Identical state after race' : "Concurrency state diff: " . json_encode($dbLegacyC) . " vs " . json_encode($dbLaravelC)
];

// OUTPUT REPORT
$out = "";
foreach ($results as $r) {
    preg_match_all('/(.{1,120})/', $r['notes'] ?: 'Identical', $lines);
    $out .= "CASE: {$r['name']}\n";
    $out .= "MATCH: " . ($r['match'] ? "YES" : "NO") . "\n";
    $out .= "NOTES:\n  " . implode("\n  ", $lines[0]) . "\n";
    $out .= "----------------------------------------\n";
}

if ($totalParity) {
    $out .= "\nPARITY CONFIRMED — ZERO STRUCTURAL DRIFT\n";
} else {
    $out .= "\nPARITY FAILED — LIST EXACT DRIFTS\n";
}

file_put_contents('parity_report_update_ap_mutation.txt', $out);
echo "Mutation Parity Test Complete.\n";
