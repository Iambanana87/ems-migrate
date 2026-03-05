<?php
/**
 * STRICT METRIC RUNTIME PARITY TEST — MOLD PROCESS
 * Tests: legacy api.php?action=search_device vs Laravel api/gateway?c=Device&m=machineDetails
 *
 * Compares per-device: efficiency, loss_pcs / total_lost_pcs, idle_breakdown / lost_time
 */

declare(strict_types=1);

// ── Config ──────────────────────────────────────────────────────────────────
const LEGACY_BASE  = 'http://localhost:8080/ems';
const LARAVEL_BASE = 'http://localhost:8000';
const TIMEOUT      = 10;

// ── HTTP helper ──────────────────────────────────────────────────────────────
function request(string $url, string $method = 'GET', array $body = []): array
{
    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, TIMEOUT);
    curl_setopt($ch, CURLOPT_HEADER, true);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    }

    $raw      = curl_exec($ch);
    $code     = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $hdrSize  = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    $err      = curl_error($ch);
    curl_close($ch);

    $body_raw     = substr($raw, $hdrSize);
    $headers_raw  = substr($raw, 0, $hdrSize);

    // Detect Content-Type
    $ct = '';
    foreach (explode("\n", $headers_raw) as $line) {
        if (stripos($line, 'content-type:') === 0) {
            $ct = trim(substr($line, strlen('content-type:')));
        }
    }

    return [
        'code'  => $code,
        'body'  => $body_raw,
        'ct'    => $ct,
        'json'  => json_decode($body_raw, true),
        'err'   => $err,
    ];
}

// ── Fetch both endpoints with process=mold ──────────────────────────────────
echo "Fetching legacy  search_device (mold)...\n";
$legResp  = request(LEGACY_BASE . '/api.php?view=mold');

echo "Fetching Laravel machineDetails (mold)...\n";
$larResp  = request(LARAVEL_BASE . '/api/gateway?c=Device&m=machineDetails&process=mold');

// ── Decode & index by device_id ─────────────────────────────────────────────
$legDevices = [];
// Legacy returns {"devices":[...], "newTimestamp":"..."}
$legArr = $legResp['json']['devices'] ?? ($legResp['json'] ?? []);
if (is_array($legArr)) {
    foreach ($legArr as $d) {
        $id = $d['device_id'] ?? null;
        if ($id) $legDevices[$id] = $d;
    }
}

$larDevices = [];
if (isset($larResp['json']) && is_array($larResp['json'])) {
    // Laravel machineDetails returns a flat array of DTOs
    $arr = isset($larResp['json']['data']) ? $larResp['json']['data'] : $larResp['json'];
    // If it's associative (single device), wrap it
    if (!isset($arr[0])) $arr = array_values($arr);
    foreach ($arr as $d) {
        $id = $d['mold_id'] ?? ($d['device_id'] ?? null); // Laravel DTO uses mold_id
        if ($id) $larDevices[$id] = $d;
    }
}

// Helper: extract the three target metrics from a device row
function extractMetrics(array $d, string $side): array
{
    if ($side === 'legacy') {
        // In legacy, metrics are merged back into live_data via array_merge
        $live = $d['live_data'] ?? [];
        if (is_string($live)) $live = json_decode($live, true) ?: [];
        // Also check top-level in case they're hoisted
        $eff = $live['efficiency']     ?? $d['efficiency']     ?? null;
        $lp  = $live['loss_pcs']       ?? $d['loss_pcs']       ?? null;
        $ib  = $live['idle_breakdown'] ?? $d['idle_breakdown'] ?? null;
        return [
            'efficiency'     => isset($eff) ? (float)$eff : null,
            'loss_pcs'       => isset($lp)  ? (float)$lp  : null,
            'idle_breakdown' => isset($ib)  ? (float)$ib  : null,
        ];
    } else {
        // Laravel returns a clean DTO
        return [
            'efficiency'     => isset($d['efficiency'])      ? (float)$d['efficiency']      : null,
            'loss_pcs'       => isset($d['total_lost_pcs'])  ? (float)$d['total_lost_pcs']  : null,
            'idle_breakdown' => isset($d['lost_time'])        ? (float)$d['lost_time']        : null,
        ];
    }
}

// ── Detect value JSON type (number vs string) ────────────────────────────────
function jsonTypeOf(array $d, string $key): string
{
    if (!array_key_exists($key, $d)) return 'absent';
    $raw = $d[$key];
    if (is_int($raw))    return 'int';
    if (is_float($raw))  return 'float';
    if (is_string($raw)) return 'string';
    if (is_null($raw))   return 'null';
    return gettype($raw);
}

// ── Determine union of device IDs ──────────────────────────────────────────
$allIds = array_unique(array_merge(array_keys($legDevices), array_keys($larDevices)));

$pass = 0;
$fail = 0;
$rows = [];   // row data for output

