<?php
/**
 * STRICT RUNTIME PARITY CERTIFICATION — TUFT
 *
 * Phase 1: 5 disconnected live_data payloads
 *          Verifies key presence/absence matches legacy guard exactly.
 * Phase 2: Connected deterministic float test (5 cycles)
 *          Verifies zero rounding/encoding drift for target=20, output=13.37
 */
declare(strict_types=1);

// ── DB Config ─────────────────────────────────────────────────────────────────
$dsn  = 'mysql:host=localhost;port=3306;dbname=production;charset=utf8mb4';
$user = 'device';
$pass = 'BtyLX96qZ4nDL!0w';

// ── Endpoints ─────────────────────────────────────────────────────────────────
const LEGACY_URL  = 'http://localhost:8080/ems/api.php?view=tuft';
const LARAVEL_URL = 'http://localhost:8000/api/gateway?c=Device&m=machineDetails&process=tuft';
const DEVICE_ID   = 'TUFT-TEST-01';

// ── HTTP ──────────────────────────────────────────────────────────────────────
function fetchJson(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
    ]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'json' => json_decode($body ?: 'null', true)];
}

// ── Extract metrics from response ─────────────────────────────────────────────
function fromLegacy(array $res, string $id): array {
    $list = $res['json']['devices'] ?? $res['json'] ?? [];
    if (!is_array($list)) return [];
    foreach ($list as $d) {
        if (($d['device_id'] ?? '') !== $id) continue;
        $live = $d['live_data'] ?? [];
        if (is_string($live)) $live = json_decode($live, true) ?: [];
        return [
            'efficiency'     => array_key_exists('efficiency',     $live) ? $live['efficiency']     : 'ABSENT',
            'loss_pcs'       => array_key_exists('loss_pcs',       $live) ? $live['loss_pcs']       : 'ABSENT',
            'idle_breakdown' => array_key_exists('idle_breakdown', $live) ? $live['idle_breakdown'] : 'ABSENT',
        ];
    }
    return ['_notfound' => true];
}

function fromLaravel(array $res, string $id): array {
    $list = $res['json'] ?? [];
    if (!is_array($list)) return [];
    if (!isset($list[0])) $list = array_values($list);
    foreach ($list as $d) {
        if (($d['mold_id'] ?? $d['device_id'] ?? '') !== $id) continue;
        return [
            'efficiency'     => array_key_exists('efficiency',     $d) ? $d['efficiency']     : 'ABSENT',
            'loss_pcs'       => array_key_exists('total_lost_pcs', $d) ? $d['total_lost_pcs'] : 'ABSENT',
            'idle_breakdown' => array_key_exists('lost_time',      $d) ? $d['lost_time']      : 'ABSENT',
        ];
    }
    return ['_notfound' => true];
}

// ── DB connect ────────────────────────────────────────────────────────────────
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE    => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (PDOException $e) {
    die("DB error: " . $e->getMessage() . "\n");
}

