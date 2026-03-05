<?php
/**
 * STRICT END-TO-END API PARITY CERTIFICATION (Robust Version)
 */
declare(strict_types=1);

// ── DB ─────────────────────────────────────────────────────────────────────
$dsn  = 'mysql:host=localhost;port=3306;dbname=production;charset=utf8mb4';
$user = 'device';
$pass = 'BtyLX96qZ4nDL!0w';

try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (PDOException $e) { die("DB error: " . $e->getMessage() . "\n"); }

// ── Configuration ──────────────────────────────────────────────────────────
const LEGACY_BASE  = 'http://localhost:8080/ems/api.php?action=get_machine_details';
const LARAVEL_BASE = 'http://localhost:8000/api/gateway?c=Device&m=machineDetails';

// ── HTTP Helper ────────────────────────────────────────────────────────────
function fetch(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $json = json_decode($body ?: '', true);
    if ($json === null && $body !== '[]' && $body !== '{}' && $body !== '') {
         echo "DEBUG: JSON Decode failed for URL: $url\n";
         echo "DEBUG: Body prefix: " . substr($body ?: '', 0, 200) . "\n";
    }
    return ['code' => $code, 'json' => $json, 'raw' => $body];
}

// ── Comparison Logic ───────────────────────────────────────────────────────
function compareResponses(array $legRes, array $larRes, string $context): array {
    $leg = $legRes['json'] ?? [];
    $lar = $larRes['json'] ?? [];

    if (!is_array($leg)) { echo "ERROR: Legacy response for $context is not an array\n"; $leg = []; }
    if (!is_array($lar)) { echo "ERROR: Laravel response for $context is not an array\n"; $lar = []; }

    $results = [
        'count_pass' => count($leg) === count($lar),
        'leg_count'  => count($leg),
        'lar_count'  => count($lar),
        'order_pass' => true,
        'drifts'     => [],
        'type_drifts' => [],
        'key_order_drifts' => []
    ];

    if (!$results['count_pass']) {
        $results['drifts'][] = "Count mismatch: Leg=".count($leg)." Lar=".count($lar);
    }

    $limit = min(count($leg), count($lar));
    for ($i = 0; $i < $limit; $i++) {
        $legD = $leg[$i] ?? null;
        $larD = $lar[$i] ?? null;

        if (!is_array($legD) || !is_array($larD)) {
            $results['drifts'][] = "Item at index $i is not an array (Leg=".gettype($legD)." Lar=".gettype($larD).")";
            continue;
        }

        // 1. Device Identity / Order
        $legId = $legD['mold_id'] ?? $legD['device_id'] ?? "INDEX_$i";
        $larId = $larD['mold_id'] ?? $larD['device_id'] ?? "INDEX_$i";

        if ($legId !== $larId) {
            $results['order_pass'] = false;
            $results['drifts'][] = "Order mismatch at index $i: Leg=$legId Lar=$larId";
        }

        // 2. Keys presence & Order
        $legKeys = array_keys($legD);
        $larKeys = array_keys($larD);

        $missingInLar = array_diff($legKeys, $larKeys);
        $extraInLar   = array_diff($larKeys, $legKeys);

        if (!empty($missingInLar)) $results['drifts'][] = "[$legId] Missing keys in Laravel: " . implode(',', $missingInLar);
        if (!empty($extraInLar))   $results['drifts'][] = "[$legId] Extra keys in Laravel: " . implode(',', $extraInLar);

        if ($legKeys !== $larKeys && empty($missingInLar) && empty($extraInLar)) {
            $results['key_order_drifts'][] = "[$legId] Key order mismatch";
        }

        // 3. Types and Values
        foreach ($legKeys as $k) {
            if (!array_key_exists($k, $larD)) continue;
            
            $lv = $legD[$k];
            $rv = $larD[$k];

            $lt = gettype($lv);
            $rt = gettype($rv);

            if ($lt !== $rt) {
                // Special case for null mismatch
                if ($lv === null || $rv === null) {
                    $results['drifts'][] = "[$legId] Value mismatch for '$k': Leg=".json_encode($lv)." Lar=".json_encode($rv);
                } else {
                    $results['type_drifts'][] = "[$legId] Type mismatch for '$k': Leg=$lt($lv) Lar=$rt($rv)";
                }
                continue;
            }

            if (is_numeric($lv) && is_numeric($rv)) {
                 if (abs((float)$lv - (float)$rv) > 0.001) {
                     $results['drifts'][] = "[$legId] Value mismatch for '$k': Leg=$lv Lar=$rv";
                 }
            } elseif ($lv !== $rv) {
                 $results['drifts'][] = "[$legId] Value mismatch for '$k': Leg=".json_encode($lv)." Lar=".json_encode($rv);
            }
        }
    }

    $results['all_pass'] = $results['count_pass'] && $results['order_pass'] && empty($results['drifts']) && empty($results['type_drifts']);
    return $results;
}