foreach ($allIds as $id) {
    $legD = $legDevices[$id] ?? null;
    $larD = $larDevices[$id] ?? null;

    if ($legD === null) {
        $rows[] = ['id' => $id, 'issue' => 'Missing in LEGACY', 'verdict' => 'FAIL'];
        $fail++;
        continue;
    }
    if ($larD === null) {
        $rows[] = ['id' => $id, 'issue' => 'Missing in LARAVEL', 'verdict' => 'FAIL'];
        $fail++;
        continue;
    }

    $legM = extractMetrics($legD, 'legacy');
    $larM = extractMetrics($larD, 'laravel');

    $fields = ['efficiency', 'loss_pcs', 'idle_breakdown'];
    $drifts = [];

    foreach ($fields as $f) {
        $lv = $legM[$f];
        $rv = $larM[$f];

        $absent = ($lv === null || $rv === null);
        $delta  = $absent ? null : abs($lv - $rv);

        // Type comparison - examine the raw arrays
        $legLive = $legD['live_data'] ?? [];
        if (is_string($legLive)) $legLive = json_decode($legLive, true) ?: [];
        $legRaw = $legLive;

        // Laravel DTO key mapping
        $larKeyMap = ['efficiency' => 'efficiency', 'loss_pcs' => 'total_lost_pcs', 'idle_breakdown' => 'lost_time'];
        $legKey    = $f;
        $larKey    = $larKeyMap[$f];

        $legType = jsonTypeOf($legRaw, $legKey);
        $larType = jsonTypeOf($larD,   $larKey);

        $numericMatch = $absent ? null : ($delta < 0.001);

        if (!$numericMatch || $legType !== $larType) {
            $drifts[] = sprintf(
                '%s: leg=%s(%s) lar=%s(%s) delta=%s',
                $f,
                $lv !== null ? round($lv, 4) : 'NULL',
                $legType,
                $rv !== null ? round($rv, 4) : 'NULL',
                $larType,
                $delta !== null ? round($delta, 6) : 'N/A'
            );
        }
    }

    if (empty($drifts)) {
        $rows[] = [
            'id'      => $id,
            'eff_leg' => $legM['efficiency'],
            'eff_lar' => $larM['efficiency'],
            'lp_leg'  => $legM['loss_pcs'],
            'lp_lar'  => $larM['loss_pcs'],
            'ib_leg'  => $legM['idle_breakdown'],
            'ib_lar'  => $larM['idle_breakdown'],
            'verdict' => 'PASS',
        ];
        $pass++;
    } else {
        $rows[] = [
            'id'      => $id,
            'eff_leg' => $legM['efficiency'],
            'eff_lar' => $larM['efficiency'],
            'lp_leg'  => $legM['loss_pcs'],
            'lp_lar'  => $larM['loss_pcs'],
            'ib_leg'  => $legM['idle_breakdown'],
            'ib_lar'  => $larM['idle_breakdown'],
            'drifts'  => implode(' | ', $drifts),
            'verdict' => 'FAIL',
        ];
        $fail++;
    }
}

// ── Print Table ──────────────────────────────────────────────────────────────
$line = str_repeat('─', 130);
echo "\n" . $line . "\n";
printf("%-20s  %8s  %8s  %8s  %8s  %8s  %8s  %-8s\n",
    'DEVICE_ID', 'EFF_LEG', 'EFF_LAR', 'LP_LEG', 'LP_LAR', 'IB_LEG', 'IB_LAR', 'VERDICT');
echo $line . "\n";

foreach ($rows as $r) {
    if (isset($r['issue'])) {
        printf("%-20s  %-88s  %-8s\n", $r['id'], $r['issue'], $r['verdict']);
    } else {
        printf("%-20s  %8s  %8s  %8s  %8s  %8s  %8s  %-8s\n",
            $r['id'],
            $r['eff_leg'] !== null ? round($r['eff_leg'], 2) : 'NULL',
            $r['eff_lar'] !== null ? round($r['eff_lar'], 2) : 'NULL',
            $r['lp_leg']  !== null ? round($r['lp_leg'], 2)  : 'NULL',
            $r['lp_lar']  !== null ? round($r['lp_lar'], 2)  : 'NULL',
            $r['ib_leg']  !== null ? round($r['ib_leg'], 2)  : 'NULL',
            $r['ib_lar']  !== null ? round($r['ib_lar'], 2)  : 'NULL',
            $r['verdict']
        );
        if (!empty($r['drifts'])) {
            echo "  ↳ DRIFT: " . $r['drifts'] . "\n";
        }
    }
}
echo $line . "\n";

// ── Summary ──────────────────────────────────────────────────────────────────
echo "\n=====================================\n";
echo " MOLD METRICS PARITY REPORT\n";
echo "=====================================\n";
echo " Legacy  HTTP: " . $legResp['code'] . " | CT: " . $legResp['ct'] . "\n";
echo " Laravel HTTP: " . $larResp['code'] . " | CT: " . $larResp['ct'] . "\n";
echo " Total devices (legacy)  : " . count($legDevices) . "\n";
echo " Total devices (laravel) : " . count($larDevices) . "\n";
echo " PASS : $pass\n";
echo " FAIL : $fail\n";

if ($fail === 0 && $pass > 0) {
    echo " VERDICT: PARITY CONFIRMED — ZERO STRUCTURAL DRIFT\n";
} elseif ($pass === 0 && $fail === 0) {
    echo " VERDICT: NO DEVICES FOUND — CHECK ENDPOINTS\n";
} else {
    echo " VERDICT: PARITY FAILED — DRIFT DETECTED\n";
    // Save full payloads for debugging
    file_put_contents('mold_leg_raw.json',  json_encode($legResp['json'],  JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    file_put_contents('mold_lar_raw.json',  json_encode($larResp['json'],  JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
    echo " Raw responses saved to mold_leg_raw.json / mold_lar_raw.json\n";
}
echo "=====================================\n";
