<?php
/**
 * STRICT CONNECTED PATH PARITY TEST — MOLD
 *
 * 1. Injects synthetic connected live_data into MOLD-01 (live_device_data table)
 * 2. Calls both endpoints 5 times
 * 3. Extracts efficiency, loss_pcs/total_lost_pcs, idle_breakdown/lost_time
 * 4. Computes per-field numeric delta & JSON type
 * 5. Restores original live_data on exit
 */
declare(strict_types=1);

// ── DB Config ────────────────────────────────────────────────────────────────
$dsn  = 'mysql:host=localhost;port=3306;dbname=production;charset=utf8mb4';
$user = 'device';
$pass = 'BtyLX96qZ4nDL!0w';

// ── Synthetic live payload ───────────────────────────────────────────────────
// Chosen to produce non-trivial, non-zero metric values
// device MOLD-01: target_limit=0 in DB as seen from raw output.
// We must ALSO update devices.target_limit for the math to produce real numbers.
// We'll patch devices.target_limit and devices.cavities temporarily too.
const DEVICE_ID   = 'MOLD-01';
const CYCLE_TIME  = 12.5;   // seconds / shot (current actual)
const TARGET_SEC  = 10.0;   // seconds / shot (target — faster than actual)
const MOLD_CAV    = 4;      // theoretical cavity count
const ACT_CAV     = 3;      // actual cavity count (from live)

// Pre-compute expected values (they must match both endpoints after the fix)
$targetCapacity = (3600 / TARGET_SEC) * MOLD_CAV;   // 1440 pcs/hr
$actualCapacity = (3600 / CYCLE_TIME) * ACT_CAV;    // 864 pcs/hr
$expectedEff    = round(($actualCapacity / $targetCapacity) * 100, 2); // 60.00
$expectedLoss   = round(max(0, $targetCapacity - $actualCapacity), 2); // 576.00
$targetPerMin   = (60 / TARGET_SEC) * MOLD_CAV;                        // 24 pcs/min
$expectedIdle   = round($expectedLoss / $targetPerMin, 2);             // 24.00

// Synthetic live JSON — datetime must be within device frequency of NOW
$livePayload = json_encode([
    'datetime'   => date('Y-m-d H:i:s'),  // fresh timestamp = connected
    'cycle_time' => CYCLE_TIME,
    'cavities'   => ACT_CAV,
    'output'     => 0,
    'cyclecount' => 0,
], JSON_UNESCAPED_UNICODE);

// ── Endpoints ─────────────────────────────────────────────────────────────────
const LEGACY_URL  = 'http://localhost:8080/ems/api.php?view=mold';
const LARAVEL_URL = 'http://localhost:8000/api/gateway?c=Device&m=machineDetails&process=mold';

// ── HTTP helper ───────────────────────────────────────────────────────────────
function fetch(string $url): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    return ['code' => $code, 'json' => json_decode($body ?: 'null', true)];
}

// ── Extract metrics ───────────────────────────────────────────────────────────
function extractLegacyMetrics(array $response, string $id): ?array
{
    $devices = $response['json']['devices'] ?? $response['json'] ?? [];
    if (!is_array($devices)) return null;
    foreach ($devices as $d) {
        if (($d['device_id'] ?? '') !== $id) continue;
        $live = $d['live_data'] ?? [];
        if (is_string($live)) $live = json_decode($live, true) ?: [];
        // Metrics are merged into live_data by legacy
        return [
            'efficiency'     => array_key_exists('efficiency',     $live) ? $live['efficiency']     : 'ABSENT',
            'loss_pcs'       => array_key_exists('loss_pcs',       $live) ? $live['loss_pcs']       : 'ABSENT',
            'idle_breakdown' => array_key_exists('idle_breakdown', $live) ? $live['idle_breakdown'] : 'ABSENT',
            '_types'         => [
                'efficiency'     => gettype($live['efficiency']     ?? null),
                'loss_pcs'       => gettype($live['loss_pcs']       ?? null),
                'idle_breakdown' => gettype($live['idle_breakdown'] ?? null),
            ],
        ];
    }
    return null;
}