function printReport(string $title, array $res): void {
    echo "════════════════════════════════════════════════════════\n";
    echo " $title\n";
    echo "════════════════════════════════════════════════════════\n";
    echo "COUNT: Leg={$res['leg_count']} Lar={$res['lar_count']} " . ($res['count_pass'] ? "Γ£ô" : "Γ£ù") . "\n";
    echo "ORDER: " . ($res['order_pass'] ? "Γ£ô" : "Γ£ù") . "\n";
    
    if (!empty($res['drifts'])) {
        echo "\nDRIFTS:\n";
        foreach (array_slice($res['drifts'], 0, 15) as $d) echo "  - $d\n";
        if (count($res['drifts']) > 15) echo "  ... and " . (count($res['drifts'])-15) . " more\n";
    }

    if (!empty($res['type_drifts'])) {
        echo "\nTYPE DRIFTS:\n";
        foreach (array_slice($res['type_drifts'], 0, 10) as $d) echo "  - $d\n";
    }

    if (!empty($res['key_order_drifts'])) {
        echo "\nKEY ORDER DRIFTS: " . count($res['key_order_drifts']) . "\n";
    }

    echo "\nVERDICT: " . ($res['all_pass'] ? "PASS Γ£ô" : "FAIL Γ£ù") . "\n\n";
}

// ===========================================================================
// PHASE 1 — FULL DEVICE LIST SNAPSHOT
// ===========================================================================
foreach (['mold', 'tuft', 'blister'] as $process) {
    $leg = fetch(LEGACY_BASE . "&process=$process");
    $lar = fetch(LARAVEL_BASE . "&process=$process");
    $report = compareResponses($leg, $lar, "Snapshot: $process");
    printReport("PHASE 1 — SNAPSHOT: $process", $report);
}

// ===========================================================================
// PHASE 2 — SORTING & FILTERING
// ===========================================================================
foreach (['running', 'breakdown', 'warning'] as $status) {
    $leg = fetch(LEGACY_BASE . "&process=mold&status=$status");
    $lar = fetch(LARAVEL_BASE . "&process=mold&status=$status");
    $report = compareResponses($leg, $lar, "Filter: mold/$status");
    printReport("PHASE 2 — FILTER: mold/$status", $report);
}

// ===========================================================================
// PHASE 3 — EDGE CONTRACT TESTS
// ===========================================================================
echo "PHASE 3 — EDGE CONTRACT TESTS (Manual Injection Needed)\n";
$deviceId = 'MOLD-01';
$pdo->exec("UPDATE live_device_data SET live_data='{corrupted-json}' WHERE device_id='$deviceId'");
echo "Injected corrupted JSON into $deviceId\n";

$leg = fetch(LEGACY_BASE . "&process=mold");
$lar = fetch(LARAVEL_BASE . "&process=mold");
$report = compareResponses($leg, $lar, "Edge: Corrupted JSON");
printReport("PHASE 3 — EDGE: Corrupted JSON", $report);

// Cleanup
$pdo->exec("UPDATE live_device_data SET live_data=NULL WHERE device_id='$deviceId'");
echo "Cleaned up $deviceId\n";