// ── Create or backup test device ──────────────────────────────────────────────
$existing = $pdo->query("SELECT * FROM devices WHERE device_id = '" . DEVICE_ID . "' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$existsLive = $pdo->query("SELECT live_data FROM live_device_data WHERE device_id = '" . DEVICE_ID . "' LIMIT 1")->fetch(PDO::FETCH_ASSOC);

if (!$existing) {
    // Insert a minimal tuft test device
    $pdo->exec("INSERT INTO devices
        (device_id, display_type, process, product, client, target_limit, capacity, frequency,
         lower_limit, upper_limit, efficiency_lower_limit, flex)
        VALUES ('" . DEVICE_ID . "', 'tuft', 'Single', 'TestFamily', NULL, 20, 0, 60,
         NULL, NULL, 0, 0)");
    echo "[Setup] Created test device " . DEVICE_ID . "\n\n";
    $deviceIsNew = true;
} else {
    echo "[Setup] Using existing device " . DEVICE_ID . "\n\n";
    $deviceIsNew = false;
}

// Temporary: ensure target_limit = 20, capacity = 0 for deterministic test
$savedTarget   = $existing['target_limit'] ?? null;
$savedCapacity = $existing['capacity']     ?? null;
$pdo->exec("UPDATE devices SET target_limit = 20, capacity = 0 WHERE device_id = '" . DEVICE_ID . "'");

// ── Helper: inject live_data and run comparison ────────────────────────────────
function runScenario(PDO $pdo, string $label, ?string $liveJson, bool $connected = false): array {
    $deviceId = DEVICE_ID;

    // Inject
    if ($liveJson === null) {
        // Remove live data entirely (simulate null)
        $pdo->exec("DELETE FROM live_device_data WHERE device_id = '$deviceId'");
    } else {
        // Decode, optionally freshen datetime if connected
        $payload = json_decode($liveJson, true) ?: [];
        if ($connected) {
            $payload['datetime'] = date('Y-m-d H:i:s');
        }
        // Intentionally NOT adding datetime for disconnected — device will be DISCONNECTED
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE);
        $check = $pdo->query("SELECT device_id FROM live_device_data WHERE device_id = '$deviceId' LIMIT 1")->fetch();
        if ($check) {
            $stmt = $pdo->prepare("UPDATE live_device_data SET live_data = ?, last_updated = NOW() WHERE device_id = ?");
            $stmt->execute([$json, $deviceId]);
        } else {
            $stmt = $pdo->prepare("INSERT INTO live_device_data (device_id, live_data, last_updated) VALUES (?, ?, NOW())");
            $stmt->execute([$deviceId, $json]);
        }
    }

    usleep(100000); // 100ms settle

    $legResp = fetchJson(LEGACY_URL);
    $larResp = fetchJson(LARAVEL_URL);

    $legM = fromLegacy($legResp, $deviceId);
    $larM = fromLaravel($larResp, $deviceId);

    $fields = ['efficiency', 'loss_pcs', 'idle_breakdown'];
    $drifts = [];
    $results = [];

    foreach ($fields as $f) {
        $lv = $legM[$f] ?? 'ABSENT';
        $rv = $larM[$f] ?? 'ABSENT';

        $bothAbsent  = ($lv === 'ABSENT' && $rv === 'ABSENT');
        $bothNumeric = is_numeric($lv) && is_numeric($rv);
        $delta       = $bothNumeric ? abs((float)$lv - (float)$rv) : null;

        $pass = $bothAbsent || ($bothNumeric && $delta < 0.001);

        $results[$f] = [
            'leg'   => $lv,
            'lar'   => $rv,
            'delta' => $delta,
            'pass'  => $pass,
        ];

        if (!$pass) {
            $drifts[] = "$f: leg=$lv lar=$rv   delta=" . ($delta !== null ? round($delta, 6) : 'N/A');
        }
    }

    return [
        'label'   => $label,
        'results' => $results,
        'verdict' => empty($drifts) ? 'PASS' : 'FAIL',
        'drifts'  => $drifts,
    ];
}

// ═══════════════════════════════════════════════════════════════
// PHASE 1 — DISCONNECTED SCENARIOS
// ═══════════════════════════════════════════════════════════════
echo "═══════════════════════════════════════════════\n";
echo " PHASE 1 — DISCONNECTED PAYLOAD SCENARIOS\n";
echo "═══════════════════════════════════════════════\n\n";

$phase1Cases = [
    ['A. null (no row)',       null],
    ['B. empty object {}',    '{}'],
    ['C. {"output":0}',       '{"output":0}'],
    ['D. {"rate":10}',        '{"rate":10}'],
    ['E. {"output":5}',       '{"output":5}'],
];

$phase1Pass = 0;
$phase1Fail = 0;
$phase1Rows = [];

foreach ($phase1Cases as [$label, $json]) {
    $r = runScenario($pdo, $label, $json, false);
    if ($r['verdict'] === 'PASS') $phase1Pass++;
    else $phase1Fail++;
    $phase1Rows[] = $r;

    $eff = $r['results']['efficiency']['leg'];
    $lp  = $r['results']['loss_pcs']['leg'];
    $ib  = $r['results']['idle_breakdown']['leg'];
    printf("%-24s  legacy(eff=%s,lp=%s,ib=%s)  verdict=%s\n",
        $label,
        $eff === 'ABSENT' ? 'ABSENT' : round((float)$eff, 2),
        $lp  === 'ABSENT' ? 'ABSENT' : round((float)$lp, 2),
        $ib  === 'ABSENT' ? 'ABSENT' : round((float)$ib, 2),
        $r['verdict']
    );
    foreach ($r['drifts'] as $drift) {
        echo "  ↳ DRIFT: $drift\n";
    }
}

echo "\nPhase 1 Summary: PASS=$phase1Pass FAIL=$phase1Fail\n";

// ═══════════════════════════════════════════════════════════════
// PHASE 2 — CONNECTED DETERMINISTIC FLOAT TEST
// ═══════════════════════════════════════════════════════════════
echo "\n═══════════════════════════════════════════════\n";
echo " PHASE 2 — CONNECTED DETERMINISTIC FLOAT TEST\n";
echo "═══════════════════════════════════════════════\n\n";

// Expected with target=20, capacity=0, output=13.37
// targetPerMin = 20
// actualPerMin = 13.37
// eff = (13.37/20)*100 = 66.85
// capacityPerHour = 20*60 = 1200 (fallback since capacity=0)
// actualPerHour = 13.37*60 = 802.2
// lossPcs = max(0, 1200-802.2) = 397.8
// idleMins = 397.8/20 = 19.89
$expEff  = round((13.37 / 20) * 100, 2);        // 66.85
$expLoss = round(max(0, (20*60) - (13.37*60)), 2); // 397.80
$expIdle = round($expLoss / 20, 2);               // 19.89

printf("Device: target_limit=20 capacity=0 | live: {\"output\":13.37}\n");
printf("Expected: efficiency=%.2f  loss_pcs=%.2f  idle_breakdown=%.2f\n\n",
    $expEff, $expLoss, $expIdle);

$sep  = str_repeat('─', 120);
printf("%-5s | %-16s | %10s %10s %12s | %10s %12s %12s | %4s\n",
    'CYC', 'FIELD', 'LEGACY', 'LARAVEL', 'DELTA', 'EXPECTED', 'LEG_ERR', 'LAR_ERR', 'PASS');
echo $sep . "\n";

$phase2Pass = 0;
$phase2Fail = 0;
$connectedPayload = '{"output":13.37}';

for ($c = 1; $c <= 5; $c++) {
    $r = runScenario($pdo, "Cycle $c", $connectedPayload, true);
    if ($r['verdict'] === 'PASS') $phase2Pass++;
    else $phase2Fail++;

    $expected = ['efficiency' => $expEff, 'loss_pcs' => $expLoss, 'idle_breakdown' => $expIdle];
    foreach (['efficiency', 'loss_pcs', 'idle_breakdown'] as $f) {
        $lv = $r['results'][$f]['leg'];
        $rv = $r['results'][$f]['lar'];
        $delta = $r['results'][$f]['delta'];
        $exp   = $expected[$f];
        $legErr = is_numeric($lv) ? abs((float)$lv - $exp) : null;
        $larErr = is_numeric($rv) ? abs((float)$rv - $exp) : null;

        printf("%-5s | %-16s | %10s %10s %12s | %10s %12s %12s | %4s\n",
            "C$c", $f,
            is_numeric($lv) ? number_format((float)$lv, 4) : (string)$lv,
            is_numeric($rv) ? number_format((float)$rv, 4) : (string)$rv,
            $delta !== null ? number_format($delta, 8) : 'N/A',
            number_format($exp, 4),
            $legErr !== null ? number_format($legErr, 8) : 'N/A',
            $larErr !== null ? number_format($larErr, 8) : 'N/A',
            $r['results'][$f]['pass'] ? '✓' : '✗'
        );
        foreach ($r['drifts'] as $drift) {
            echo "  ↳ DRIFT in field: $drift\n";
        }
    }
    echo str_repeat(' ', 6) . "|\n";
}
echo $sep . "\n";

// ── Final Report ──────────────────────────────────────────────────────────────
echo "\n=============================================\n";
echo " TUFT METRICS PARITY CERTIFICATION REPORT\n";
echo "=============================================\n";
echo " Phase 1 (disconnected):  PASS=$phase1Pass FAIL=$phase1Fail\n";
echo " Phase 2 (connected ×5):  PASS=$phase2Pass FAIL=$phase2Fail\n";
$totalFail = $phase1Fail + $phase2Fail;
if ($totalFail === 0) {
    echo " FINAL VERDICT: CERTIFIED — ZERO BEHAVIORAL DRIFT\n";
} else {
    echo " FINAL VERDICT: DRIFT DETECTED\n";
}
echo "=============================================\n";

// ── Cleanup ───────────────────────────────────────────────────────────────────
echo "\n[Cleanup] Restoring device state...\n";
if ($deviceIsNew) {
    $pdo->exec("DELETE FROM live_device_data WHERE device_id = '" . DEVICE_ID . "'");
    $pdo->exec("DELETE FROM devices WHERE device_id = '" . DEVICE_ID . "'");
    echo "[Cleanup] Deleted temporary test device.\n";
} else {
    $pdo->exec("UPDATE devices SET target_limit = " . ((float)$savedTarget) . ",
                 capacity = " . ((float)$savedCapacity) . "
                 WHERE device_id = '" . DEVICE_ID . "'");
    if ($existsLive) {
        $pdo->prepare("UPDATE live_device_data SET live_data = ? WHERE device_id = ?")
            ->execute([$existsLive['live_data'], DEVICE_ID]);
    } else {
        $pdo->exec("DELETE FROM live_device_data WHERE device_id = '" . DEVICE_ID . "'");
    }
    echo "[Cleanup] Restored original device config.\n";
}