function extractLaravelMetrics(array $response, string $id): ?array
{
    $arr = $response['json'] ?? [];
    if (!is_array($arr)) return null;
    // flat array of DTOs
    if (!isset($arr[0])) $arr = array_values($arr);
    foreach ($arr as $d) {
        if (($d['mold_id'] ?? $d['device_id'] ?? '') !== $id) continue;
        return [
            'efficiency'     => array_key_exists('efficiency',     $d) ? $d['efficiency']     : 'ABSENT',
            'loss_pcs'       => array_key_exists('total_lost_pcs', $d) ? $d['total_lost_pcs'] : 'ABSENT',
            'idle_breakdown' => array_key_exists('lost_time',      $d) ? $d['lost_time']      : 'ABSENT',
            '_types'         => [
                'efficiency'     => gettype($d['efficiency']     ?? null),
                'loss_pcs'       => gettype($d['total_lost_pcs'] ?? null),
                'idle_breakdown' => gettype($d['lost_time']      ?? null),
            ],
        ];
    }
    return null;
}

// ── Connect to DB ─────────────────────────────────────────────────────────────
try {
    $pdo = new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4",
    ]);
} catch (PDOException $e) {
    echo "DB connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// ── Backup original device config ─────────────────────────────────────────────
$origDevice = $pdo->query(
    "SELECT target_limit, cavities FROM devices WHERE device_id = '" . DEVICE_ID . "' LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);

$origLive = $pdo->query(
    "SELECT live_data FROM live_device_data WHERE device_id = '" . DEVICE_ID . "' LIMIT 1"
)->fetch(PDO::FETCH_ASSOC);
$liveExists = ($origLive !== false);

echo "Injecting synthetic live data into " . DEVICE_ID . "...\n";
echo "  target_limit = " . TARGET_SEC . "s  mold_cavity = " . MOLD_CAV . "  cycle_time = " . CYCLE_TIME . "s  actual_cavity = " . ACT_CAV . "\n";
printf("  Expected efficiency=%.2f  loss_pcs=%.2f  idle_breakdown=%.2f\n\n",
    $expectedEff, $expectedLoss, $expectedIdle);

// ── Inject synthetic device config ────────────────────────────────────────────
$pdo->exec("UPDATE devices SET target_limit = " . TARGET_SEC . ", cavities = " . MOLD_CAV . "
             WHERE device_id = '" . DEVICE_ID . "'");

if ($liveExists) {
    $stmt = $pdo->prepare("UPDATE live_device_data SET live_data = ?, last_updated = NOW() WHERE device_id = ?");
    $stmt->execute([$livePayload, DEVICE_ID]);
} else {
    $stmt = $pdo->prepare("INSERT INTO live_device_data (device_id, live_data, last_updated) VALUES (?, ?, NOW())");
    $stmt->execute([DEVICE_ID, $livePayload]);
}

// ── Run 5 comparison cycles ───────────────────────────────────────────────────
const CYCLES = 5;
$allResults = [];
$lineW = 110;
$sepLine = str_repeat('─', $lineW);

for ($i = 1; $i <= CYCLES; $i++) {
    // Refresh datetime each cycle so device stays connected
    $live = json_decode($livePayload, true);
    $live['datetime'] = date('Y-m-d H:i:s');
    $refreshedPayload = json_encode($live, JSON_UNESCAPED_UNICODE);
    $pdo->prepare("UPDATE live_device_data SET live_data = ? WHERE device_id = ?")
        ->execute([$refreshedPayload, DEVICE_ID]);

    $legResp = fetch(LEGACY_URL);
    $larResp = fetch(LARAVEL_URL);

    $legM = extractLegacyMetrics($legResp, DEVICE_ID);
    $larM = extractLaravelMetrics($larResp, DEVICE_ID);

    $fields   = ['efficiency', 'loss_pcs', 'idle_breakdown'];
    $expected = ['efficiency' => $expectedEff, 'loss_pcs' => $expectedLoss, 'idle_breakdown' => $expectedIdle];

    $cyclePass = true;
    $drifts    = [];

    foreach ($fields as $f) {
        $lv = $legM[$f] ?? 'NULL';
        $rv = $larM[$f] ?? 'NULL';

        $bothNumeric = is_numeric($lv) && is_numeric($rv);
        $delta       = $bothNumeric ? abs((float)$lv - (float)$rv) : null;
        $expDelta    = is_numeric($lv) ? abs((float)$lv - $expected[$f]) : null;

        $typeLeg = $legM['_types'][$f] ?? '?';
        $typeLar = $larM['_types'][$f] ?? '?';

        $fieldPass = $bothNumeric && $delta < 0.001 && $typeLeg === $typeLar;
        if (!$fieldPass) {
            $cyclePass = false;
            $drifts[]  = "$f: leg=$lv($typeLeg) lar=$rv($typeLar) delta=" . ($delta !== null ? round($delta, 6) : 'N/A');
        }

        $allResults[$i][$f] = [
            'leg'      => $lv,
            'lar'      => $rv,
            'delta'    => $delta,
            'expDelta' => $expDelta,
            'typeLeg'  => $typeLeg,
            'typeLar'  => $typeLar,
            'pass'     => $fieldPass,
        ];
    }

    $allResults[$i]['_verdict'] = $cyclePass ? 'PASS' : 'FAIL';
    $allResults[$i]['_drifts']  = $drifts;
    echo "Cycle $i/5 ... " . ($cyclePass ? 'PASS' : 'FAIL') . "\n";
    if (!empty($drifts)) {
        foreach ($drifts as $drift) echo "  ↳ DRIFT: $drift\n";
    }
}

// ── Per-field Delta Table ─────────────────────────────────────────────────────
echo "\n$sepLine\n";
printf("%-5s | %-16s | %10s %10s %10s | %10s %10s %10s | %4s\n",
    'CYCLE', 'FIELD', 'LEGACY', 'LARAVEL', 'DELTA', 'EXP', 'LEG_ERR', 'LAR_ERR', 'PASS');
echo $sepLine . "\n";

foreach ($allResults as $cycle => $data) {
    if (!is_int($cycle)) continue;
    foreach (['efficiency', 'loss_pcs', 'idle_breakdown'] as $f) {
        $r   = $data[$f];
        $exp = ['efficiency' => $expectedEff, 'loss_pcs' => $expectedLoss, 'idle_breakdown' => $expectedIdle][$f];
        $legErr = is_numeric($r['leg']) ? abs((float)$r['leg'] - $exp) : null;
        $larErr = is_numeric($r['lar']) ? abs((float)$r['lar'] - $exp) : null;

        printf("%-5s | %-16s | %10s %10s %10s | %10s %10s %10s | %4s\n",
            "C$cycle",
            $f,
            is_numeric($r['leg']) ? number_format((float)$r['leg'], 4) : $r['leg'],
            is_numeric($r['lar']) ? number_format((float)$r['lar'], 4) : $r['lar'],
            $r['delta'] !== null ? number_format($r['delta'], 6) : 'N/A',
            number_format($exp, 4),
            $legErr !== null ? number_format($legErr, 6) : 'N/A',
            $larErr !== null ? number_format($larErr, 6) : 'N/A',
            $r['pass'] ? '✓' : '✗'
        );
    }
    echo str_repeat(' ', 6) . "|\n";
}
echo $sepLine . "\n";

// ── Summary ───────────────────────────────────────────────────────────────────
$totalPass = 0;
$totalFail = 0;
foreach ($allResults as $cycle => $data) {
    if (!is_int($cycle)) continue;
    if ($data['_verdict'] === 'PASS') $totalPass++;
    else $totalFail++;
}

echo "\n=====================================\n";
echo " CONNECTED PATH MOLD METRICS REPORT\n";
echo "=====================================\n";
printf(" Expected: efficiency=%.2f  loss_pcs=%.2f  idle_breakdown=%.2f\n",
    $expectedEff, $expectedLoss, $expectedIdle);
echo " PASS : $totalPass\n";
echo " FAIL : $totalFail\n";
if ($totalFail === 0 && $totalPass > 0) {
    echo " VERDICT: PARITY CONFIRMED — ZERO ARITHMETIC DRIFT ACROSS 5 CYCLES\n";
} else {
    echo " VERDICT: PARITY FAILED — ARITHMETIC DRIFT DETECTED\n";
}
echo "=====================================\n";

// ── Restore original state ────────────────────────────────────────────────────
echo "\nRestoring original device config...\n";
$pdo->exec("UPDATE devices SET target_limit = " . ((float)($origDevice['target_limit'] ?? 0)) . ",
             cavities = " . ((int)($origDevice['cavities'] ?? 0)) . "
             WHERE device_id = '" . DEVICE_ID . "'");

if ($liveExists) {
    $stmt = $pdo->prepare("UPDATE live_device_data SET live_data = ? WHERE device_id = ?");
    $stmt->execute([$origLive['live_data'], DEVICE_ID]);
} else {
    $pdo->exec("DELETE FROM live_device_data WHERE device_id = '" . DEVICE_ID . "'");
}
echo "Done. DB restored to original state.\n";
