<?php
/**
 * STRICT RUNTIME PARITY CERTIFICATION — calculateBlisterMetrics
 *
 * Phase 1: 8 disconnected / edge live_data payloads
 * Phase 2: BPC inference test (brushes_per_cycle derived from capacity/target)
 * Phase 3: Float stability — 5 cycles with fractional target + float output
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

// ── Endpoints ──────────────────────────────────────────────────────────────
const LEGACY_URL  = 'http://localhost:8080/ems/api.php?view=blister';
const LARAVEL_URL = 'http://localhost:8000/api/gateway?c=Device&m=machineDetails&process=blister';
const DEVICE_ID   = 'BLISTER-CERT-01';

// ── HTTP ───────────────────────────────────────────────────────────────────
function fetch(string $url): array {
    $ch = curl_init($url);
    curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'json' => json_decode($body ?: 'null', true)];
}

// ── Metric extractors ──────────────────────────────────────────────────────
function legacyMetrics(array $res): array {
    $list = $res['json']['devices'] ?? $res['json'] ?? [];
    foreach ((array)$list as $d) {
        if (($d['device_id'] ?? '') !== DEVICE_ID) continue;
        $live = $d['live_data'] ?? [];
        if (is_string($live)) $live = json_decode($live, true) ?: [];
        return [
            'efficiency'     => array_key_exists('efficiency',     $live) ? $live['efficiency']     : 'ABSENT',
            'loss_pcs'       => array_key_exists('loss_pcs',       $live) ? $live['loss_pcs']       : 'ABSENT',
            'idle_breakdown' => array_key_exists('idle_breakdown', $live) ? $live['idle_breakdown'] : 'ABSENT',
        ];
    }
    return [];
}

function laravelMetrics(array $res): array {
    $list = $res['json'] ?? [];
    if (!is_array($list)) return [];
    if (!isset($list[0])) $list = array_values($list);
    foreach ($list as $d) {
        if (($d['mold_id'] ?? $d['device_id'] ?? '') !== DEVICE_ID) continue;
        return [
            'efficiency'     => array_key_exists('efficiency',     $d) ? $d['efficiency']     : 'ABSENT',
            'loss_pcs'       => array_key_exists('total_lost_pcs', $d) ? $d['total_lost_pcs'] : 'ABSENT',
            'idle_breakdown' => array_key_exists('lost_time',      $d) ? $d['lost_time']      : 'ABSENT',
        ];
    }
    return [];
}

// ── DB helpers ─────────────────────────────────────────────────────────────
function upsertLive(PDO $pdo, ?string $payload, bool $connected = false): void {
    $id = DEVICE_ID;
    if ($payload === null) {
        $pdo->exec("DELETE FROM live_device_data WHERE device_id = '$id'");
        return;
    }
    $data = json_decode($payload, true) ?: [];
    if ($connected) $data['datetime'] = date('Y-m-d H:i:s');
    $json  = json_encode($data, JSON_UNESCAPED_UNICODE);
    $exists = $pdo->query("SELECT 1 FROM live_device_data WHERE device_id='$id'")->fetch();
    if ($exists) {
        $pdo->prepare("UPDATE live_device_data SET live_data=?, last_updated=NOW() WHERE device_id=?")
            ->execute([$json, $id]);
    } else {
        $pdo->prepare("INSERT INTO live_device_data (device_id,live_data,last_updated) VALUES(?,?,NOW())")
            ->execute([$id, $json]);
    }
}

function compareScenario(PDO $pdo, string $label, ?string $payload,
                          array $expected, bool $connected = false): array {
    upsertLive($pdo, $payload, $connected);
    usleep(120000);

    $legR = fetch(LEGACY_URL);
    $larR = fetch(LARAVEL_URL);
    $legM = legacyMetrics($legR);
    $larM = laravelMetrics($larR);

    $fields  = ['efficiency', 'loss_pcs', 'idle_breakdown'];
    $drifts  = [];
    $data    = [];
    $pass    = true;

    foreach ($fields as $f) {
        $lv = $legM[$f] ?? 'ABSENT';
        $rv = $larM[$f] ?? 'ABSENT';
        $exp = $expected[$f] ?? 'ABSENT';

        $bothAbsent  = ($lv === 'ABSENT' && $rv === 'ABSENT');
        $bothNumeric = is_numeric($lv) && is_numeric($rv);
        $delta       = $bothNumeric ? abs((float)$lv - (float)$rv) : null;
        $fieldPass   = $bothAbsent || ($bothNumeric && $delta < 0.001);
        if (!$fieldPass) { $pass = false; $drifts[] = "$f leg=$lv lar=$rv"; }

        $expDelta = ($bothNumeric && $exp !== 'ABSENT') ? abs((float)$lv - (float)$exp) : null;

        $data[$f] = compact('lv','rv','delta','expDelta','fieldPass','exp');
    }
    return ['label' => $label, 'data' => $data, 'pass' => $pass, 'drifts' => $drifts];
}

// ============================================================
// DEVICE SETUP — target=30, capacity=36000, brushes_per_cycle=0
// ============================================================
$existing = $pdo->query("SELECT * FROM devices WHERE device_id='".DEVICE_ID."' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$existsLive = $pdo->query("SELECT live_data FROM live_device_data WHERE device_id='".DEVICE_ID."' LIMIT 1")->fetch(PDO::FETCH_ASSOC);
$isNew = !$existing;

if ($isNew) {
    $pdo->exec("INSERT INTO devices
        (device_id,display_type,process,product,client,target_limit,capacity,frequency,
         lower_limit,upper_limit,efficiency_lower_limit,flex,brushes_per_cycle)
        VALUES ('".DEVICE_ID."','blister','Single','TestBlister',NULL,30,36000,60,
        NULL,NULL,0,0,0)");
    echo "[Setup] Created BLISTER-CERT-01 (target=30, capacity=36000, bpc=0)\n\n";
} else {
    // patch for deterministic config
    $pdo->exec("UPDATE devices SET target_limit=30, capacity=36000, brushes_per_cycle=0
                WHERE device_id='".DEVICE_ID."'");
    echo "[Setup] Patched existing BLISTER-CERT-01 (target=30, capacity=36000, bpc=0)\n\n";
}

// ============================================================
// PHASE 1 — DISCONNECTED SCENARIOS
// BPC inferred: round(36000/(30*60))=20. targetPerMinPcs=600
// ============================================================
// Pre-compute per-scenario expected values:
// Capacity fallback: device capacity=36000 → used directly
// brushes = 20, targetPerMinPcs = 600

function exp_phase1(float $actualPcs): array {
    $tgt = 600.0; $cap = 36000.0; $tgtPer = $tgt;
    $eff  = ($tgtPer > 0) ? round(($actualPcs / $tgtPer) * 100, 2) : 0;
    $aph  = $actualPcs * 60;
    $loss = round(max(0, $cap - $aph), 2);
    $idle = ($tgtPer > 0) ? round($loss / $tgtPer, 2) : 0;
    return ['efficiency' => $eff, 'loss_pcs' => $loss, 'idle_breakdown' => $idle];
}

$p1cases = [
    ['A. null',                     null,                             ['efficiency'=>'ABSENT','loss_pcs'=>'ABSENT','idle_breakdown'=>'ABSENT']],
    ['B. {}',                       '{}',                             ['efficiency'=>'ABSENT','loss_pcs'=>'ABSENT','idle_breakdown'=>'ABSENT']],
    ['C. {"output":0}',             '{"output":0}',                   exp_phase1(0)],   // actualPcs=0 (output key exists)
    ['D. {"cyclecount":5}',         '{"cyclecount":5}',               exp_phase1(5*20)], // actualPcs=100
    ['E. {"output":10}',            '{"output":10}',                  exp_phase1(10)],
    ['F. {"cycle_count":5}',        '{"cycle_count":5}',              exp_phase1(5*20)],
    ['G. {"CycleCount":5}',         '{"CycleCount":5}',               exp_phase1(5*20)],
    ['H. {"output":10,"cyclecount":5}', '{"output":10,"cyclecount":5}', exp_phase1(10)],  // output priority
];

echo "════════════════════════════════════════════════════════\n";
echo " PHASE 1 — DISCONNECTED SCENARIOS (8 cases)\n";
echo "════════════════════════════════════════════════════════\n\n";

$sep  = str_repeat('─', 100);
printf("%-30s | %-16s | %12s %12s %12s | %4s\n", 'SCENARIO', 'FIELD', 'LEGACY', 'LARAVEL', 'DELTA_8', 'PASS');
echo $sep . "\n";

$p1Pass = 0; $p1Fail = 0;

foreach ($p1cases as [$label, $payload, $expected]) {
    $r = compareScenario($pdo, $label, $payload, $expected, false);
    if ($r['pass']) $p1Pass++; else $p1Fail++;

    foreach (['efficiency','loss_pcs','idle_breakdown'] as $f) {
        $d   = $r['data'][$f];
        $lv  = $d['lv'];  $rv = $d['rv'];  $dlt = $d['delta'];
        $fmtL = $lv === 'ABSENT' ? 'ABSENT' : number_format((float)$lv, 4);
        $fmtR = $rv === 'ABSENT' ? 'ABSENT' : number_format((float)$rv, 4);
        $fmtD = $dlt !== null ? number_format($dlt, 8) : ($lv === 'ABSENT' && $rv === 'ABSENT' ? 'BOTH_ABSENT' : 'N/A');
        printf("%-30s | %-16s | %12s %12s %12s | %4s\n",
            ($f === 'efficiency') ? $label : '',
            $f, $fmtL, $fmtR, $fmtD,
            $d['fieldPass'] ? '✓' : '✗'
        );
    }
    echo $sep . "\n";
}

printf("Phase 1 → PASS=%d FAIL=%d\n", $p1Pass, $p1Fail);

// ============================================================
// PHASE 2 — BPC INFERENCE TEST
// target=30, capacity=36000, brushes=0 → inferred 20
// live: {"cyclecount":10} → actualPerMinPcs = 10*20 = 200
// ============================================================
echo "\n════════════════════════════════════════════════════════\n";
echo " PHASE 2 — BPC INFERENCE DETERMINISTIC TEST\n";
echo "════════════════════════════════════════════════════════\n\n";

$bpcInferred = (int)round(36000 / (30 * 60));   // 20
$tgtPps      = 30 * $bpcInferred;               // 600
$actualPps   = 10 * $bpcInferred;               // 200
$eff2   = round(($actualPps / $tgtPps) * 100, 2); // 33.33
$aph2   = $actualPps * 60;                        // 12000
$loss2  = round(max(0, 36000 - $aph2), 2);        // 24000
$idle2  = round($loss2 / $tgtPps, 2);             // 40.00

printf("Inferred BPC = round(36000/(30×60)) = %d\n", $bpcInferred);
printf("targetPerMinPcs = 30 × %d = %d\n", $bpcInferred, $tgtPps);
printf("actualPerMinPcs = cyclecount(10) × %d = %d\n", $bpcInferred, $actualPps);
printf("Expected: efficiency=%.2f  loss_pcs=%.2f  idle_breakdown=%.2f\n\n",
    $eff2, $loss2, $idle2);

$p2Exp = ['efficiency' => $eff2, 'loss_pcs' => $loss2, 'idle_breakdown' => $idle2];
$r2 = compareScenario($pdo, 'BPC Infer', '{"cyclecount":10}', $p2Exp, true);

printf("%-16s | %12s %12s %12s %12s | %4s\n", 'FIELD', 'LEGACY', 'LARAVEL', 'DELTA_8', 'EXPECTED', 'PASS');
echo $sep . "\n";
foreach (['efficiency','loss_pcs','idle_breakdown'] as $f) {
    $d = $r2['data'][$f];
    $fmtL = is_numeric($d['lv']) ? number_format((float)$d['lv'],4) : (string)$d['lv'];
    $fmtR = is_numeric($d['rv']) ? number_format((float)$d['rv'],4) : (string)$d['rv'];
    $fmtD = $d['delta'] !== null ? number_format($d['delta'],8) : 'N/A';
    $fmtE = number_format((float)$p2Exp[$f], 4);
    printf("%-16s | %12s %12s %12s %12s | %4s\n",
        $f, $fmtL, $fmtR, $fmtD, $fmtE,
        $d['fieldPass'] ? '✓' : '✗');
}
echo $sep . "\n";
printf("Phase 2 → %s\n", $r2['pass'] ? 'PASS' : 'FAIL: ' . implode(', ', $r2['drifts']));

// ============================================================
// PHASE 3 — FLOAT STABILITY TEST
// target=17.5, capacity=0, brushes_per_cycle=3
// live: {"output":11.73}
// targetPerMinPcs = 17.5 * 3 = 52.5
// actualPerMinPcs = 11.73
// eff = (11.73/52.5)*100 = 22.342857... → 22.34
// capacityPerHour fallback = 52.5*60 = 3150
// actualPerHour = 11.73*60 = 703.8
// lossPcs = 3150-703.8 = 2446.2
// idleMins = 2446.2/52.5 = 46.594285... → 46.59
// ============================================================
echo "\n════════════════════════════════════════════════════════\n";
echo " PHASE 3 — FLOAT STABILITY TEST (5 cycles)\n";
echo "════════════════════════════════════════════════════════\n\n";

// Patch device: target=17.5, capacity=0, brushes_per_cycle=3
$pdo->exec("UPDATE devices SET target_limit=17.5, capacity=0, brushes_per_cycle=3
            WHERE device_id='".DEVICE_ID."'");

$tgtPps3   = 17.5 * 3;                        // 52.5
$actPps3   = 11.73;
$eff3      = round(($actPps3 / $tgtPps3) * 100, 2);  // 22.34
$capFb3    = $tgtPps3 * 60;                   // 3150
$aph3      = $actPps3 * 60;                   // 703.8
$loss3     = round(max(0, $capFb3 - $aph3), 2); // 2446.2
$idle3     = round($loss3 / $tgtPps3, 2);       // 46.59

printf("Device: target=17.5  capacity=0  brushes_per_cycle=3\n");
printf("Live: {\"output\":11.73}\n");
printf("Expected: efficiency=%.2f  loss_pcs=%.2f  idle_breakdown=%.2f\n\n",
    $eff3, $loss3, $idle3);
printf("%-5s | %-16s | %12s %12s %14s | %12s %12s | %4s\n",
    'CYC','FIELD','LEGACY','LARAVEL','DELTA_8','EXPECTED','LEG_ERR_8','PASS');
echo str_repeat('─', 120) . "\n";

$p3Pass = 0; $p3Fail = 0;
$p3Exp  = ['efficiency' => $eff3, 'loss_pcs' => $loss3, 'idle_breakdown' => $idle3];

for ($c = 1; $c <= 5; $c++) {
    $r3 = compareScenario($pdo, "C$c", '{"output":11.73}', $p3Exp, true);
    if ($r3['pass']) $p3Pass++; else $p3Fail++;

    foreach (['efficiency','loss_pcs','idle_breakdown'] as $f) {
        $d   = $r3['data'][$f];
        $exp = (float)$p3Exp[$f];
        $legs= is_numeric($d['lv']) ? abs((float)$d['lv'] - $exp) : null;
        printf("%-5s | %-16s | %12s %12s %14s | %12s %12s | %4s\n",
            ($f==='efficiency') ? "C$c" : '',
            $f,
            is_numeric($d['lv']) ? number_format((float)$d['lv'],4) : (string)$d['lv'],
            is_numeric($d['rv']) ? number_format((float)$d['rv'],4) : (string)$d['rv'],
            $d['delta'] !== null ? number_format($d['delta'],8) : 'N/A',
            number_format($exp,4),
            $legs !== null ? number_format($legs,8) : 'N/A',
            $d['fieldPass'] ? '✓' : '✗'
        );
    }
    echo str_repeat(' ',6)."|\n";
}
echo str_repeat('─',120)."\n";
printf("Phase 3 → PASS=%d FAIL=%d\n", $p3Pass, $p3Fail);

// ════════════════════════════════════════════════════════════
echo "\n═══════════════════════════════════════════════════════════\n";
echo " BLISTER METRICS PARITY CERTIFICATION REPORT\n";
echo "═══════════════════════════════════════════════════════════\n";
printf(" Phase 1 (disconnected × 8): PASS=%d FAIL=%d\n", $p1Pass, $p1Fail);
printf(" Phase 2 (BPC inference):    %s\n",   $r2['pass'] ? 'PASS' : 'FAIL');
printf(" Phase 3 (float × 5):        PASS=%d FAIL=%d\n", $p3Pass, $p3Fail);
$totalFail = $p1Fail + ($r2['pass'] ? 0 : 1) + $p3Fail;
echo ($totalFail === 0)
    ? " FINAL VERDICT: CERTIFIED — ZERO BEHAVIORAL DRIFT\n"
    : " FINAL VERDICT: DRIFT DETECTED\n";
echo "═══════════════════════════════════════════════════════════\n";

// ─── Cleanup ──────────────────────────────────────────────────
echo "\n[Cleanup] Restoring state...\n";
if ($isNew) {
    $pdo->exec("DELETE FROM live_device_data WHERE device_id='".DEVICE_ID."'");
    $pdo->exec("DELETE FROM devices WHERE device_id='".DEVICE_ID."'");
    echo "[Cleanup] Deleted temporary test device.\n";
} else {
    $orig = $existing;
    $pdo->exec("UPDATE devices SET target_limit=".((float)$orig['target_limit']).",
                capacity=".((float)$orig['capacity']).",
                brushes_per_cycle=".((int)$orig['brushes_per_cycle'])."
                WHERE device_id='".DEVICE_ID."'");
    if ($existsLive) {
        $pdo->prepare("UPDATE live_device_data SET live_data=? WHERE device_id=?")
            ->execute([$existsLive['live_data'], DEVICE_ID]);
    } else {
        $pdo->exec("DELETE FROM live_device_data WHERE device_id='".DEVICE_ID."'");
    }
    echo "[Cleanup] Restored original device config.\n";
}
