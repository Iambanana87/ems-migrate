<?php
ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

date_default_timezone_set('Asia/Ho_Chi_Minh');

// CHÚ Ý: đường dẫn dưới đây tùy theo cấu trúc thư mục thực tế của bạn
require_once __DIR__ . '/helper/jwt.php';
require_once __DIR__ . '/helper/auth_helper.php';
require_once __DIR__ . '/backend/config.php';
require_once __DIR__ . '/middlewave/middleware_endpoint_logger.php';


// LẤY THÔNG TIN USER CHO API (bắt buộc Authorization header)
$AUTH = auth_user_or_null_ui(); 

try {
  $pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (PDOException $e) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'DB connect error: '.$e->getMessage()]);
  exit;
}

require_once __DIR__ . '/model/audit.php';

$audit = new AuditTrail($pdo, [
  'ignore_fields' => ['created_at','updated_at']
]);

$WHO = (function($AUTH) {
  if (is_array($AUTH)  && !empty($AUTH['username'])) return (string)$AUTH['username'];
  if (is_object($AUTH) && !empty($AUTH->username))   return (string)$AUTH->username;
  return AuditTrail::current_user(); // fallback 'guest' / cookie / REMOTE_USER
})($AUTH);




$PUBLIC_ACTIONS = [
  // ----- Trang chủ/Popup/Board đọc-only:
  'get_families',
  'get_machine_details',
  'get_summary_report',
  'get_output_report',
  'get_output_report_bulk',
  'get_efficiency_report_data',
  'search_device',
  'get_hourly_report',

  'count_flexible',
  'count_actions',
  'count_device_status',
  'actions_board',
  'devices_action_table',

  // Xem action/plan ở modal (xem được không cần đăng nhập)
  'list_device_actions_v2',
  'list_action_plans',

  // Counters/TTL
  'get_total_count',
  'tc_meta',

  // (tuỳ) preview_next_codes: thường để dev/admin → KHÔNG public
];

// Lấy action hiện tại
$action = $_POST['action'] ?? ($_GET['action'] ?? null);

// Quy ước: route “mặc định” (KHÔNG có ?action=…) trả feed devices cho dashboard → public
$isDefaultDashboardFeed = ($action === null);

// Cho phép public nếu action trong danh sách hoặc là dashboard feed
$isPublic = in_array((string)$action, $PUBLIC_ACTIONS, true) || $isDefaultDashboardFeed;




function send_json($data, int $code = 200) {
  while (ob_get_level()) { ob_end_clean(); }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');


  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function send_json_ok($data = null, int $http = 200): void {
  api_send('success', $data, '', $http);
}

function send_json_error($message = 'error', int $http = 400): void {
  $msg = is_string($message) ? $message : json_encode($message, JSON_UNESCAPED_UNICODE);
  api_send('error', null, $msg, $http);
}

// đổi PHP warning/notice → exception để bắt được trong try/catch
set_error_handler(function($sev,$msg,$file,$line){
  throw new ErrorException($msg, 0, $sev, $file, $line);
});


    
// --- Helpers chung cho efficiency “ratio-of-sums” ---
function pct($x){ return is_finite($x) ? $x*100 : 0; }

function api_send(string $status, $data = null, string $message = '', int $http = 200): void {
  while (ob_get_level()) { ob_end_clean(); }
  http_response_code($http);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'status'  => $status,          // "success" | "error"
    'data'    => $data,            // object | array | null
    'message' => $message          // mô tả ngắn gọn
  ], JSON_UNESCAPED_UNICODE);
  exit;
}


/**
 * Safe permission checker to avoid calling undefined has_permission().
 * - Tries to obtain current user via require_auth() if available;
 * - Grants permission to admin/manager roles or when user contains a permissions array matching $perm.
 * - Returns false on any error or when no matching permission found.
 */
function has_permission(string $perm): bool {
  try {
    if (!function_exists('require_auth')) return false;
    $u = require_auth();
    if (!is_array($u)) return false;
    $role = strtolower($u['role'] ?? '');
    if ($role === 'admin' || $role === 'manager') return true;
    if (!empty($u['permissions']) && is_array($u['permissions'])) {
      return in_array($perm, $u['permissions'], true);
    }
    return false;
  } catch (Throwable $e) {
    // If require_auth throws or anything else fails, treat as no permission.
    return false;
  }
}


function getTotalCountFor(string $eid, PDO $pdo): array {
    // Lấy history_count và display_type để xác định đơn vị mặc định
    $q0 = $pdo->prepare("
        SELECT COALESCE(history_count,0) AS history_count, LOWER(display_type) AS display_type
        FROM devices
        WHERE device_id = :eid
        LIMIT 1
    ");
    $q0->execute([':eid' => $eid]);
    $meta = $q0->fetch(PDO::FETCH_ASSOC) ?: ['history_count' => 0, 'display_type' => null];

    $history = (int)$meta['history_count'];
    $type    = (string)$meta['display_type'];

    // Đơn vị mặc định theo loại thiết bị (khi base = 0)
    $unit = ($type === 'mold') ? 'shot' : 'pcs';

    // Base theo dữ liệu thực tế (giống logic cũ)
    $base = 0;

    // Mold: đếm số record có cycle_time >= 20
    $q1 = $pdo->prepare("
        SELECT COUNT(*)
        FROM mold
        WHERE mold_id = :eid
          AND cycle_time >= 20
    ");
    $q1->execute([':eid' => $eid]);
    $mold = (int)($q1->fetchColumn() ?: 0);
    if ($mold > 0) {
        $base = $mold;
        $unit = 'shot';
    } else {
        // Tuft
        $q2 = $pdo->prepare("SELECT COALESCE(SUM(output),0) FROM tuft WHERE device_id = :eid");
        $q2->execute([':eid' => $eid]);
        $tuft = (int)($q2->fetchColumn() ?: 0);
        if ($tuft > 0) {
            $base = $tuft;
            $unit = 'pcs';
        } else {
            // Blister
            $q3 = $pdo->prepare("SELECT COALESCE(SUM(output),0) FROM blister WHERE device_id = :eid");
            $q3->execute([':eid' => $eid]);
            $base = (int)($q3->fetchColumn() ?: 0);
            $unit = 'pcs';
        }
    }

    // Cộng thêm history_count
    $total = $base + $history;

    return [$total, $unit];
}



function calc_efficiency_summary_ratio_of_sums(array $rows, float $hours_day = 12.0, float $hours_night = 12.0): array {
    $sumCap = 0.0;
    foreach ($rows as $r) {
        $sumCap += (float)($r['capacity_per_hour'] ?? 0);
    }
    $den_day   = $sumCap * $hours_day;   // mẫu số ca ngày
    $den_night = $sumCap * $hours_night; // mẫu số ca đêm

    $sumDayOut   = 0.0;
    $sumNightOut = 0.0;
    foreach ($rows as $r){
        $sumDayOut   += (float)($r['day_output']   ?? 0);
        $sumNightOut += (float)($r['night_output'] ?? 0);
    }
    $sumTotalOut = $sumDayOut + $sumNightOut;
    $den_total   = $den_day + $den_night; // 24h

    return [
        'day'     => $den_day   > 0 ? pct($sumDayOut   / $den_day)   : 0,
        'night'   => $den_night > 0 ? pct($sumNightOut / $den_night) : 0,
        'average' => $den_total > 0 ? pct($sumTotalOut / $den_total) : 0,
        // tiện trả kèm để frontend dùng:
        '_sum'    => [
            'capacity_per_hour' => $sumCap,
            'day_output'        => $sumDayOut,
            'night_output'      => $sumNightOut,
            'total_output'      => $sumTotalOut,
            'den_day'           => $den_day,
            'den_night'         => $den_night,
            'den_total'         => $den_total,
        ],
    ];
}


    

    function calculateMoldMetrics(array $live, array $device) {
        // Đầu vào cần: target (sec/shot), mold_cavity, current_cycle (sec/shot), actual_cavity
        $targetSec     = (float)($device['target'] ?? $device['target_limit'] ?? 0); // giây/shot
        $moldCavity    = (int)  ($device['mold_cavity'] ?? $device['cavities'] ?? 0);
        $currentSec    = (float)($live['cycle_time'] ?? $device['current_cycle'] ?? 0);
        $actualCavity  = (int)  ($live['cavities'] ?? $device['actual_cavity'] ?? $moldCavity);

        // Năng lực lý thuyết và thực tế (pcs/giờ)
        $capacityPerHour = ($targetSec > 0 && $moldCavity > 0) ? (3600 / $targetSec) * $moldCavity : 0;
        $actualPerHour   = ($currentSec > 0 && $actualCavity > 0) ? (3600 / $currentSec) * $actualCavity : 0;

        // Hiệu suất (%)
        $efficiency = ($capacityPerHour > 0) ? ($actualPerHour / $capacityPerHour) * 100 : 0;

        // Mất sản lượng (pcs) — không âm
        $lossPcs = max(0, $capacityPerHour - $actualPerHour);

        // Quy đổi "lost time" (phút) theo tốc độ target
        $targetPerMin = ($targetSec > 0 && $moldCavity > 0) ? (60 / $targetSec) * $moldCavity : 0;
        $idleMins     = ($targetPerMin > 0) ? ($lossPcs / $targetPerMin) : 0;

        return [
            'efficiency'     => round($efficiency, 2),
            'loss_pcs'       => round($lossPcs, 2),
            'idle_breakdown' => round($idleMins, 2),
        ];
    }
    function calculateTuftMetrics(array $live, array $device) {
        // target_per_min: cấu hình pcs/phút (devices.target_limit)
        $targetPerMin = (float)($device['target'] ?? $device['target_limit'] ?? 0); // ví dụ 20
        // actual_per_min: live output pcs/phút
        $actualPerMin = (float)($live['output'] ?? 0);

        // Efficiency (%)
        $eff = ($targetPerMin > 0) ? ($actualPerMin / $targetPerMin) * 100 : 0;

        // Công suất/giờ
        $capacityPerHour = (float)($device['capacity_per_hr'] ?? 0);
        if ($capacityPerHour <= 0 && $targetPerMin > 0) {
            $capacityPerHour = $targetPerMin * 60;
        }
        $actualPerHour = $actualPerMin * 60;

        // Mất pcs & thời gian quy đổi (phút)
        $lossPcs   = max(0, $capacityPerHour - $actualPerHour);
        $idleMins  = ($targetPerMin > 0) ? ($lossPcs / $targetPerMin) : 0;

        return [
            'efficiency'     => round($eff, 2),
            'loss_pcs'       => round($lossPcs, 2),
            'idle_breakdown' => round($idleMins, 2),
        ];
    }

    function calculateBlisterMetrics(array $live, array $device) {
        // 1) Tham số từ cấu hình
        $targetCyclesPerMin = (float)($device['target'] ?? $device['target_limit'] ?? 0); // cycles/phút
        $brushesPerCycle = (int)($device['brushes_per_cycle']
                        ?? $live['BrushesperCycle']
                        ?? $live['BrushesPerCycle']
                        ?? $live['brushes_per_cycle']
                        ?? 0);

        // Nếu không có brushesPerCycle nhưng có capacity và target, suy ra bpc ~ capacity/(target*60)
        if ($brushesPerCycle <= 0 && $targetCyclesPerMin > 0 && !empty($device['capacity_per_hr'])) {
            $brushesPerCycle = (int)round($device['capacity_per_hr'] / ($targetCyclesPerMin * 60));
        }
        if ($brushesPerCycle <= 0) $brushesPerCycle = 1; // tránh chia 0

        // 2) Mục tiêu & thực tế theo pcs/phút
        $targetPerMinPcs = $targetCyclesPerMin * $brushesPerCycle;             // pcs/phút

        // actual pcs/phút: ưu tiên live 'output', nếu không có -> cyclecount * brushes_per_cycle (chấp nhận key khác nhau)
        $cycleCount = (float)($live['cyclecount'] ?? $live['cycle_count'] ?? $live['CycleCount'] ?? 0);
        $actualPerMinPcs = isset($live['output'])
            ? (float)$live['output']
            : $cycleCount * $brushesPerCycle;

        // 3) Efficiency
        $efficiency = ($targetPerMinPcs > 0) ? ($actualPerMinPcs / $targetPerMinPcs) * 100 : 0;

        // 4) Mất sản lượng/giờ
        $capacityPerHour = (float)($device['capacity_per_hr'] ?? 0);
        if ($capacityPerHour <= 0 && $targetPerMinPcs > 0) {
            $capacityPerHour = $targetPerMinPcs * 60;
        }
        $actualPerHour = $actualPerMinPcs * 60;
        $lossPcs = max(0, $capacityPerHour - $actualPerHour);

        // 5) Lost time (phút) — CHIA CHO pcs/phút (đúng), không phải cycles/phút
        $idleMins = ($targetPerMinPcs > 0) ? ($lossPcs / $targetPerMinPcs) : 0;

        return [
            'efficiency'     => round($efficiency, 2),
            'loss_pcs'       => round($lossPcs, 2),
            'idle_breakdown' => round($idleMins, 2),
        ];
    }


    function sendToDiscord($device_type, $title, $message, $color, $fields = []) {
        if (!defined('DISCORD_WEBHOOK_URLS') || !is_array(DISCORD_WEBHOOK_URLS)) {
            error_log("FATAL: DISCORD_WEBHOOK_URLS is not configured correctly in config.php.");
            return;
        }
        $webhookUrl = DISCORD_WEBHOOK_URLS[$device_type] ?? DISCORD_WEBHOOK_URLS['default'] ?? null;
        if (empty($webhookUrl)) {
            error_log("Error: Discord webhook URL not found for device type '{$device_type}' or for default.");
            return;
        }
        $embed = [
            "title" => $title,
            "description" => $message,
            "color" => hexdec($color),
            "fields" => $fields,
        ];
        $payload = json_encode(["username" => "Machine Monitoring Bot", "embeds" => [$embed]], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $ch = curl_init($webhookUrl);
        curl_setopt_array($ch, [
            CURLOPT_HTTPHEADER => ['Content-type: application/json'],
            CURLOPT_POST => 1,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_TIMEOUT => 10
        ]);
        curl_exec($ch);
        if(curl_errno($ch)) {
            error_log('Discord cURL Error: ' . curl_error($ch));
        }
        curl_close($ch);
    }

    function create_safe_datetime($date_string, $timezone) {
        if (empty($date_string) || $date_string === '0000-00-00 00:00:00') {
            return new DateTime('1970-01-01 00:00:00', $timezone);
        }
        try {
            return new DateTime($date_string, $timezone);
        } catch (Exception $e) {
            error_log("Failed to create DateTime from string: {$date_string}. Error: {$e->getMessage()}");
            return new DateTime('1970-01-01 00:00:00', $timezone);
        }
    }

    try {


        $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

        // --- Cấu hình process (đúng như các truy vấn cũ) ---
        $PROC = [
            'mold'    => ['table' => 'mold',    'join_on' => 'BINARY t.mold_id = BINARY d.device_id',
                          'where_ext' => "AND d.process IN ('Single','1st') AND (t.cycle_time > 20)",
                          'output_col' => 't.cavities'],
            'tuft'    => ['table' => 'tuft',    'join_on' => 'BINARY t.device_id = BINARY d.device_id',
                          'where_ext' => "AND (BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')",
                          'output_col' => 't.output'],
            'blister' => ['table' => 'blister', 'join_on' => 'BINARY t.device_id = BINARY d.device_id',
                          'where_ext' => "",
                          'output_col' => 't.output'],
        ];

        $action = $_POST['action'] ?? ($_GET['action'] ?? null);

        $history_device_id = $_GET['history'] ?? null;

        if (($_GET['action'] ?? '') === 'get_hourly_report') {
            // capture_current_endpoint();


            
        try {
            if (!isset($_GET['device_id'])) {
            send_json(['status'=>'error','message'=>'Device ID is required'], 400);
            }

            $deviceId = $_GET['device_id'];
            $report_date_str = $_GET['report_date'] ?? date('Y-m-d');
            $start_datetime = $report_date_str . ' 07:00:00';
            $end_datetime   = date('Y-m-d H:i:s', strtotime($start_datetime . ' +24 hours -1 second'));

            // TODO: $pdo = new PDO(...); // đảm bảo đã có kết nối PDO

            $stmt_device = $pdo->prepare("SELECT * FROM devices WHERE device_id = ?");
            $stmt_device->execute([$deviceId]);
            $device_config = $stmt_device->fetch(PDO::FETCH_ASSOC);

            if (!$device_config || empty($device_config['data_source'])) {
            send_json(['status'=>'error','message'=>'Device configuration not found or incomplete'], 404);
            }

            $table = $device_config['data_source'];
            if (!ctype_alnum(str_replace('_', '', $table))) {
            send_json(['status'=>'error','message'=>'Invalid data source table name'], 400);
            }

            $efficiency_sql = "0";
            $output_sql = "0";
            $id_column = "device_id";
            $extra_where_condition = "1=1";

            switch ($device_config['display_type']) {
            case 'mold':
                $id_column = "mold_id";
                $target_cycle_time = (float)($device_config['target_limit'] ?? 0); // sec/shot
                $efficiency_sql = "AVG( CASE WHEN t.cycle_time < 20 THEN 0 ELSE ({$target_cycle_time} / NULLIF(t.cycle_time, 0)) * 100 END )";
                $output_sql     = "AVG( CASE WHEN t.cycle_time < 20 THEN 0 ELSE (60 * t.cavities) / NULLIF(t.cycle_time, 0) END ) * 60"; // pcs/h
                break;

            case 'tuft':
                $id_column = "device_id";
                $target_output = (float)($device_config['target_limit'] ?? 0); // pcs/min
                $efficiency_sql = "AVG(t.output / NULLIF({$target_output}, 0)) * 100";
                $output_sql     = "AVG(t.output) * 60"; // pcs/h
                break;

            case 'blister':
                $id_column = "device_id";
                $target_cycles_per_min = (float)($device_config['target_limit'] ?? 0);
                $brushes_per_cycle     = (int)  ($device_config['brushes_per_cycle'] ?? 0);
                $target_pcs_per_min    = $target_cycles_per_min * $brushes_per_cycle;
                $efficiency_sql = "AVG(t.output / NULLIF({$target_pcs_per_min}, 0)) * 100";
                $output_sql     = "AVG(t.output) * 60"; // pcs/h
                break;

            default:
                send_json([
                'status' => 'success',
                'hourly_data' => [],
                'lost_pcs_24' => array_fill(0,24,0),
                'idle_24_min' => array_fill(0,24,0),
                'window' => ['from'=>$start_datetime,'to'=>$end_datetime,'start_hour'=>7]
                ]);
            }

            $sql_query = "
            SELECT HOUR(t.`datetime`) as hour_of_day,
                    {$efficiency_sql} as avg_efficiency,
                    {$output_sql}     as total_output
            FROM `{$table}` AS t
            WHERE t.`{$id_column}` = ?
                AND t.`datetime` BETWEEN ? AND ?
                AND {$extra_where_condition}
            GROUP BY hour_of_day
            ORDER BY hour_of_day ASC";

            $stmt_report = $pdo->prepare($sql_query);
            $stmt_report->execute([$deviceId, $start_datetime, $end_datetime]);
            $rows = $stmt_report->fetchAll(PDO::FETCH_ASSOC);

            // Chuẩn hóa 24 giờ 07->06 (index = (hour - 7 + 24) % 24)
            $capacity_hr = (float)($device_config['capacity'] ?? 0);
            if ($capacity_hr < 0) $capacity_hr = 0.0;
            $cap_per_min = $capacity_hr > 0 ? $capacity_hr / 60.0 : 0.0;

            $lost_pcs_24 = array_fill(0, 24, 0.0);
            $idle_24_min = array_fill(0, 24, 0.0);
            $hourly_data = array_fill(0, 24, [
            'hour'                 => null,
            'avg_efficiency'       => 0.0,
            'total_output'         => 0.0,
            'total_loss_pcs'       => 0.0,
            'total_idle_breakdown' => 0.0,
            ]);

            foreach ($rows as $r) {
            $hAbs = (int)$r['hour_of_day'];             // 0..23 lịch
            $idx  = ($hAbs - 7 + 24) % 24;              // 0..23 theo cửa sổ 07->06
            $actual = round((float)$r['total_output'], 2); // pcs/h
            $lost   = max(0.0, $capacity_hr - $actual);
            $idleM  = $cap_per_min > 0 ? $lost / $cap_per_min : 0.0;

            $lost_pcs_24[$idx] = round($lost, 2);
            $idle_24_min[$idx] = round($idleM, 2);

            $hourly_data[$idx] = [
                'hour'                 => $hAbs,
                'avg_efficiency'       => round((float)$r['avg_efficiency'], 2),
                'total_output'         => $actual,
                'total_loss_pcs'       => round($lost, 2),
                'total_idle_breakdown' => round($idleM, 2),
            ];
            }

            // Điền trường hour cho các giờ trống (để FE dễ debug)
            for ($i=0; $i<24; $i++) {
            if ($hourly_data[$i]['hour'] === null) {
                // Map ngược về giờ lịch:
                $hAbs = ($i + 7) % 24;
                $hourly_data[$i]['hour'] = $hAbs;
            }
            }

            send_json([
            'status'          => 'success',
            'device_id'       => $deviceId,
            'window'          => ['from'=>$start_datetime,'to'=>$end_datetime,'start_hour'=>7],
            'capacity'        => $capacity_hr,
            'efficiency_limit'=> 100,
            'eff_lower_limit' => (float)($device_config['efficiency_lower_limit'] ?? 0),
            'eff_upper_limit' => (float)($device_config['efficiency_upper_limit'] ?? 0),
            'hourly_data'     => $hourly_data,  // vẫn giữ để các nơi khác dùng
            'lost_pcs_24'     => $lost_pcs_24,  // mảng 24 phần tử cho populateLossReport()
            'idle_24_min'     => $idle_24_min,  // mảng 24 phần tử (phút)
            ]);

        } catch (Throwable $e) {
            // Luôn trả JSON khi lỗi
            send_json(['status'=>'error','message'=>$e->getMessage()], 500);
        }
}
elseif ($action === 'get_output_report_bulk') {
    try {
        $tzVN = new DateTimeZone('Asia/Ho_Chi_Minh');

        if (!function_exists('parseLocal')) {
            function parseLocal(?string $s, DateTimeZone $tz): ?DateTime {
                if (!$s) return null;
                try { return new DateTime(str_replace('T',' ', $s), $tz); }
                catch (Throwable $e) { return null; }
            }
        }

        // --- Inputs ---
        $from_raw = $_GET['from'] ?? '';
        $to_raw   = $_GET['to']   ?? '';
        $fromVN   = parseLocal($from_raw, $tzVN);
        $toVN     = parseLocal($to_raw,   $tzVN);
        if (!$fromVN || !$toVN) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Invalid from/to']); exit;
        }

        if (!isset($PROC) || !is_array($PROC) || !$PROC) {
            http_response_code(500);
            echo json_encode(['status'=>'error','message'=>'Server misconfigured: $PROC not defined']); exit;
        }

        // Nhận cả process lẫn proc (nếu bạn muốn)
        $processParam = strtolower(trim($_GET['process'] ?? ($_GET['proc'] ?? 'all')));
        $allProcKeys  = array_keys($PROC);
        if ($processParam !== 'all' && !in_array($processParam, $allProcKeys, true)) {
            http_response_code(400);
            echo json_encode(['status'=>'error','message'=>'Invalid process']); exit;
        }

        // 1) Process dùng cho SQL (có thể lọc theo tham số)
        $PROC_SQL = ($processParam === 'all') ? $PROC : [$processParam => $PROC[$processParam]];

        // 2) Bộ process dùng cho PAD & totals (luôn là toàn bộ)
        $procKeysAll = array_keys($PROC);

        // Số giờ
        $hours = max(0, ($toVN->getTimestamp() - $fromVN->getTimestamp()) / 3600.0);

        // Lọc theo family
        $list_raw = $_GET['families'] ?? ($_GET['products'] ?? '');
        $requestedProducts = [];
        if ($list_raw !== '') {
            foreach (explode(',', $list_raw) as $p) {
                $p = trim($p);
                if ($p !== '') $requestedProducts[] = $p;
            }
        }

        // --- SQL (UNION ALL theo $PROC_SQL) ---
        $parts  = [];
        $params = [
            ':from' => $fromVN->format('Y-m-d H:i:s'),
            ':to'   => $toVN->format('Y-m-d H:i:s'),
        ];

        $prodFilterSql = '';
        if (!empty($requestedProducts)) {
            $ph = [];
            foreach ($requestedProducts as $i => $p) { $k=":prod{$i}"; $ph[]=$k; $params[$k]=$p; }
            $prodFilterSql = ' AND TRIM(d.product) IN ('.implode(',', $ph).') ';
        }

        foreach ($PROC_SQL as $procName => $cfg) {
            $t   = $cfg['table'];
            $jo  = $cfg['join_on'];      // ví dụ: t.device_id = d.device_id
            $oc  = $cfg['output_col'];   // ví dụ: t.total_count
            $wxOn    = $cfg['where_on']    ?? '';                       // điều kiện liên quan t.*
            $wxWhere = $cfg['where_where'] ?? ($cfg['where_ext'] ?? ''); // điều kiện d.*

            $parts[] = "
                SELECT
                    '{$procName}' AS process,
                    Y.product     AS product,
                    SUM(Y.output)   AS output_total,
                    SUM(Y.capacity) AS capacity_sum
                FROM (
                    SELECT
                        d.device_id,
                        TRIM(d.product) AS product,
                        COALESCE(d.capacity,0) AS capacity,
                        COALESCE(SUM(COALESCE({$oc},0)),0) AS output
                    FROM devices d
                    LEFT JOIN {$t} t
                      ON {$jo}
                     AND t.`datetime` >= :from AND t.`datetime` < :to
                     {$wxOn}
                    WHERE d.display_type = '{$procName}'
                      {$wxWhere}
                      {$prodFilterSql}
                    GROUP BY d.device_id, TRIM(d.product), d.capacity
                ) Y
                GROUP BY Y.product
            ";
        }

        $sql = implode("\nUNION ALL\n", $parts) . "\nORDER BY product, process";
        $stmt = $pdo->prepare($sql);
        foreach ($params as $k => $v) { $stmt->bindValue($k, $v); }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // --- Reshape ---
        $matrix = [];

        // PAD: luôn tạo đủ 3 process (mold/tuft/blister) cho mọi family được yêu cầu
        if (!empty($requestedProducts)) {
            foreach ($requestedProducts as $prodName) {
                $prodName = trim($prodName);
                if ($prodName === '') continue;
                if (!isset($matrix[$prodName])) $matrix[$prodName] = [];
                foreach ($procKeysAll as $p) {
                    if (!isset($matrix[$prodName][$p])) {
                        $matrix[$prodName][$p] = ['output'=>0,'cap'=>0,'efficiency'=>0];
                    }
                }
            }
        }

        $totals = ['out'=>[], 'cap'=>[], 'eff_weighted'=>[], 'eff_avg'=>[]];
        $effSum = []; $effCnt = [];
        foreach ($procKeysAll as $p) {
            $totals['out'][$p] = 0; $totals['cap'][$p] = 0;
            $totals['eff_weighted'][$p] = 0; $totals['eff_avg'][$p] = 0;
            $effSum[$p] = 0; $effCnt[$p] = 0;
        }

        // Ghi đè dữ liệu thật từ SQL (chỉ có các process nằm trong $PROC_SQL)
        foreach ($rows as $r) {
            $prod = trim($r['product'] ?? 'Unknown');
            $proc = $r['process'];

            if (!isset($matrix[$prod])) $matrix[$prod] = [];
            // bảo đảm đủ 3 process cho family này
            foreach ($procKeysAll as $p) {
                if (!isset($matrix[$prod][$p])) {
                    $matrix[$prod][$p] = ['output'=>0,'cap'=>0,'efficiency'=>0];
                }
            }

            $o = (float)($r['output_total'] ?? 0);
            $c = (float)($r['capacity_sum'] ?? 0);

            $matrix[$prod][$proc]['output'] = $o;
            $matrix[$prod][$proc]['cap']    = $c;

            $eff = ($c > 0 && $hours > 0) ? ($o / ($c * $hours)) * 100 : 0.0;
            $matrix[$prod][$proc]['efficiency'] = round($eff, 2);

            $totals['out'][$proc] += $o;
            $totals['cap'][$proc] += $c;
            if ($eff > 0) { $effSum[$proc] += $eff; $effCnt[$proc]++; }
        }

        // Tính totals cho đủ 3 process (những process không truy vấn sẽ vẫn = 0)
        foreach ($procKeysAll as $p) {
            $totals['eff_weighted'][$p] = ($totals['cap'][$p] > 0 && $hours > 0)
                ? round(($totals['out'][$p] / ($totals['cap'][$p] * $hours)) * 100, 2)
                : 0.0;
            $totals['eff_avg'][$p] = ($effCnt[$p] > 0)
                ? round($effSum[$p] / $effCnt[$p], 2)
                : 0.0;
        }

        // (Tuỳ chọn) nếu không truyền families và rows rỗng, vẫn trả một nhãn để FE dễ xử lý
        if (empty($requestedProducts) && empty($rows)) {
            $prod = '(No data)';
            $matrix[$prod] = [];
            foreach ($procKeysAll as $p) {
                $matrix[$prod][$p] = ['output'=>0,'cap'=>0,'efficiency'=>0];
            }
        }

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'status' => 'success',
            'hours'  => $hours,
            'matrix' => $matrix,
            'totals' => $totals,
        ], JSON_UNESCAPED_UNICODE | JSON_NUMERIC_CHECK);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>'get_output_report_bulk failed: '.$e->getMessage()]);
        exit;
    }
}


 elseif ($action === 'search_device') {
            $deviceId = $_GET['device_id'] ?? '';
            $from_date_str = $_GET['from'] ?? '';
            $to_date_str = $_GET['to'] ?? '';
            if (empty($deviceId) || empty($from_date_str) || empty($to_date_str)) {
                throw new Exception("Device ID and date range are required for device search.");
            }
            $stmt_device = $pdo->prepare("SELECT data_source, display_type FROM devices WHERE device_id = ?");
            $stmt_device->execute([$deviceId]);
            $device_config = $stmt_device->fetch();
            if (!$device_config || empty($device_config['data_source'])) {
                throw new Exception("Device configuration not found for device ID: " . htmlspecialchars($deviceId));
            }
            $table = $device_config['data_source'];
            if (!ctype_alnum(str_replace('_', '', $table))) {
                throw new Exception("Invalid data source table name.");
            }
            $from_date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $from_date_str);
            $to_date_obj = DateTime::createFromFormat('Y-m-d H:i:s', $to_date_str);
            if (!$from_date_obj || !$to_date_obj) {
                throw new Exception("Invalid date format. Expected 'Y-m-d H:i:s'.");
            }
            $from_date_sql = $from_date_obj->format('Y-m-d H:i:s');
            $to_date_sql = $to_date_obj->format('Y-m-d H:i:s');
            $id_column = ($table === 'mold') ? 'mold_id' : 'device_id';
            $select_clause = ($device_config['display_type'] === 'mold') ? "*, CASE WHEN cycle_time < 20 THEN 0 ELSE cavities END AS output" : "*";
            $sql = "SELECT {$select_clause} FROM `{$table}` WHERE {$id_column} = ? AND datetime BETWEEN ? AND ? ORDER BY datetime DESC LIMIT 2000";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$deviceId, $from_date_sql, $to_date_sql]);
            echo json_encode($stmt->fetchAll(), JSON_NUMERIC_CHECK);
            exit;

        }// --- COUNT FLEXIBLE THEO TAB/PROCESS ---
elseif (($action ?? '') === 'count_flexible') {
    try {
        $proc = strtolower(trim($_GET['process'] ?? ''));     // mold|injection|tuft|blister
        $family = trim($_GET['family'] ?? '');                // tùy chọn (nếu có cột family)

        $allowed = ['mold','injection','tuft','blister'];
        $where = "WHERE flex = '1'";
        $params = [];

        if (in_array($proc, $allowed, true)) {
            $where .= " AND display_type = :proc";
            $params[':proc'] = $proc;
        }

        // Nếu bạn muốn lọc sâu theo family (nếu có cột 'family' trong devices)
        if ($family !== '') {
            $where .= " AND family = :family";
            $params[':family'] = $family;
        }

        $sql = "SELECT COUNT(*) AS c FROM devices {$where}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int)($stmt->fetchColumn() ?? 0);

        echo json_encode(['count' => $count], JSON_UNESCAPED_UNICODE); exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
    }
}

// --- COUNT ACTION OPEN/IN_PROGRESS THEO TAB/PROCESS ---
elseif (($action ?? '') === 'count_actions') {
    try {
        $proc = strtolower(trim($_GET['process'] ?? ''));     // mold|injection|tuft|blister
        $family = trim($_GET['family'] ?? '');                // tùy chọn

        $allowed = ['mold','injection','tuft','blister'];
        $where = "WHERE da.status <> 'cancelled' AND NOT (da.status='done' AND da.approval_status='approved')";

        $params = [];

        // Join devices để lọc theo process/family
        $join = "JOIN devices d ON d.device_id = da.device_id";

        if (in_array($proc, $allowed, true)) {
            $where .= " AND d.display_type = :proc";
            $params[':proc'] = $proc;
        }
        if ($family !== '') {
            $where .= " AND d.family = :family";
            $params[':family'] = $family;
        }

        $sql = "SELECT COUNT(*) AS c
                FROM device_actions da
                {$join}
                {$where}";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $count = (int)($stmt->fetchColumn() ?? 0);

        echo json_encode(['count' => $count], JSON_UNESCAPED_UNICODE); exit;
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]); exit;
    }
}
 elseif ($history_device_id) {
            $stmt_device = $pdo->prepare("SELECT data_source, metric_name FROM devices WHERE device_id = ?");
            $stmt_device->execute([$history_device_id]);
            $device_config = $stmt_device->fetch();
            if (!$device_config || empty($device_config['data_source']) || empty($device_config['metric_name'])) {
                throw new Exception('Device config not found or primary metric is not set.');
            }
            $table = $device_config['data_source'];
            $metric = $device_config['metric_name'];
            if (!ctype_alnum(str_replace('_', '', $table)) || !ctype_alnum(str_replace('_', '', $metric))) {
                throw new Exception("Invalid table or metric name.");
            }
            $stmt_history = $pdo->prepare("SELECT datetime, `{$metric}` FROM `{$table}` WHERE device_id = ? AND `{$metric}` IS NOT NULL ORDER BY datetime DESC LIMIT 8");
            $stmt_history->execute([$history_device_id]);
            echo json_encode(['metric' => $metric, 'data' => array_reverse($stmt_history->fetchAll())], JSON_NUMERIC_CHECK);
            exit;

        } elseif ($action === 'get_families') {
        $process_type = $_GET['process'] ?? 'mold';

        $sql = "SELECT DISTINCT product FROM devices WHERE display_type = ? AND product IS NOT NULL AND product != '' ORDER BY product ASC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$process_type]);
        $families = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        echo json_encode($families);
        exit;
        } elseif ($action === 'get_summary_report') {
    try {
        /* =======================
         * 1) Parse time (Asia/Ho_Chi_Minh) + build business windows
         * ======================= */
        $tzVN  = new DateTimeZone('Asia/Ho_Chi_Minh');

        $from_raw = $_GET['from'] ?? '';
        $to_raw   = $_GET['to']   ?? '';

        $fromVN = $from_raw ? new DateTime(str_replace('T',' ', $from_raw), $tzVN) : null;
        $toVN   = $to_raw   ? new DateTime(str_replace('T',' ', $to_raw),   $tzVN) : null;

        // Fallback: [07:00 hôm qua -> 07:00 hôm nay]
        if (!$fromVN || !$toVN) {
            $toVN   = new DateTime('today 07:00:00', $tzVN);
            $fromVN = (clone $toVN)->modify('-1 day');
        }
        // guard
        if ($fromVN >= $toVN) {
            $toVN = (clone $fromVN)->modify('+1 hour');
        }

        // Khung “day” = 07:00 -> 19:00 của ngày bắt đầu; “night” = 19:00 -> 07:00 kế tiếp.
        $day_from_dt   = clone $fromVN;
        $day_to_dt     = (clone $fromVN)->setTime(19,0,0);
        $night_from_dt = clone $day_to_dt;
        $night_to_dt   = clone $toVN;

        $day_from   = $day_from_dt->format('Y-m-d H:i:s');
        $day_to     = $day_to_dt->format('Y-m-d H:i:s');
        $night_from = $night_from_dt->format('Y-m-d H:i:s');
        $night_to   = $night_to_dt->format('Y-m-d H:i:s');

        $sec_day   = max(1, strtotime($day_to)   - strtotime($day_from));
        $sec_night = max(1, strtotime($night_to) - strtotime($night_from));
        $sec_total = max(1, strtotime($toVN->format('Y-m-d H:i:s')) - strtotime($fromVN->format('Y-m-d H:i:s')));

        $hrs_day   = $sec_day   / 3600.0;
        $hrs_night = $sec_night / 3600.0;
        $hrs_total = $sec_total / 3600.0;

        /* =======================
         * 2) + 3) Lấy output/day-night và capacity/h theo process (2 query)
         *  - Giữ BINARY trong JOIN/LIKE để tránh lỗi mix collation
         *  - Giới hạn WHERE theo 24h để cắt I/O
         * ======================= */

        // Query 1: tổng output theo khung day/night cho 3 process (siết WHERE theo 24h)
        $sqlOutputs = "
            SELECT proc,
                   SUM(day_out)   AS day_out,
                   SUM(night_out) AS night_out
            FROM (
                /* MOLDING */
                SELECT 'mold' AS proc,
                       SUM(CASE WHEN t.`datetime`>=:day_from AND t.`datetime`<:day_to
                                THEN COALESCE(t.cavities,0) ELSE 0 END) AS day_out,
                       SUM(CASE WHEN t.`datetime`>=:night_from AND t.`datetime`<:night_to
                                THEN COALESCE(t.cavities,0) ELSE 0 END) AS night_out
                FROM mold t
                JOIN devices d ON BINARY t.device_id = BINARY d.device_id
                WHERE d.display_type='mold'
                  AND d.process IN ('Single','1st')
                  AND t.cycle_time > 20
                  AND t.`datetime` >= :from_all AND t.`datetime` < :to_all

                UNION ALL

                /* TUFTING */
                SELECT 'tuft' AS proc,
                       SUM(CASE WHEN t.`datetime`>=:day_from AND t.`datetime`<:day_to
                                THEN COALESCE(t.output,0) ELSE 0 END) AS day_out,
                       SUM(CASE WHEN t.`datetime`>=:night_from AND t.`datetime`<:night_to
                                THEN COALESCE(t.output,0) ELSE 0 END) AS night_out
                FROM tuft t
                JOIN devices d ON BINARY t.device_id = BINARY d.device_id
                WHERE d.display_type='tuft'
                  AND (BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')
                  AND t.`datetime` >= :from_all AND t.`datetime` < :to_all

                UNION ALL

                /* BLISTER */
                SELECT 'blister' AS proc,
                       SUM(CASE WHEN t.`datetime`>=:day_from AND t.`datetime`<:day_to
                                THEN COALESCE(t.output,0) ELSE 0 END) AS day_out,
                       SUM(CASE WHEN t.`datetime`>=:night_from AND t.`datetime`<:night_to
                                THEN COALESCE(t.output,0) ELSE 0 END) AS night_out
                FROM blister t
                JOIN devices d ON BINARY t.device_id = BINARY d.device_id
                WHERE d.display_type='blister'
                  AND t.`datetime` >= :from_all AND t.`datetime` < :to_all
            ) X
            GROUP BY proc
        ";

        $stOut = $pdo->prepare($sqlOutputs);
        $stOut->execute([
            ':day_from'   => $day_from,
            ':day_to'     => $day_to,
            ':night_from' => $night_from,
            ':night_to'   => $night_to,
            ':from_all'   => $day_from,  // 07:00 hôm qua
            ':to_all'     => $night_to,  // 07:00 hôm nay
        ]);

        $outM = ['day_out'=>0.0,'night_out'=>0.0];
        $outT = ['day_out'=>0.0,'night_out'=>0.0];
        $outB = ['day_out'=>0.0,'night_out'=>0.0];

        while ($row = $stOut->fetch(PDO::FETCH_ASSOC)) {
            $proc = $row['proc'];
            $pair = ['day_out'=>(float)$row['day_out'], 'night_out'=>(float)$row['night_out']];
            if ($proc === 'mold')        $outM = $pair;
            elseif ($proc === 'tuft')    $outT = $pair;
            elseif ($proc === 'blister') $outB = $pair;
        }

        // Query 2: tổng capacity_per_hour cho 3 process — có cache 120s
        $sqlCaps = "
            SELECT proc, SUM(capacity) AS cap_per_hour
            FROM (
                SELECT 'mold' AS proc, COALESCE(d.capacity,0) AS capacity
                FROM devices d
                WHERE d.display_type='mold' AND d.process IN ('Single','1st')

                UNION ALL
                SELECT 'tuft' AS proc, COALESCE(d.capacity,0) AS capacity
                FROM devices d
                WHERE d.display_type='tuft'
                  AND (BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')

                UNION ALL
                SELECT 'blister' AS proc, COALESCE(d.capacity,0) AS capacity
                FROM devices d
                WHERE d.display_type='blister'
            ) C
            GROUP BY proc
        ";

        static $capCache = null; // cache trong process PHP
        if ($capCache && ($capCache['ts'] > time() - 120)) {
            [$capM, $capT, $capB] = $capCache['vals'];
        } else {
            $caps = $pdo->query($sqlCaps)->fetchAll(PDO::FETCH_KEY_PAIR); // ['mold'=>..., 'tuft'=>..., 'blister'=>...]
            $capM = (float)($caps['mold']    ?? 0.0);
            $capT = (float)($caps['tuft']    ?? 0.0);
            $capB = (float)($caps['blister'] ?? 0.0);
            $capCache = ['ts'=>time(), 'vals'=>[$capM,$capT,$capB]];
        }

        /* =======================
         * 4) Efficiency = ratio-of-sums
         * ======================= */
        $eff = function(float $sumOutput, float $sumCapPerHour, float $hours): float {
            return ($sumCapPerHour > 0 && $hours > 0)
                ? round(($sumOutput / ($sumCapPerHour * $hours)) * 100.0, 2)
                : 0.0;
        };

        // Molding
        $effM_day   = $eff($outM['day_out'],                      $capM, $hrs_day);
        $effM_night = $eff($outM['night_out'],                    $capM, $hrs_night);
        $effM_avg   = $eff($outM['day_out'] + $outM['night_out'], $capM, $hrs_total);

        // Tufting
        $effT_day   = $eff($outT['day_out'],                      $capT, $hrs_day);
        $effT_night = $eff($outT['night_out'],                    $capT, $hrs_night);
        $effT_avg   = $eff($outT['day_out'] + $outT['night_out'], $capT, $hrs_total);

        // Blister
        $effB_day   = $eff($outB['day_out'],                      $capB, $hrs_day);
        $effB_night = $eff($outB['night_out'],                    $capB, $hrs_night);
        $effB_avg   = $eff($outB['day_out'] + $outB['night_out'], $capB, $hrs_total);

        /* =======================
         * 5) Lost (giữ cách tính của bạn)
         * ======================= */
        $makeOutputNode = function(array $self, ?array $base): array {
            $day   = (int)round($self['day_out']   ?? 0);
            $night = (int)round($self['night_out'] ?? 0);
            $total = $day + $night;

            if (!$base) {
                return [
                    'day' => $day, 'night' => $night, 'total' => $total,
                    'day_lost_pcs'=>0,'day_loss_percent'=>0.0,
                    'night_lost_pcs'=>0,'night_loss_percent'=>0.0,
                    'total_lost_pcs'=>0,'total_loss_percent'=>0.0
                ];
            }

            $base_day   = (int)round($base['day_out']   ?? 0);
            $base_night = (int)round($base['night_out'] ?? 0);
            $lost_day   = max(0, $base_day   - $day);
            $lost_night = max(0, $base_night - $night);
            $lost_total = $lost_day + $lost_night;

            $pct_day    = $base_day   > 0 ? round($lost_day   / $base_day   * 100, 2) : 0.0;
            $pct_night  = $base_night > 0 ? round($lost_night / $base_night * 100, 2) : 0.0;
            $base_total = $base_day + $base_night;
            $pct_total  = $base_total > 0 ? round($lost_total / $base_total * 100, 2) : 0.0;

            return [
                'day' => $day, 'night' => $night, 'total' => $total,
                'day_lost_pcs'   => $lost_day,    'day_loss_percent'   => $pct_day,
                'night_lost_pcs' => $lost_night,  'night_loss_percent' => $pct_night,
                'total_lost_pcs' => $lost_total,  'total_loss_percent' => $pct_total,
            ];
        };
        $countsBy = [
            'mold'    => ['running'=>0, 'breakdown'=>0, 'warning'=>0, 'total'=>0],
            'tuft'    => ['running'=>0, 'breakdown'=>0, 'warning'=>0, 'total'=>0],
            'blister' => ['running'=>0, 'breakdown'=>0, 'warning'=>0, 'total'=>0],
            ];

            $sqlStatus = "
            SELECT
                d.display_type AS type,
                UPPER(COALESCE(ds.connection_status,'DISCONNECTED')) AS conn,
                UPPER(COALESCE(ds.threshold_status,'NORMAL'))        AS thres
            FROM devices d
            LEFT JOIN device_status ds ON ds.device_id = d.device_id
            WHERE
                (d.display_type='mold'    )
                OR
                (d.display_type='tuft'    )
                OR
                (d.display_type='blister')
            ";

            foreach ($pdo->query($sqlStatus) as $r) {
            $t = $r['type']; if (!isset($countsBy[$t])) continue;
            $countsBy[$t]['total']++;

            $conn  = $r['conn']  ?: 'DISCONNECTED';
            $thres = $r['thres'] ?: 'NORMAL';
            if ($thres === 'WARNING') $thres = 'BREACHED'; // chuẩn hoá

            if ($conn === 'DISCONNECTED') {
                $countsBy[$t]['breakdown']++;
            } else {
                $countsBy[$t]['running']++;
                if ($thres === 'BREACHED') $countsBy[$t]['warning']++;
            }
            }
        /* =======================
         * 6) Payload
         * ======================= */
        $payload = [
            'mold' => [
                'status'     => $countsBy['mold'],
                'efficiency' => ['day'=>$effM_day, 'night'=>$effM_night, 'average'=>$effM_avg],
                'output'     => $makeOutputNode($outM, null),
            ],
            'tuft' => [
                'status'     => $countsBy['tuft'],
                'efficiency' => ['day'=>$effT_day, 'night'=>$effT_night, 'average'=>$effT_avg],
                'output'     => $makeOutputNode($outT, $outM),
            ],
            'blister' => [
                'status'     => $countsBy['blister'],
                'efficiency' => ['day'=>$effB_day, 'night'=>$effB_night, 'average'=>$effB_avg],
                'output'     => $makeOutputNode($outB, $outT),
            ],
            '_range' => [
                'day_from'   => $day_from,
                'day_to'     => $day_to,
                'night_from' => $night_from,
                'night_to'   => $night_to,
            ],
        ];


        if (!empty($_GET['debug'])) {
            $payload['_debug'] = [
                'windows' => [
                    'day_from' => $day_from, 'day_to' => $day_to,
                    'night_from' => $night_from, 'night_to' => $night_to,
                    'hours' => ['day'=>$hrs_day,'night'=>$hrs_night,'total'=>$hrs_total],
                ],
                'sum_output' => ['mold'=>$outM,'tuft'=>$outT,'blister'=>$outB],
                'sum_capacity_per_hour' => ['mold'=>$capM,'tuft'=>$capT,'blister'=>$capB],
            ];
        }

        // Headers + output
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header('Content-Type: application/json; charset=utf-8');
        // Nén do web server đảm nhiệm (gzip/brotli). Tránh DEBUG ở prod để payload nhỏ.
        echo json_encode($payload, JSON_NUMERIC_CHECK);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>'get_summary_report failed: '.$e->getMessage()]);
        exit;
    }
}


/* ===== BACKEND: Devices for Action tab ===== */
elseif ($action === 'devices_action_table') {
  try {
    // filter theo display_type (mold|tuft|blister|injection|all)
    $type   = $_GET['display_type'] ?? 'all';
    $params = [];
    $where  = '';
    if ($type !== 'all') { $where = 'WHERE d.display_type = ?'; $params[] = $type; }

    // Giải thích nhanh:
    // - open_count: số action chưa cancel và chưa "done + approved"
    // - urgent_overdue: action priority='urgent' và CÓ plan với min(est_date) < CURRENT_DATE()
    // - last_*: action mới nhất + ngày hoàn tất dự kiến dựa trên MAX(est_date) của các plan
    $sql = "
      SELECT
        d.device_id,
        d.display_type,
        d.product,
        d.process,

        COALESCE(a.open_count, 0)     AS open_count,
        COALESCE(a.urgent_overdue, 0) AS urgent_overdue,

        la.id                         AS last_action_id,
        la.title                      AS last_title,
        la.status                     AS last_status,
        la.priority                   AS last_priority,
        DATE(lp.max_est_date)         AS last_planned_completion_date
      FROM devices d

      /* Tổng hợp theo thiết bị */
      LEFT JOIN (
        SELECT
          TRIM(a.device_id) AS device_id,
          SUM(
            CASE
              WHEN a.status <> 'cancelled'
               AND NOT (a.status = 'done' AND a.approval_status = 'approved')
              THEN 1 ELSE 0
            END
          ) AS open_count,
          SUM(
            CASE
              WHEN a.status <> 'cancelled'
               AND NOT (a.status = 'done' AND a.approval_status = 'approved')
               AND a.priority = 'urgent'
               AND p.min_est_date IS NOT NULL
               AND p.min_est_date < CURRENT_DATE()
              THEN 1 ELSE 0
            END
          ) AS urgent_overdue
        FROM device_actions a
        LEFT JOIN (
          SELECT action_id, MIN(est_date) AS min_est_date
          FROM device_action_plans
          GROUP BY action_id
        ) p ON p.action_id = a.id
        GROUP BY TRIM(a.device_id)
      ) a ON a.device_id = d.device_id

      /* Action mới nhất theo created_at */
      LEFT JOIN (
        SELECT da.*
        FROM device_actions da
        JOIN (
          SELECT device_id, MAX(created_at) AS max_created_at
          FROM device_actions
          GROUP BY device_id
        ) mx
          ON mx.device_id = da.device_id
         AND da.created_at = mx.max_created_at
      ) la ON la.device_id = d.device_id

      /* Ngày hoàn tất dự kiến cuối cùng của action mới nhất (từ plan) */
      LEFT JOIN (
        SELECT action_id, MAX(est_date) AS max_est_date
        FROM device_action_plans
        GROUP BY action_id
      ) lp ON lp.action_id = la.id

      $where
      ORDER BY d.display_type, d.device_id
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC), JSON_UNESCAPED_UNICODE);
    exit;
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}



elseif ($action === 'get_machine_details') {
    $process_type  = $_GET['process'] ?? 'mold';
    $status_filter = strtolower($_GET['status'] ?? 'total'); // total|running|breakdown|warning

    // SQL giữ nguyên để lấy đủ các thông số giới hạn (limit)
    $sql = "
        SELECT 
            d.device_id, d.client, d.product AS family, d.process, d.cavities AS mold_cavity,
            d.capacity AS capacity_per_hr, d.target_limit AS target,
            d.upper_limit, d.lower_limit, d.efficiency_lower_limit, d.frequency, d.brushes_per_cycle, d.hole_per_brush,
            d.total_count, d.cavity_count, d.total_rpm, d.total_cycle, 
            (COALESCE(d.flex,'0') = '1') AS is_flex,
            EXISTS (
                SELECT 1
                FROM device_actions da
                WHERE BINARY da.device_id = BINARY d.device_id
                    AND da.status <> 'cancelled'
                    AND NOT (da.status='done' AND da.approval_status='approved')
                LIMIT 1
            ) AS has_action,
            ldd.live_data, ldd.last_updated
        FROM devices d
        LEFT JOIN live_device_data ldd ON d.device_id = ldd.device_id
        WHERE d.display_type = ?
        ORDER BY d.device_id;
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$process_type]);
    $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $results = [];
    $now = time(); // Lấy thời gian server hiện tại để tính heartbeat

    foreach ($devices as $device) {
        // 1. Parse Data
        $rawLiveData = $device['live_data'] ?? '';
        if (!is_string($rawLiveData) || trim($rawLiveData) === '') {
            $live = [];
        } else {
            $live = json_decode($rawLiveData, true) ?: [];
        }

        // Lấy output/rate/speed
        $live_output = isset($live['output']) ? (float)$live['output'] : null;
        if ($live_output === null) {
            $live_output = isset($live['rate']) ? (float)$live['rate'] : (isset($live['speed']) ? (float)$live['speed'] : 0);
        }
        $live_cyclecount = (float)($live['cyclecount'] ?? $live['cycle_count'] ?? 0);

        // 2. Tính toán Metrics (Calculate Metrics)
        switch ($process_type) {
            case 'mold':    $calc = calculateMoldMetrics($live, $device);    break;
            case 'tuft':    $calc = calculateTuftMetrics($live, $device);    break;
            case 'blister': $calc = calculateBlisterMetrics($live, $device); break;
            default:        $calc = [];
        }

        // ======================================================================================
        // BẮT ĐẦU LOGIC GIỐNG API VIEW
        // ======================================================================================

        // A. XÁC ĐỊNH KẾT NỐI (DISCONNECTED)
        // Ưu tiên lấy timestamp từ gói tin JSON, nếu không có thì lấy last_updated từ DB
        $dataTimestamp = 0;
        if (isset($live['datetime']) && $live['datetime']) {
            $dataTimestamp = strtotime($live['datetime']);
        } elseif (!empty($device['last_updated'])) {
            $dataTimestamp = strtotime($device['last_updated']);
        }

        $freq = (int)($device['frequency'] ?? 300);
        if ($freq <= 0) $freq = 300;

        // Logic: Nếu quá frequency giây không có dữ liệu => DISCONNECTED
        $isConnected = ($dataTimestamp > 0 && ($now - $dataTimestamp) <= $freq);


        // B. XÁC ĐỊNH VI PHẠM (BREACHED) - Tự tính toán, không tin DB status
        $isBreached = false;

        // Chỉ xét breached nếu đang kết nối
        if ($isConnected) {
            $eff = (float)($calc['efficiency'] ?? 0);
            $effLL = isset($device['efficiency_lower_limit']) ? (float)$device['efficiency_lower_limit'] : 0;
            $limitLower = isset($device['lower_limit']) ? (float)$device['lower_limit'] : 0;
            $limitUpper = isset($device['upper_limit']) ? (float)$device['upper_limit'] : 0;

            // -- Logic từng loại máy giống hệt view --
            if ($process_type === 'mold') {
                // 1. Hiệu suất thấp
                if ($effLL > 0 && $eff < $effLL) $isBreached = true;
                
                // 2. Cycle time vi phạm
                $cyc = (float)($live['cycle_time'] ?? 0);
                if ($cyc > 0) {
                    if ($limitLower > 0 && $cyc < $limitLower) $isBreached = true; // Chạy quá nhanh
                    if ($limitUpper > 0 && $cyc > $limitUpper) $isBreached = true; // Chạy quá chậm
                }

            } elseif ($process_type === 'tuft') {
                // 1. Hiệu suất thấp
                if ($effLL > 0 && $eff < $effLL) $isBreached = true;

                // 2. Output vi phạm
                if ($limitLower > 0 && $live_output < $limitLower) $isBreached = true;
                if ($limitUpper > 0 && $live_output > $limitUpper) $isBreached = true;

            } elseif ($process_type === 'blister') {
                // 1. Hiệu suất thấp
                if ($effLL > 0 && $eff < $effLL) $isBreached = true;

                // 2. Cycle Count vi phạm
                if ($limitLower > 0 && $live_cyclecount < $limitLower) $isBreached = true;
                if ($limitUpper > 0 && $live_cyclecount > $limitUpper) $isBreached = true;
            }
        }

        // C. TỔNG HỢP TRẠNG THÁI CUỐI CÙNG
        if (!$isConnected) {
            $status = 'DISCONNECTED';
        } elseif ($isBreached) {
            $status = 'BREACHED'; // Tương đương WARNING
        } else {
            $status = 'NORMAL';
        }
        // ======================================================================================


        // Các thông số phụ trợ
        $lost_time = (float)($calc['idle_breakdown'] ?? $calc['lost_time'] ?? $live['lost_time'] ?? 0);
        $isFlex    = ((int)($device['is_flex']    ?? 0) === 1);
        $hasAction = ((int)($device['has_action'] ?? 0) === 1);

        $results[] = [
            'mold_id'         => $device['device_id'],
            'client'          => $device['client'],
            'family'          => $device['family'],
            'process'         => $device['process'],
            'mold_cavity'     => (int)$device['mold_cavity'],
            'actual_cavity'   => (int)($live['cavities'] ?? 0),
            'capacity_per_hr' => (float)$device['capacity_per_hr'],
            'efficiency'      => (float)($calc['efficiency'] ?? 0),
            'efficiency_lower_limit' => isset($device['efficiency_lower_limit']) ? (float)$device['efficiency_lower_limit'] : null,
            'current_cycle'   => $process_type === 'mold'
                           ? (float)($live['cycle_time'] ?? 0)
                           : ($process_type === 'tuft' ? (float)$live_output : (float)$live_cyclecount),                 
            'output'          => (float)$live_output,        
            'cyclecount'      => (float)$live_cyclecount,    
            'total_count'     => (int)$device['total_count'],
            'cavity_count'    => $process_type === 'mold' ? (int)$device['cavity_count'] : null,
            'total_rpm'       => $process_type === 'tuft' ? (float)$device['total_rpm'] : null,
            'total_cycle'     => $process_type === 'blister' ? (float)$device['total_cycle'] : null,
            'target'          => (float)$device['target'] ?: null,
            'bush_per_cycle'  => ($process_type === 'blister') ? (isset($live['BrushesperCycle']) ? (float)$live['BrushesperCycle'] : null): null,
            'hole_per_brush'  => isset($device['hole_per_brush']) ? (int)$device['hole_per_brush'] : null,
            'upper_limit'     => (float)$device['upper_limit'] ?: null,
            'lower_limit'     => (float)$device['lower_limit'] ?: null,
            'total_lost_pcs'  => (float)($calc['loss_pcs'] ?? 0),
            'lost_time'       => $lost_time,
            'status'          => $status, // Trạng thái Real-time mới
            'last_updated'    => $device['last_updated'],
            'is_flex'         => $isFlex,
            'has_action'      => $hasAction,
        ];
    }

    // ---- Lọc kết quả (Filter) ----
    if ($status_filter !== 'total') {
        $sf = strtolower($status_filter);
        if ($sf === 'running') {
            // Running = NORMAL + BREACHED
            $results = array_values(array_filter($results, function ($r) {
                $st = strtoupper($r['status']);
                return $st === 'NORMAL' || $st === 'BREACHED';
            }));
        } elseif ($sf === 'breakdown') {
            // Breakdown = DISCONNECTED
            $results = array_values(array_filter($results, function ($r) {
                return strtoupper($r['status']) === 'DISCONNECTED';
            }));
        } elseif ($sf === 'warning') {
            // Warning = BREACHED
            $results = array_values(array_filter($results, function ($r) {
                return strtoupper($r['status']) === 'BREACHED';
            }));
        }
    }

    // Debug mode
    if (($_GET['debug'] ?? '') === '1') {
        $counts = [];
        foreach ($results as $r) {
            $k = strtoupper($r['status']);
            $counts[$k] = ($counts[$k] ?? 0) + 1;
        }
        header('Content-Type: application/json');
        echo json_encode([
            'data'   => $results,
            'counts' => $counts,
            'filter' => $status_filter,
            'process'=> $process_type,
        ], JSON_NUMERIC_CHECK);
        exit;
    }

    header('Content-Type: application/json');
    echo json_encode($results, JSON_NUMERIC_CHECK);
    exit;
}elseif ($action === 'get_efficiency_report_data') {
    try {
        /* ========= 0) Input & defaults ========= */
        $process_type      = $_GET['process']    ?? 'mold';                // mold|tuft|blister
        $from_datetime_str = $_GET['from']       ?? date('Y-m-d H:i:s');   // VN time
        $to_datetime_str   = $_GET['to']         ?? date('Y-m-d H:i:s');   // VN time
        $sort_by           = $_GET['sort_by']    ?? 'machine_id';
        $sort_order        = $_GET['sort_order'] ?? 'ASC';

        // Whitelist sort
        $allowed_sort_columns = ['machine_id','family','process','efficiency','current_cycle','total_lost_pcs','output','capacity'];
        if (!in_array($sort_by, $allowed_sort_columns, true)) $sort_by = 'machine_id';
        $sort_order = strtoupper($sort_order);
        if (!in_array($sort_order, ['ASC','DESC'], true)) $sort_order = 'ASC';

        // Tables + join key cho DETAILS (process đang chọn)
        $data_table = [
            'mold'    => 'mold',
            'tuft'    => 'tuft',
            'blister' => 'blister'
        ][$process_type] ?? 'mold';

        $joinOn = ($process_type === 'mold')
            ? "ON BINARY t.device_id  = BINARY d.device_id"
            : "ON BINARY t.device_id = BINARY d.device_id";

        // Process filters (đồng bộ với get_summary_report)
        $process_where_condition = "1=1";
        $extra_where_condition   = "1=1";
        if ($process_type === 'mold') {
            $process_where_condition = "d.process IN ('Single','1st')";
            $extra_where_condition   = "t.cycle_time > 20";
        } elseif ($process_type === 'tuft') {
            $process_where_condition = "(BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')";
        }

        /* ========= 1) Timezones, parse from/to ========= */
        $tzVN  = new DateTimeZone('Asia/Ho_Chi_Minh');
        $tzUTC = new DateTimeZone('UTC');

        // Để khớp get_summary_report (không convert), đặt FALSE (DB lưu giờ VN).
        // Nếu DB của bạn thực tế lưu UTC, hãy đặt TRUE ở cả 2 API để đồng chuẩn.
        $DB_IS_UTC = false;

        $fromLocal = DateTime::createFromFormat('Y-m-d H:i:s', $from_datetime_str, $tzVN)
                  ?: DateTime::createFromFormat('Y-m-d H:i',    $from_datetime_str, $tzVN);
        $toLocal   = DateTime::createFromFormat('Y-m-d H:i:s', $to_datetime_str,   $tzVN)
                  ?: DateTime::createFromFormat('Y-m-d H:i',    $to_datetime_str,   $tzVN);

        if (!$fromLocal || !$toLocal) {
            $fromLocal = new DateTime('now', $tzVN);
            $toLocal   = (clone $fromLocal)->modify('+1 hour');
        }
        if ($fromLocal >= $toLocal) {
            $toLocal = (clone $fromLocal)->modify('+1 hour');
        }

        $fromForQuery = $DB_IS_UTC ? (clone $fromLocal)->setTimezone($tzUTC)->format('Y-m-d H:i:s')
                                   : $fromLocal->format('Y-m-d H:i:s');
        $toForQuery   = $DB_IS_UTC ? (clone $toLocal)->setTimezone($tzUTC)->format('Y-m-d H:i:s')
                                   : $toLocal->format('Y-m-d H:i:s');

        $hours_total = max(0.0001, ($toLocal->getTimestamp() - $fromLocal->getTimestamp()) / 3600.0);

        /* ========= 2) DETAILS (per machine) – giữ schema hiện tại ========= */
        switch ($process_type) {
            case 'mold':
                // Output = tổng cavities hợp lệ
                $sum_output_sql = "SUM(CASE WHEN t.cycle_time > 20 THEN COALESCE(t.cavities,0) ELSE 0 END)";
                $select_fields = "
                    AVG(t.cavities)   AS actual_cavity,
                    {$sum_output_sql} AS output,
                    AVG(t.cycle_time) AS current_cycle,
                    GREATEST(0, ((AVG(d.capacity) / 3600 * TIMESTAMPDIFF(SECOND, MIN(t.`datetime`), MAX(t.`datetime`))) - {$sum_output_sql})) AS total_lost_pcs,
                    GREATEST(0, (((AVG(d.capacity) / 3600 * TIMESTAMPDIFF(SECOND, MIN(t.`datetime`), MAX(t.`datetime`))) - {$sum_output_sql}) / (AVG(d.capacity) / 60))) AS lost_time
                ";
                break;

            case 'tuft':
                // Output = tổng t.output (pcs)
                $sum_output_sql = "SUM(COALESCE(t.output,0))";
                $select_fields = "
                    0                  AS actual_cavity,
                    {$sum_output_sql}  AS output,
                    AVG(t.output)      AS current_cycle,
                    GREATEST(0, ((AVG(d.capacity) / 60 * TIMESTAMPDIFF(MINUTE, MIN(t.`datetime`), MAX(t.`datetime`))) - {$sum_output_sql})) AS total_lost_pcs,
                    GREATEST(0, (((AVG(d.capacity) / 60 * TIMESTAMPDIFF(MINUTE, MIN(t.`datetime`), MAX(t.`datetime`))) - {$sum_output_sql}) / (AVG(d.capacity) / 60))) AS lost_time
                ";
                break;

            case 'blister':
                // DETAILS có thể quy đổi output/phút → pcs (không ảnh hưởng tới SUMMARY)
                $sum_output_sql = "SUM(CAST(t.output AS DECIMAL(10,2)) * 60)";
                $select_fields = "
                    0                                     AS actual_cavity,
                    {$sum_output_sql}                     AS output,
                    AVG(CAST(t.output AS DECIMAL(10,2)))  AS current_cycle,
                    SUM(d.capacity - (CAST(t.output AS DECIMAL(10,2)) * 60)) AS total_lost_pcs,
                    SUM((d.capacity - (CAST(t.output AS DECIMAL(10,2)) * 60)) / (d.capacity / 60)) AS lost_time
                ";
                break;
        }

        $details_sql = "
            SELECT 
                d.device_id             AS machine_id,
                d.product               AS family,
                d.process               AS process,
                d.cavities              AS mold_cavity,
                d.capacity              AS capacity_per_hour,
                (d.capacity * :hoursTotal) AS capacity,

                d.target_limit          AS target,
                d.upper_limit,
                d.lower_limit,
                {$select_fields},
                (CASE WHEN COALESCE(d.capacity,0) > 0
                    THEN ({$sum_output_sql}) / (d.capacity * :hoursTotal) * 100
                    ELSE 0 END) AS efficiency
            FROM {$data_table} t
            JOIN devices d {$joinOn}
            WHERE d.display_type = :proc
              AND t.`datetime`   >= :fromQ
              AND t.`datetime`   <  :toQ
              AND {$process_where_condition}
              AND {$extra_where_condition}
            GROUP BY d.device_id, d.product, d.process, d.cavities, d.capacity, d.target_limit, d.upper_limit, d.lower_limit
            ORDER BY {$sort_by} {$sort_order}
        ";
        $details_stmt = $pdo->prepare($details_sql);
        $details_stmt->execute([
            ':proc'        => $process_type,
            ':fromQ'       => $fromForQuery,
            ':toQ'         => $toForQuery,
            ':hoursTotal'  => $hours_total
        ]);
        $details_data = $details_stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        /* ========= 3) SUMMARY WINDOWS (giống get_summary_report) =========
         * Day   = fromLocal(07:00) -> 19:00 cùng ngày-from
         * Night = 19:00 cùng ngày-from -> toLocal(07:00)
         * Avg   = fromLocal(07:00) -> toLocal(07:00)
         */
        $day_from_vn   = clone $fromLocal;
        $day_to_vn     = (clone $fromLocal)->setTime(19,0,0);
        $night_from_vn = clone $day_to_vn;
        $night_to_vn   = clone $toLocal;

        $hDay   = max(1, ($day_to_vn->getTimestamp()   - $day_from_vn->getTimestamp()))   / 3600.0;
        $hNight = max(1, ($night_to_vn->getTimestamp() - $night_from_vn->getTimestamp())) / 3600.0;
        $hAll   = max(1, ($toLocal->getTimestamp()     - $fromLocal->getTimestamp()))     / 3600.0;

        $toQuery = function(DateTime $dt) use ($DB_IS_UTC,$tzUTC) {
            $x = clone $dt;
            if ($DB_IS_UTC) $x->setTimezone($tzUTC);
            return $x->format('Y-m-d H:i:s');
        };
        $day_from_q   = $toQuery($day_from_vn);
        $day_to_q     = $toQuery($day_to_vn);
        $night_from_q = $toQuery($night_from_vn);
        $night_to_q   = $toQuery($night_to_vn);

        /* ========= 3a) SUMMARY cho 1 process (closure) =========
         * - Đồng bộ filter/process & công thức với get_summary_report:
         *   mold   => cavities (cycle_time > 20)
         *   tuft   => output (pcs)
         *   blister=> output (pcs)  // KHÔNG *60 trong SUMMARY
         */
        $computeSummaryFor = function(string $proc) use ($pdo, $day_from_q, $day_to_q, $night_from_q, $night_to_q, $hDay, $hNight, $hAll) {
            // Table + Join
            if ($proc === 'mold') {
                $tbl = 'mold';
                $joinOnLocal = "ON BINARY t.device_id = BINARY d.device_id";
                $sumOutExpr  = "CASE WHEN t.cycle_time > 20 THEN COALESCE(t.cavities,0) ELSE 0 END";
                $whereProc   = "d.display_type='mold' AND d.process IN ('Single','1st')";
                $capSql      = "SELECT SUM(COALESCE(capacity,0)) FROM devices WHERE display_type='mold' AND process IN ('Single','1st')";
                $extraWhere  = "t.cycle_time > 20";
            } elseif ($proc === 'tuft') {
                $tbl = 'tuft';
                $joinOnLocal = "ON BINARY t.device_id = BINARY d.device_id";
                $sumOutExpr  = "COALESCE(t.output,0)";
                $whereProc   = "d.display_type='tuft' AND (BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')";
                $capSql      = "SELECT SUM(COALESCE(capacity,0)) FROM devices WHERE display_type='tuft' AND (BINARY process LIKE BINARY '%Single%' OR BINARY process LIKE BINARY '%1st%')";
                $extraWhere  = "1=1";
            } else { // blister
                $tbl = 'blister';
                $joinOnLocal = "ON BINARY t.device_id = BINARY d.device_id";
                $sumOutExpr  = "COALESCE(t.output,0)"; // KHÔNG *60 để khớp get_summary_report
                $whereProc   = "d.display_type='blister'";
                $capSql      = "SELECT SUM(COALESCE(capacity,0)) FROM devices WHERE display_type='blister'";
                $extraWhere  = "1=1";
            }

            // Helper sum output
            $sumOutInRange = function($fromQ, $toQ) use ($pdo, $tbl, $joinOnLocal, $whereProc, $extraWhere, $sumOutExpr, $proc) {
                $sql = "
                    SELECT SUM({$sumOutExpr}) AS sum_output
                    FROM {$tbl} t
                    JOIN devices d {$joinOnLocal}
                    WHERE {$whereProc}
                      AND {$extraWhere}
                      AND t.`datetime` >= :fromQ
                      AND t.`datetime` <  :toQ
                ";
                $st = $pdo->prepare($sql);
                $st->execute([':fromQ'=>$fromQ, ':toQ'=>$toQ]);
                return (float)($st->fetchColumn() ?? 0.0);
            };

            $sumCapPerHour = (float)($pdo->query($capSql)->fetchColumn() ?? 0.0);
            $sumDay        = $sumOutInRange($day_from_q,   $day_to_q);
            $sumNight      = $sumOutInRange($night_from_q, $night_to_q);

            $effDay   = ($sumCapPerHour > 0) ? round(($sumDay   / ($sumCapPerHour * $hDay  )) * 100, 2) : 0.0;
            $effNight = ($sumCapPerHour > 0) ? round(($sumNight / ($sumCapPerHour * $hNight)) * 100, 2) : 0.0;
            $effAvg   = ($sumCapPerHour > 0) ? round((($sumDay + $sumNight) / ($sumCapPerHour * $hAll)) * 100, 2) : 0.0;

            return [
                'day'     => $effDay,
                'night'   => $effNight,
                'average' => $effAvg,
            ];
        };

        // Tính summary cho cả 3 process
        $summary_all = [
            'mold'    => $computeSummaryFor('mold'),
            'tuft'    => $computeSummaryFor('tuft'),
            'blister' => $computeSummaryFor('blister'),
        ];
        // Giữ 'summary' = process đang chọn (để UI cũ dùng)
        $summary_current = $summary_all[$process_type] ?? ['day'=>0,'night'=>0,'average'=>0];

        /* ========= 4) Response ========= */
        $response = [
            // SUMMARY_ALL cho cả 3 process như bạn yêu cầu
            'summary_all'  => $summary_all,

            // DETAILS = theo from/to datepicker
            'details' => $details_data,

            // Debug info
            '_range_details' => [
                'from_local' => $fromLocal->format('Y-m-d H:i:s'),
                'to_local'   => $toLocal->format('Y-m-d H:i:s'),
                'hours'      => round($hours_total, 4),
                'db_is_utc'  => $DB_IS_UTC,
            ],
            '_range_summary_windows' => [
                'day_from_vn'   => $day_from_vn->format('Y-m-d H:i:s'),
                'day_to_vn'     => $day_to_vn->format('Y-m-d H:i:s'),
                'night_from_vn' => $night_from_vn->format('Y-m-d H:i:s'),
                'night_to_vn'   => $night_to_vn->format('Y-m-d H:i:s'),
                'h_day'         => $hDay,
                'h_night'       => $hNight,
                'h_total'       => $hAll,
            ],
        ];

        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($response, JSON_NUMERIC_CHECK);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'get_efficiency_report_data failed: '.$e->getMessage()]);
        exit;
    }
}


 elseif ($action === 'get_output_report') {
    try {
        // ---------- Timezone (VN) ----------
        $tzVN = new DateTimeZone('Asia/Ho_Chi_Minh');
        // ---------- Helpers ----------
        if (!function_exists('parseLocal')) {
            function parseLocal(?string $s, DateTimeZone $tz): ?DateTime {
                if (!$s) return null;
                try { return new DateTime(str_replace('T',' ', $s), $tz); }
                catch (Throwable $e) { return null; }
            }
        }

        // SUM output cho 1 process trong [fromLocal,toLocal) GIỜ VN
        if (!function_exists('sum_output_block_local')) {
            function sum_output_block_local(PDO $pdo, string $process, string $fromLocal, string $toLocal, string $family_filter=''): float {
                $isMold = ($process === 'mold');
                $table  = ['mold'=>'mold','tuft'=>'tuft','blister'=>'blister'][$process] ?? 'mold';

                $joinOn = $isMold
                    ? "BINARY t.mold_id  = BINARY d.device_id"
                    : "BINARY t.device_id = BINARY d.device_id";

                $procFilter = $isMold
                    ? "AND d.process IN ('Single','1st') AND (t.cycle_time > 20)"
                    : ($process === 'tuft'
                        ? "AND (BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')"
                        : "");

                $outCol = $isMold ? "t.cavities" : "t.output";

                $sql = "
                    SELECT SUM(COALESCE($outCol,0)) AS sum_output
                    FROM $table t
                    JOIN devices d ON $joinOn
                    WHERE d.display_type = :proc
                      $procFilter
                      AND t.`datetime` >= :from AND t.`datetime` < :to
                ";
                $params = [':proc'=>$process, ':from'=>$fromLocal, ':to'=>$toLocal];

                if ($family_filter !== '') {
                    $sql .= " AND d.product LIKE :fam";
                    $params[':fam'] = '%'.$family_filter.'%';
                }

                $st = $pdo->prepare($sql);
                $st->execute($params);
                return (float)($st->fetchColumn() ?? 0);
            }
        }

        // ---------- Inputs ----------
        $process_type  = $_GET['process'] ?? 'mold';   // mold | tuft | blister
        $family_filter = $_GET['family']  ?? '';
        $from_raw      = $_GET['from']    ?? '';
        $to_raw        = $_GET['to']      ?? '';

        // ---------- Parse time theo VN ----------
        $fromVN = parseLocal($from_raw, $tzVN);
        $toVN   = parseLocal($to_raw,   $tzVN);

        // Fallback: nếu thiếu from/to -> 07:00 hôm qua → 07:00 hôm nay
        if (!$fromVN || !$toVN) {
            $base = new DateTime('today 07:00:00', $tzVN);
            $fromVN = $fromVN ?: (clone $base)->modify('-1 day');
            $toVN   = $toVN   ?: clone $base;
        }
        if ($fromVN >= $toVN) $toVN = (clone $fromVN)->modify('+1 hour');

        // ---------- Cắt các đoạn day/night theo GIỜ VN ----------
        $segments = (function(DateTime $fromVN, DateTime $toVN) {
            $segments = [];
            $cursor = (clone $fromVN)->setTime(0,0,0);
            while ($cursor < $toVN) {
                $dayStart   = (clone $cursor)->setTime(7,0,0);
                $dayEnd     = (clone $cursor)->setTime(19,0,0);
                $nightStart = (clone $cursor)->setTime(19,0,0);
                $nightEnd   = (clone $cursor)->modify('+1 day')->setTime(7,0,0);

                $dFrom = max($dayStart,   $fromVN);
                $dTo   = min($dayEnd,     $toVN);
                $nFrom = max($nightStart, $fromVN);
                $nTo   = min($nightEnd,   $toVN);

                if ($dFrom < $dTo) $segments[] = ['type'=>'day',
                    'fromStr'=>$dFrom->format('Y-m-d H:i:s'), 'toStr'=>$dTo->format('Y-m-d H:i:s')];
                if ($nFrom < $nTo) $segments[] = ['type'=>'night',
                    'fromStr'=>$nFrom->format('Y-m-d H:i:s'), 'toStr'=>$nTo->format('Y-m-d H:i:s')];

                $cursor = (clone $cursor)->modify('+1 day')->setTime(0,0,0);
            }
            return $segments;
        })($fromVN, $toVN);

        // ---------- SUMMARY: Lost = chênh lệch giữa các process ----------
        $daySum   = ['mold'=>0.0,'tuft'=>0.0,'blister'=>0.0];
        $nightSum = ['mold'=>0.0,'tuft'=>0.0,'blister'=>0.0];

        foreach ($segments as $seg) {
            foreach (['mold','tuft','blister'] as $p) {
                $sum = sum_output_block_local($pdo, $p, $seg['fromStr'], $seg['toStr'], $family_filter);
                if     ($seg['type']==='day')   $daySum[$p]   += $sum;
                else /* night */                 $nightSum[$p] += $sum;
            }
        }

        $day_out   = $daySum[$process_type];
        $night_out = $nightSum[$process_type];

        $prevOf = ['mold'=>null, 'tuft'=>'mold', 'blister'=>'tuft'];
        $prev   = $prevOf[$process_type];

        if ($prev === null) {
            $day_lost = 0; $night_lost = 0;
            $day_loss_pct = 0; $night_loss_pct = 0; $total_loss_pct = 0;
        } else {
            $day_base   = max(0, (int)round($daySum[$prev]));
            $night_base = max(0, (int)round($nightSum[$prev]));
            $day_curr   = (int)round($day_out);
            $night_curr = (int)round($night_out);

            $day_lost   = max(0, $day_base   - $day_curr);
            $night_lost = max(0, $night_base - $night_curr);

            $day_loss_pct   = $day_base   > 0 ? round($day_lost   / $day_base   * 100, 2) : 0;
            $night_loss_pct = $night_base > 0 ? round($night_lost / $night_base * 100, 2) : 0;

            $total_base     = $day_base + $night_base;
            $total_loss_pct = $total_base > 0 ? round(($day_lost + $night_lost) / $total_base * 100, 2) : 0;
        }

        $summary_data = [
            'day_output'         => (int)round($day_out),
            'night_output'       => (int)round($night_out),
            'total_output'       => (int)round($day_out + $night_out),
            'day_lost'           => (int)$day_lost,
            'night_lost'         => (int)$night_lost,
            'total_lost'         => (int)($day_lost + $night_lost),
            'day_loss_percent'   => $day_loss_pct,
            'night_loss_percent' => $night_loss_pct,
            'total_loss_percent' => $total_loss_pct,
        ];

        // ---------- DETAILS: dùng VN-bounds ----------
        $data_table = ['mold'=>'mold','tuft'=>'tuft','blister'=>'blister'][$process_type] ?? 'mold';
        $joinOn     = ($process_type === 'mold')
            ? "BINARY t.mold_id  = BINARY d.device_id"
            : "BINARY t.device_id = BINARY d.device_id";

        $procFilterDetails = '';
        if ($process_type === 'mold') {
            $procFilterDetails = "AND d.process IN ('Single','1st') AND (t.cycle_time > 20)";
        } elseif ($process_type === 'tuft') {
            $procFilterDetails = "AND (BINARY d.process LIKE BINARY '%Single%' OR BINARY d.process LIKE BINARY '%1st%')";
        }

        $details_where = "
            d.display_type = :proc
            AND t.`datetime` >= :from AND t.`datetime` < :to
            $procFilterDetails
        ";
        $params = [
            ':proc'=>$process_type,
            ':from'=>$fromVN->format('Y-m-d H:i:s'),
            ':to'  =>$toVN->format('Y-m-d H:i:s')
        ];

        if (!empty($family_filter)) {
            $details_where .= " AND d.product LIKE :fam";
            $params[':fam'] = '%'.$family_filter.'%';
        }

        $output_col = ($process_type === 'mold') ? 't.cavities' : 't.output';

        $details_sql = "
            SELECT
                X.machine_id,
                X.family,
                X.process,
                X.mold_cavity,
                X.capacity,
                X.target,
                X.upper_limit,
                X.lower_limit,

                X.output,
                X.subtotal AS SubTotal,

                ROUND(CASE WHEN X.cap_expected > 0
                    THEN (X.output / X.cap_expected) * 100 ELSE 0 END, 2) AS efficiency,

                GREATEST(0, X.cap_expected - X.output) AS total_lost_pcs,
                ROUND(CASE WHEN X.capacity > 0
                    THEN GREATEST(0, (X.cap_expected - X.output) / (X.capacity/60.0))
                    ELSE 0 END, 0) AS lost_time,

                X.current_cycle

            FROM (
                SELECT
                    d.device_id   AS machine_id,
                    d.product     AS family,
                    d.process,
                    d.cavities    AS mold_cavity,
                    COALESCE(d.capacity,0)      AS capacity,
                    d.target_limit AS target,
                    d.upper_limit, d.lower_limit,

                    SUM(COALESCE($output_col,0)) AS output,
                    SUM(COALESCE($output_col,0)) AS subtotal,

                    GREATEST(1, TIMESTAMPDIFF(MINUTE, MIN(t.`datetime`), MAX(t.`datetime`))) AS device_minutes,
                    (COALESCE(d.capacity,0) * GREATEST(1, TIMESTAMPDIFF(MINUTE, MIN(t.`datetime`), MAX(t.`datetime`))) / 60.0) AS cap_expected,

                    " . ($process_type === 'mold'
                        ? "AVG(t.cycle_time)"
                        : "AVG($output_col)") . " AS current_cycle

                FROM {$data_table} t
                JOIN devices d ON {$joinOn}
                WHERE $details_where
                GROUP BY d.device_id, d.product, d.process, d.cavities, d.capacity, d.target_limit, d.upper_limit, d.lower_limit
            ) X
            ORDER BY X.family, X.machine_id
        ";
        $stmt = $pdo->prepare($details_sql);
        $stmt->execute($params);
        $details_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // ---------- Subtotal theo family & family_counts ----------
        $family_subtotals = [];
        foreach ($details_data as $item) {
            $fam = $item['family'] ?? 'Unknown';
            $family_subtotals[$fam] = ($family_subtotals[$fam] ?? 0) + (float)($item['output'] ?? 0);
        }
        usort($details_data, fn($a,$b)=>($a['family'] ?? '') <=> ($b['family'] ?? ''));
        $final_details_data = [];
        $family_counts = [];
        $n = count($details_data);
        for ($i=0;$i<$n;$i++) {
            $row = $details_data[$i];
            $fam = $row['family'] ?? 'Unknown';
            $family_counts[$fam] = ($family_counts[$fam] ?? 0) + 1;
            $row['subtotal'] = null;
            if ($i+1 >= $n || $details_data[$i+1]['family'] !== $fam) {
                $row['subtotal'] = $family_subtotals[$fam];
            }
            $final_details_data[] = $row;
        }
        $lastSubtotalByFamily = [];
        foreach ($final_details_data as $it) {
            if ($it['subtotal'] !== null && $it['subtotal'] !== '') {
                $lastSubtotalByFamily[$it['family']] = $it['subtotal'];
            }
        }
        $firstIndexByFamily = [];
        foreach ($final_details_data as $idx=>$it) {
            if (!isset($firstIndexByFamily[$it['family']])) $firstIndexByFamily[$it['family']] = $idx;
        }
        foreach ($firstIndexByFamily as $fam=>$idx) {
            if (isset($lastSubtotalByFamily[$fam])) $final_details_data[$idx]['subtotal'] = $lastSubtotalByFamily[$fam];
        }
        $details_data = $final_details_data;

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        echo json_encode([
            'summary'       => $summary_data,
            'details'       => $details_data,
            'family_counts' => $family_counts
        ], JSON_NUMERIC_CHECK);
        exit;

    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['status'=>'error','message'=>$e->getMessage()]);
        exit;
    }
}

/************** ACTIONS API (BACKEND + INDEX) **************/
elseif ($action === 'actions_board') {
  header('Content-Type: application/json; charset=utf-8');

  $tab     = $_GET['tab']     ?? 'active';   // 'active' | 'done'
  $process = $_GET['process'] ?? 'all';      // 'all' | 'mold' | 'tuft' | 'blister' | 'injection' | 'end-rounding'

  $tab = ($tab === 'done') ? 'done' : 'active';
  $allowProc = ['all','mold','tuft','blister','injection','end-rounding'];
  if (!in_array($process, $allowProc, true)) $process = 'all';

  // Tổng hợp trạng thái từng ACTION (dựa trên plan: done khi plans_done = plans_total và >0)
  $sql = "
    SELECT 
      dv.display_type,
      dv.device_id,
      dv.product,
      dv.process,
      SUM(CASE WHEN ag.plans_total > 0 AND ag.plans_done = ag.plans_total THEN 1 ELSE 0 END)  AS done_count,
      SUM(CASE WHEN (ag.plans_total = 0 OR ag.plans_done < ag.plans_total) THEN 1 ELSE 0 END) AS open_count,
      MAX(ag.latest_due) AS latest_due
    FROM (
      SELECT 
        da.id,
        da.device_id,
        COALESCE(SUM(CASE WHEN dap.status='done' THEN 1 ELSE 0 END), 0) AS plans_done,
        COALESCE(COUNT(dap.id), 0) AS plans_total,
        MAX(dap.est_date) AS latest_due
      FROM device_actions da
      LEFT JOIN device_action_plans dap ON dap.action_id = da.id
      WHERE da.status <> 'cancelled'
      GROUP BY da.id, da.device_id
    ) ag
    JOIN devices dv ON dv.device_id = ag.device_id
    " . ($process !== 'all' ? "WHERE dv.display_type = :proc " : "") . "
    GROUP BY dv.display_type, dv.device_id, dv.product, dv.process
    HAVING " . ($tab === 'done' ? "done_count > 0" : "open_count > 0") . "
    ORDER BY dv.display_type, dv.device_id
  ";

  $stmt = $pdo->prepare($sql);
  if ($process !== 'all') $stmt->bindValue(':proc', $process);
  $stmt->execute();
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  $data = array_map(function($r) use ($tab){
    return [
      'type'       => $r['display_type'],
      'device_id'  => $r['device_id'],
      'product'    => $r['product'],
      'process'    => $r['process'],
      'open'       => ($tab === 'done') ? (int)$r['done_count'] : (int)$r['open_count'],
      'latest_due' => $r['latest_due'] ?: null,
    ];
  }, $rows);

  echo json_encode(['tab'=>$tab, 'process'=>$process, 'devices'=>$data], JSON_UNESCAPED_UNICODE);
  exit;
}

elseif ($action === 'list_users') {
  requireAdmin();
  $stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
  echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC)); exit;
}

elseif ($action === 'create_device_action') {
    capture_current_endpoint();
//   $u = requireRolesOr403(['admin','maintenance']);
  $creator = (int)($u['id'] ?? 0);

  $device_id  = trim($_POST['device_id'] ?? '');
  $title      = trim($_POST['title'] ?? '');
  $priority   = $_POST['priority'] ?? 'medium';
  $due_date   = $_POST['due_date'] ?? null;
  $assignee   = ($_POST['assigned_to_user_id'] ?? '') !== '' ? (int)$_POST['assigned_to_user_id'] : null;
  $short_form = $_POST['short_form'] ?? '[]';

  if (!$device_id || !$title) { echo json_encode(['error'=>'Missing device_id/title']); exit; }
  $sf = json_decode($short_form, true);
  if (!is_array($sf)) { echo json_encode(['error'=>'short_form must be JSON array']); exit; }

  $sql = "INSERT INTO device_actions
          (device_id,title,short_form,status,priority,due_date,assigned_to_user_id,created_by_user_id)
          VALUES (?,?,?,?,?,?,?,?)";
  $stmt = $pdo->prepare($sql);
  $stmt->execute([$device_id,$title,json_encode($sf,JSON_UNESCAPED_UNICODE),
                  'open',$priority,$due_date ?: null,$assignee,$creator]);
  echo json_encode(['ok'=>true,'id'=>$pdo->lastInsertId()]); exit;
}


// elseif ($action === 'update_device_action_status') {
//   requireRolesOr403(['admin','maintenance']);
//   $id     = (int)($_POST['action_id'] ?? 0);
//   $status = $_POST['status'] ?? 'open';
//   if (!$id) { echo json_encode(['error'=>'Missing action_id']); exit; }
//   if (!in_array($status, ['open','in_progress','done','cancelled'], true)) {
//     echo json_encode(['error'=>'Invalid status']); exit;
//   }
//   $stmt = $pdo->prepare("UPDATE device_actions SET status=? WHERE id=?");
//   $stmt->execute([$status,$id]);
//   echo json_encode(['ok'=>true]); exit;
// }

/* === PATCH: update_action_status (Complete / Incomplete) === */
// if (isset($_POST['action']) && $_POST['action'] === 'update_action_status') {
//     $u = requireRolesOr403(['admin','maintenance']);
//     $id = (int)($_POST['action_id'] ?? 0);
//     $newStatus = strtolower(trim($_POST['status'] ?? ''));

//     if ($id <= 0 || !in_array($newStatus, ['open', 'in_progress', 'done', 'cancelled'], true)) {
//         echo json_encode(['status'=>'error','message'=>'Invalid params']); exit;
//     }

//     if ($newStatus === 'done') {
//         // Chỉ đánh dấu DONE, KHÔNG tự duyệt
//         $stmt = $pdo->prepare("UPDATE device_actions SET status='done' WHERE id=?");
//         $stmt->execute([$id]);
//     } elseif ($newStatus === 'open') {
//         // Trả về OPEN => reset lại approval
//         $stmt = $pdo->prepare("
//             UPDATE device_actions
//                SET status='open',
//                    approval_status=NULL,
//                    approved_by_user_id=NULL,
//                    approved_by_name=NULL,
//                    approved_at=NULL,
//                    approval_note=NULL
//              WHERE id=?");
//         $stmt->execute([$id]);
//     } else {
//         // Các trạng thái khác giữ nguyên hành vi (nếu có)
//         $stmt = $pdo->prepare("UPDATE device_actions SET status=? WHERE id=?");
//         $stmt->execute([$newStatus, $id]);
//     }

//     echo json_encode(['status'=>'success']); exit;
// }

// === Cập nhật trạng thái 1 action plan
elseif (($_POST['action'] ?? '') === 'update_action_plan_status') {
  capture_current_endpoint();
  header('Content-Type: application/json; charset=utf-8');

  // Bật nếu bạn MUỐN log riêng cho auto-recalc device_actions
  if (!defined('ENABLE_ISSUE_AUTORECALC_AUDIT')) define('ENABLE_ISSUE_AUTORECALC_AUDIT', false);

//   $u   = requireRolesOr403(['admin','maintenance']);
  $WHO = $u['username'] ?? ('user-'.($u['id'] ?? 0));

  $planId     = (int)($_POST['plan_id'] ?? 0);
  $status     = (($_POST['status'] ?? 'open') === 'done') ? 'done' : 'open';
  $userReason = trim((string)($_POST['reason'] ?? ''));

  if ($planId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'plan_id required'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    $pdo->beginTransaction();

    // 1) BEFORE (plan)
    $st = $pdo->prepare("SELECT * FROM device_action_plans WHERE id=?");
    $st->execute([$planId]);
    $beforePlan = $st->fetch(PDO::FETCH_ASSOC);
    if (!$beforePlan) throw new Exception('Plan not found');

    // 2) Update plan status
    $pdo->prepare("UPDATE device_action_plans SET status=? WHERE id=?")->execute([$status, $planId]);

    // 3) AFTER (plan)
    $st = $pdo->prepare("SELECT * FROM device_action_plans WHERE id=?");
    $st->execute([$planId]);
    $afterPlan = $st->fetch(PDO::FETCH_ASSOC);

    // 4) Recalc ISSUE (device_actions) theo tổng plan
    $aid = (int)($afterPlan['action_id'] ?? $beforePlan['action_id'] ?? 0);
    if ($aid > 0) {
      $st = $pdo->prepare("
        SELECT SUM(status='done') AS done, COUNT(*) AS total
        FROM device_action_plans
        WHERE action_id=?
      ");
      $st->execute([$aid]);
      $agg = $st->fetch(PDO::FETCH_ASSOC);

      // BEFORE (issue)
      $st = $pdo->prepare("SELECT id, status, approval_status FROM device_actions WHERE id=?");
      $st->execute([$aid]);
      $beforeIssue = $st->fetch(PDO::FETCH_ASSOC);

      if ($beforeIssue) {
        $newStatus = $beforeIssue['status'];
        $newAppr   = $beforeIssue['approval_status'];

        if ($agg && (int)$agg['total'] > 0) {
          if ((int)$agg['done'] === 0) {
            $newStatus = 'open';
          } elseif ((int)$agg['done'] < (int)$agg['total']) {
            $newStatus = 'in_progress';
          } else {
            $newStatus = 'done';
            $newAppr   = ($beforeIssue['approval_status'] === 'approved') ? 'approved' : 'pending';
          }
        }

        if ($newStatus !== $beforeIssue['status'] || $newAppr !== $beforeIssue['approval_status']) {
          $pdo->prepare("UPDATE device_actions SET status=?, approval_status=? WHERE id=?")
              ->execute([$newStatus, $newAppr, $aid]);

          // AFTER (issue)
          if (ENABLE_ISSUE_AUTORECALC_AUDIT && isset($audit) && $audit instanceof AuditTrail) {
            $st = $pdo->prepare("SELECT id, status, approval_status FROM device_actions WHERE id=?");
            $st->execute([$aid]);
            $afterIssue = $st->fetch(PDO::FETCH_ASSOC);

            $audit->log(
              'update',
              'device_actions:auto_status_recalc#'.$aid,
              $beforeIssue,
              $afterIssue,
              $WHO
            );
          }
        }
      }
    }

    // 5) Chuẩn bị REASON cho audit.reason (ưu tiên user nhập)
    $reasonForAudit = ($userReason !== '') ? $userReason : ('device_action_plans:status#'.$planId);
    if (function_exists('mb_strlen') && function_exists('mb_substr')) {
      if (mb_strlen($reasonForAudit) > 100) $reasonForAudit = mb_substr($reasonForAudit, 0, 100);
    } else {
      if (strlen($reasonForAudit) > 100) $reasonForAudit = substr($reasonForAudit, 0, 100);
    }

    // 6) Gắn snapshot để frontend render bảng gọn (không cần trước/sau N/A)
    $snapshot = [
      'plan_id'   => (int)($afterPlan['id'] ?? $beforePlan['id'] ?? 0),
      'plan_code' => (string)($afterPlan['plan_code'] ?? $beforePlan['plan_code'] ?? ''),
      'plan_text' => (string)($afterPlan['plan_text'] ?? $beforePlan['plan_text'] ?? ''),
      'est_date'  => (string)($afterPlan['est_date'] ?? $beforePlan['est_date'] ?? ''),
      'owner'     => (string)($afterPlan['owner_name'] ?? $afterPlan['owner'] ?? $beforePlan['owner_name'] ?? $beforePlan['owner'] ?? ''),
    ];
    // Ép diff có khóa __plan_snapshot bằng cách để BEFORE=null, AFTER=có dữ liệu
    $beforePlan['__plan_snapshot'] = null;
    $afterPlan['__plan_snapshot']  = $snapshot;

    // 7) AUDIT: chỉ GHI 1 LẦN cho việc đổi trạng thái plan
    if (isset($audit) && $audit instanceof AuditTrail) {
      $audit->log(
        'update',
        $reasonForAudit,   // -> cột audit_trail.reason (ghi đúng lý do user nhập)
        $beforePlan,
        $afterPlan,
        $WHO
      );
    }

    $pdo->commit();
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// TRONG api.php
elseif (($action ?? '') === 'count_device_status') {
    try {
        $process_type = $_GET['process'] ?? 'mold';
        $client_filter = $_GET['family'] ?? ''; // App.js gửi client qua param 'family'
        $now = time();

        $sql = "
            SELECT 
                d.device_id, d.display_type, d.client,
                d.target_limit, d.upper_limit, d.lower_limit, d.efficiency_lower_limit, d.frequency, 
                d.cavities AS mold_cavity,
                ldd.live_data, ldd.last_updated
            FROM devices d
            LEFT JOIN live_device_data ldd ON d.device_id = ldd.device_id
            WHERE d.display_type = :proc
        ";
        
        // Thêm lọc theo Client nếu có
        if ($client_filter !== '' && $client_filter !== 'all') {
            $sql .= " AND d.client = :client";
        }

        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':proc', $process_type);
        if ($client_filter !== '' && $client_filter !== 'all') {
            $stmt->bindValue(':client', $client_filter);
        }
        $stmt->execute();
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $counts = ['total' => 0, 'connected' => 0, 'normal' => 0, 'breached' => 0, 'disconnected' => 0];

        foreach ($devices as $device) {
            $counts['total']++;
            $live = json_decode($device['live_data'] ?? '{}', true) ?: [];

            // Tính heartbeat
            $ts = isset($live['datetime']) ? strtotime($live['datetime']) : (!empty($device['last_updated']) ? strtotime($device['last_updated']) : 0);
            $freq = (int)($device['frequency'] ?? 300) ?: 300;
            
            if ($ts > 0 && ($now - $ts) <= $freq) {
                // Đang Online -> Check vi phạm (Breached)
                $isBreached = false;
                $eff = 0; $val = 0;

                if ($process_type === 'mold') {
                    $res = calculateMoldMetrics($live, $device);
                    $eff = $res['efficiency'];
                    $val = (float)($live['cycle_time'] ?? 0);
                } elseif ($process_type === 'tuft') {
                    $res = calculateTuftMetrics($live, $device);
                    $eff = $res['efficiency'];
                    $val = (float)($live['output'] ?? $live['rate'] ?? 0);
                } elseif ($process_type === 'blister') {
                    $res = calculateBlisterMetrics($live, $device);
                    $eff = $res['efficiency'];
                    $val = (float)($live['cyclecount'] ?? 0);
                }

                if (((float)$device['efficiency_lower_limit'] > 0 && $eff < (float)$device['efficiency_lower_limit']) ||
                    ((float)$device['lower_limit'] > 0 && $val > 0 && $val < (float)$device['lower_limit']) ||
                    ((float)$device['upper_limit'] > 0 && $val > (float)$device['upper_limit'])) {
                    $isBreached = true;
                }

                if ($isBreached) $counts['breached']++; else $counts['normal']++;
            } else {
                $counts['disconnected']++;
            }
        }
        $counts['connected'] = $counts['normal'] + $counts['breached'];
        send_json($counts);
    } catch (Throwable $e) {
        send_json_error($e->getMessage(), 500);
    }
}


// === Xóa 1 action plan
elseif (($_POST['action'] ?? '') === 'delete_action_plan') {
  capture_current_endpoint();
  header('Content-Type: application/json; charset=utf-8');

//   $u = requireRolesOr403(['admin']);
  $WHO = $u['username'] ?? ('user-'.($u['id'] ?? 0));

  $planId = (int)($_POST['plan_id'] ?? 0);
  if ($planId <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'plan_id required'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  try {
    $pdo->beginTransaction();

    // BEFORE (plan)
    $st = $pdo->prepare("SELECT * FROM device_action_plans WHERE id=?");
    $st->execute([$planId]);
    $beforePlan = $st->fetch(PDO::FETCH_ASSOC);
    if (!$beforePlan) throw new Exception('Plan not found');
    $aid = (int)$beforePlan['action_id'];

    // >>> NEW: lấy user reason & chuẩn bị cho audit.reason
    $userReason = trim((string)($_POST['reason'] ?? ''));
    $context    = 'device_action_plans:delete#'.$planId;
    $reasonForAudit = $userReason !== '' ? $userReason : $context;
    if (mb_strlen($reasonForAudit) > 100) $reasonForAudit = mb_substr($reasonForAudit, 0, 100);

    // DELETE
    $pdo->prepare("DELETE FROM device_action_plans WHERE id=?")->execute([$planId]);

    // AUDIT (delete plan) — ghi user reason vào cột `reason`
    if (isset($audit) && $audit instanceof AuditTrail) {
      $audit->log(
        'delete',
        $reasonForAudit,   // <<< ghi vào cột audit.reason
        $beforePlan,
        [],                // after rỗng
        $WHO
      );
    }

    // === Recalc ISSUE giữ nguyên như bạn ===
    $st = $pdo->prepare("
      SELECT SUM(status='done') AS done, COUNT(*) AS total
      FROM device_action_plans
      WHERE action_id=?
    ");
    $st->execute([$aid]);
    $agg = $st->fetch(PDO::FETCH_ASSOC);

    $st = $pdo->prepare("SELECT id, status, approval_status FROM device_actions WHERE id=?");
    $st->execute([$aid]);
    $beforeIssue = $st->fetch(PDO::FETCH_ASSOC);

    if ($beforeIssue) {
      $newStatus = $beforeIssue['status'];
      $newAppr   = $beforeIssue['approval_status'];

      if ($agg && (int)$agg['total'] > 0) {
        if ((int)$agg['done'] === 0) $newStatus = 'open';
        elseif ((int)$agg['done'] < (int)$agg['total']) $newStatus = 'in_progress';
        else { $newStatus = 'done'; $newAppr = ($beforeIssue['approval_status'] === 'approved') ? 'approved' : 'pending'; }
      } else {
        // giữ nguyên theo rule hiện tại
      }

      if ($newStatus !== $beforeIssue['status'] || $newAppr !== $beforeIssue['approval_status']) {
        $pdo->prepare("UPDATE device_actions SET status=?, approval_status=? WHERE id=?")
            ->execute([$newStatus, $newAppr, $aid]);

        $st = $pdo->prepare("SELECT id, status, approval_status FROM device_actions WHERE id=?");
        $st->execute([$aid]);
        $afterIssue = $st->fetch(PDO::FETCH_ASSOC);

        if (isset($audit) && $audit instanceof AuditTrail) {
          $audit->log('update', 'device_actions:auto_status_recalc#'.$aid.' (after plan delete)', $beforeIssue, $afterIssue, $WHO);
        }
      }
    }

    $pdo->commit();
    echo json_encode(['success' => true], JSON_UNESCAPED_UNICODE);
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}




// ===== Approve / Reject (hợp nhất) =====
if (isset($_POST['action']) && ($_POST['action'] === 'approve_action' || $_POST['action'] === 'reject_action')) {
  capture_current_endpoint();
  header('Content-Type: application/json; charset=utf-8');

  try {
    $u = requireManagerOrAdmin();

    $id       = (int)($_POST['action_id'] ?? 0);
    $decision = ($_POST['action'] === 'reject_action' || ($_POST['decision'] ?? '') === 'rejected')
                ? 'rejected' : 'approved';
    $note     = trim((string)($_POST['note'] ?? ''));

    if ($id <= 0) { echo json_encode(['status'=>'error','message'=>'Invalid action_id']); exit; }

    $uid   = (int)($u['id'] ?? 0);
    $uname = $u['username'] ?? ('user-'.$uid);

    $pdo->beginTransaction();

    // BEFORE
    $st = $pdo->prepare("SELECT * FROM device_actions WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $beforeRow = $st->fetch(PDO::FETCH_ASSOC);
    if (!$beforeRow) {
      throw new Exception('Action not found');
    }

    // Cập nhật: nếu APPROVED thì set luôn status='done'
    $sql = "
      UPDATE device_actions
         SET status              = IF(:decision='approved','done',status),
             approval_status     = :decision,
             approved_by_user_id = :uid,
             approved_by_name    = :uname,
             approved_at         = NOW(),
             approval_note       = :note
       WHERE id = :id
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
      ':decision' => $decision,
      ':uid'      => $uid,
      ':uname'    => $uname,
      ':note'     => $note,
      ':id'       => $id
    ]);

    // AFTER
    $st = $pdo->prepare("SELECT * FROM device_actions WHERE id = ? LIMIT 1");
    $st->execute([$id]);
    $afterRow = $st->fetch(PDO::FETCH_ASSOC);

    // AUDIT
    if (isset($audit) && $audit instanceof AuditTrail) {
      $reason = 'device_actions:' . ($decision === 'approved' ? 'approve' : 'reject') . '#' . $id;
      $audit->log('update', $reason, $beforeRow, $afterRow, $uname);
    }

    $pdo->commit();
    echo json_encode(['status' => 'success']); 
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

// ===================== BACKEND: list 11 columns =====================
// GET ?action=list_backend_issues
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'list_backend_issues')) {
  capture_current_endpoint();
//   $u = requireRolesOr403(['admin','manager']);
  header('Content-Type: application/json; charset=utf-8');

  $display = $_GET['display_type'] ?? 'all';
  $where   = '';
  $params  = [];
  if ($display !== 'all') {
    $where   = ' AND d.display_type = ? ';
    $params[] = $display;
  }

  $sql = <<<SQL
SELECT
  a.id                                                   AS action_pk,
  d.display_type                                         AS type,                       -- Machine Type
  a.device_id                                            AS device,                     -- Device
  a.issue_type                                           AS issue_type,                 -- Issue Type
  COALESCE(a.action_code, CONCAT('AC', LPAD(a.id,5,'0'))) AS issue_id,                  -- Issue ID
  COALESCE(JSON_UNQUOTE(JSON_EXTRACT(a.short_form, '$[0].value')), a.title, '') AS description_of_issue,
  a.created_at                                           AS created_at,                 -- Created Day

  /* KHÔNG dùng due_date nữa: chỉ lấy từ các kế hoạch */
  COALESCE(p_next.est_date, p_last.est_date)             AS planned_completion_date,    -- Planned completion date

  COALESCE(u.username, '')                               AS created_by,                 -- Creator
  a.status                                               AS action_status,              -- raw status (tham khảo)
  a.approval_status                                      AS approval_status,            -- raw approval (tham khảo)

  CASE                                                   -- Status hiển thị (Complete/In progress)
    WHEN COALESCE(c.plans_total,0) > 0
     AND COALESCE(c.plans_done,0)  = COALESCE(c.plans_total,0)
    THEN 'complete' ELSE 'in_progress'
  END                                                    AS status_display,

  COALESCE(c.plans_done,0)                               AS plans_done,                 -- Action plans (done/total)
  COALESCE(c.plans_total,0)                              AS plans_total,

  -- (thông tin tham khảo)
  COALESCE(d.product, 'Common')                          AS product,
  d.process                                              AS process

FROM device_actions a
JOIN devices d ON d.device_id = a.device_id
LEFT JOIN users u ON u.id = a.created_by_user_id

/* Kế hoạch OPEN gần nhất (est_date nhỏ nhất) nếu có */
LEFT JOIN (
  SELECT x.action_id, x.plan_text, x.est_date
  FROM device_action_plans x
  JOIN (
    SELECT action_id, MIN(est_date) AS est_date
    FROM device_action_plans
    WHERE (status IS NULL OR status <> 'done') AND est_date IS NOT NULL
    GROUP BY action_id
  ) y ON y.action_id = x.action_id AND y.est_date = x.est_date
) p_next ON p_next.action_id = a.id

/* Nếu không có OPEN thì lấy kế hoạch DONE muộn nhất */
LEFT JOIN (
  SELECT x.action_id, x.plan_text, x.est_date
  FROM device_action_plans x
  JOIN (
    SELECT action_id, MAX(est_date) AS est_date
    FROM device_action_plans
    WHERE status = 'done' AND est_date IS NOT NULL
    GROUP BY action_id
  ) y ON y.action_id = x.action_id AND y.est_date = x.est_date
) p_last ON p_last.action_id = a.id

/* Đếm kế hoạch */
LEFT JOIN (
  SELECT action_id,
         SUM(status='done') AS plans_done,
         COUNT(*)           AS plans_total
  FROM device_action_plans
  GROUP BY action_id
) c ON c.action_id = a.id

WHERE a.status <> 'cancelled' {$where}
ORDER BY d.display_type, a.device_id,
         planned_completion_date IS NULL, planned_completion_date
SQL;

  try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
  }
  exit;
}



/* === END PATCH === */
elseif (($action ?? '') === 'preview_next_codes') {
    capture_current_endpoint();
  header('Content-Type: application/json; charset=utf-8');
  $nextActionId = (int)$pdo->query("
    SELECT AUTO_INCREMENT FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'device_actions'
  ")->fetchColumn();
  $nextPlanId = (int)$pdo->query("
    SELECT AUTO_INCREMENT FROM information_schema.TABLES
    WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'device_action_plans'
  ")->fetchColumn();
  echo json_encode([
    'next_action_id'   => $nextActionId,
    'next_action_code' => sprintf('ISS%05d', $nextActionId),
    'next_plan_id'     => $nextPlanId,
    'next_plan_code'   => sprintf('AP%05d', $nextPlanId),
  ]);
  exit;
}
elseif ($action === 'whoami') {
  header('Cache-Control: no-store');
  $u = require_auth();
  echo json_encode([
    'logged_in' => true,
    'id'        => (int)($u['id'] ?? 0),
    'username'  => $u['username'] ?? null,
    'role'      => $u['role'] ?? 'user',
    'is_admin'  => strtolower($u['role'] ?? '') === 'admin',
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

elseif (($action ?? '') === 'bulk_update_action_plan_status') {
  capture_current_endpoint();
  header('Content-Type: application/json; charset=utf-8');

  try {
    // $u = requireRolesOr403(['admin','maintenance']);
    $uid   = (int)($u['id'] ?? 0);
    $uname = $u['username'] ?? ('user-'.$uid);

    $actionId = (int)($_POST['action_id'] ?? 0);
    $status   = (strtolower((string)($_POST['status'] ?? 'done')) === 'open') ? 'open' : 'done';
    if ($actionId <= 0) { http_response_code(400); echo json_encode(['error'=>'Missing action_id']); exit; }

    $pdo->beginTransaction();

    // BEFORE: lấy map plan_id => status
    $qPlans = $pdo->prepare("SELECT id, status FROM device_action_plans WHERE action_id = ?");
    $qPlans->execute([$actionId]);
    $plansBefore = $qPlans->fetchAll(PDO::FETCH_ASSOC);
    $beforeMap = [];
    foreach ($plansBefore as $r) {
      $beforeMap[(string)$r['id']] = $r['status'];
    }

    // UPDATE hàng loạt
    $stmt = $pdo->prepare("UPDATE device_action_plans SET status=:st WHERE action_id=:aid");
    $stmt->execute([':st' => $status, ':aid' => $actionId]);
    $affected = $stmt->rowCount();

    // AFTER: lấy lại map plan_id => status
    $qPlans->execute([$actionId]);
    $plansAfter = $qPlans->fetchAll(PDO::FETCH_ASSOC);
    $afterMap = [];
    foreach ($plansAfter as $r) {
      $afterMap[(string)$r['id']] = $r['status'];
    }

    // Recalc trạng thái ISSUE cha (giống logic ở update_action_plan_status)
    $stAgg = $pdo->prepare("SELECT SUM(status='done') AS done, COUNT(*) AS total FROM device_action_plans WHERE action_id=?");
    $stAgg->execute([$actionId]);
    $agg = $stAgg->fetch(PDO::FETCH_ASSOC);

    $parentStatus = null;
    if ($agg && (int)$agg['total'] > 0) {
      if ((int)$agg['done'] === 0) {
        $pdo->prepare("UPDATE device_actions SET status='open' WHERE id=?")->execute([$actionId]);
        $parentStatus = 'open';
      } elseif ((int)$agg['done'] < (int)$agg['total']) {
        $pdo->prepare("UPDATE device_actions SET status='in_progress' WHERE id=?")->execute([$actionId]);
        $parentStatus = 'in_progress';
      } else {
        $pdo->prepare("
          UPDATE device_actions
             SET status='done',
                 approval_status = CASE WHEN approval_status='approved'
                                        THEN 'approved' ELSE 'pending' END
           WHERE id=?")->execute([$actionId]);
        $parentStatus = 'done';
      }
    }

    // AUDIT
    if (isset($audit) && $audit instanceof AuditTrail) {
      $audit->log(
        'update',
        'device_action_plans:bulk_update_status#' . $actionId,
        ['plan_statuses' => $beforeMap],
        ['plan_statuses' => $afterMap, 'parent_action_status_after' => $parentStatus],
        $uname
      );
    }

    $pdo->commit();
    echo json_encode([
      'status'        => 'success',
      'updated_rows'  => $affected,
      'parent_status' => $parentStatus
    ], JSON_UNESCAPED_UNICODE);
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['status'=>'error','message'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}

elseif (($action ?? '') === 'create_action_public') {
  capture_current_endpoint();
  header('Content-Type: application/json; charset=utf-8');

  // 1) Quyền
//   $u = requireRolesOr403(['admin','maintenance']);
  $creatorId   = (int)($u['id'] ?? 0);
  $creatorName = $u['username'] ?? ('user-'.$creatorId);

  // 2) Input
  $deviceId  = trim($_POST['device_id'] ?? '');
  $title     = trim($_POST['title'] ?? '');
  $sf_json   = $_POST['short_form'] ?? '[]';
  $sf        = json_decode($sf_json, true);
  $issueType = trim($_POST['issue_type'] ?? '');

  if ($deviceId === '' || $title === '' || !is_array($sf)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing/invalid fields (device_id/title/short_form)'], JSON_UNESCAPED_UNICODE);
    exit;
  }

  // 3) Helper tách kế hoạch từ short_form
  $extractPlans = function(array $arr): array {
    $plans = [];
    foreach ($arr as $x) {
      $label = strtolower(trim($x['label'] ?? ''));
      $val   = trim($x['value'] ?? '');
      if ($val === '') continue;

      $isPlan = (strpos($label, 'action plan') !== false) || (strpos($label, 'plan #') === 0);
      if (!$isPlan) continue;

      $est = null;
      if (preg_match('/\((\d{4}-\d{2}-\d{2})\)/', ($x['label'] ?? ''), $m))      $est = $m[1];
      elseif (!empty($x['est']))                                                 $est = trim($x['est']);

      $ownerName = isset($x['owner']) && trim($x['owner']) !== '' ? trim($x['owner']) : null;
      $ownerId   = isset($x['owner_user_id']) && $x['owner_user_id'] !== '' ? (int)$x['owner_user_id'] : null;

      $plans[] = [
        'plan_text'     => $val,
        'est_date'      => $est ?: null,
        'issue_note'    => isset($x['issue']) && strlen(trim($x['issue'])) ? trim($x['issue']) : null,
        'owner_user_id' => $ownerId,
        'owner_name'    => $ownerName,
      ];
    }
    return $plans;
  };
  $plans = $extractPlans($sf);

  try {
    $pdo->beginTransaction();

    // 4) Thêm action (ĐÃ GỠ due_date, assigned_to_user_id)
    $stmt = $pdo->prepare("
      INSERT INTO device_actions
        (device_id, title, issue_type, short_form, status, priority,
         created_by_user_id, created_by_name, approval_status, created_at)
      VALUES
        (?, ?, ?, ?, 'open', 'medium',
         ?, ?, 'pending', NOW())
    ");
    $stmt->execute([
      $deviceId,
      $title,
      ($issueType !== '' ? $issueType : null),
      json_encode($sf, JSON_UNESCAPED_UNICODE),
      $creatorId,
      $creatorName,
    ]);

    $actionId   = (int)$pdo->lastInsertId();
    $actionCode = sprintf('ISS%05d', $actionId);
    $pdo->prepare("UPDATE device_actions SET action_code = :c WHERE id = :i")
        ->execute([':c'=>$actionCode, ':i'=>$actionId]);

    // 5) Thêm các plan (nếu có)
    $createdPlans = [];
    if (!empty($plans)) {
      $stmtPlan = $pdo->prepare("
        INSERT INTO device_action_plans
          (action_id, action_code, plan_text, est_date, issue_note, owner_user_id, owner_name)
        VALUES
          (:action_id, :action_code, :plan_text, :est_date, :issue_note, :owner_user_id, :owner_name)
      ");
      $stmtSetCode  = $pdo->prepare("UPDATE device_action_plans SET plan_code = :pc WHERE id = :id");

      foreach ($plans as $p) {
        $planText  = trim($p['plan_text'] ?? '');
        if ($planText === '') continue;

        $estDate   = trim($p['est_date'] ?? '');
        $estDate   = ($estDate !== '' ? $estDate : null);

        $issueNote = trim($p['issue_note'] ?? '');
        $issueNote = ($issueNote !== '' ? $issueNote : null);

        $ownerId   = $p['owner_user_id'] ?? null;
        $ownerId   = ($ownerId === '' ? null : (int)$ownerId);

        $stmtPlan->execute([
          ':action_id'     => $actionId,
          ':action_code'   => $actionCode,
          ':plan_text'     => $planText,
          ':est_date'      => $estDate,
          ':issue_note'    => $issueNote,
          ':owner_user_id' => $ownerId,
          ':owner_name'    => $p['owner_name'] ?? null,
        ]);

        $pid   = (int)$pdo->lastInsertId();
        $pcode = sprintf('AP%05d', $pid);
        $stmtSetCode->execute([':pc' => $pcode, ':id' => $pid]);

        $createdPlans[] = [
          'id'            => $pid,
          'plan_code'     => $pcode,
          'plan_text'     => $planText,
          'est_date'      => $estDate,
          'issue_note'    => $issueNote,
          'owner_user_id' => $ownerId,
          'owner_name'    => $p['owner_name'] ?? null,
        ];
      }
    }


// 6) AUDIT LOG — GỘP VÀO 1 RECORD
if (isset($audit) && $audit instanceof AuditTrail) {
  $after = [
    'id'                 => $actionId,
    'action_code'        => $actionCode,
    'device_id'          => $deviceId,
    'title'              => $title,
    'issue_type'         => ($issueType !== '' ? $issueType : null),
    'short_form'         => $sf,           // array
    'status'             => 'open',
    'priority'           => 'medium',
    'created_by_user_id' => $creatorId,
    'created_by_name'    => $creatorName,
    'approval_status'    => 'pending',
    // >>> GỘP PLANS LUÔN Ở ĐÂY:
    'plans'              => array_map(function($p){
      return [
        'plan_code'  => $p['plan_code']  ?? null,
        'plan_text'  => $p['plan_text']  ?? '',
        'est_date'   => $p['est_date']   ?? null,
        'owner_name' => $p['owner_name'] ?? ($p['owner'] ?? null),
      ];
    }, $createdPlans),
  ];
  $audit->log('create', 'device_actions:create_public#'.$actionId, [], $after, $creatorName);
//   $audit->log('update', 'device_action_plans:update#'.$actionId, ['plans'=>$oldPlans], ['plans'=>$newPlans], $who);
}


    $pdo->commit();
    echo json_encode([
      'status'      => 'success',
      'id'          => $actionId,
      'action_code' => $actionCode,
      'plans'       => $createdPlans
    ], JSON_UNESCAPED_UNICODE);
    exit;

  } catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    http_response_code(500);
    echo json_encode(['error'=>$e->getMessage()], JSON_UNESCAPED_UNICODE);
    exit;
  }
}






// === v2: Danh sách ISSUE có x/y plans & status_auto
elseif ($action === 'list_device_actions_v2') {
  try {
    // if (!$AUTH) send_json_error('auth_required', 401);

    $deviceId = trim($_GET['device_id'] ?? '');
    if ($deviceId === '') send_json_error('device_id_required', 400);

    $sql = "
      SELECT
        a.id,
        a.device_id,
        a.title,
        a.issue_type,
        JSON_UNQUOTE(JSON_EXTRACT(a.short_form, '$[0].value')) AS `desc`,
        a.created_at,
        a.created_by_name,
        a.approval_status,
        a.status,
        c.plans_done,
        c.plans_total,
        DATE(c.max_est_date) AS planned_completion_date,
        CASE
          WHEN c.plans_total IS NULL OR c.plans_total = 0 THEN a.status
          WHEN c.plans_done = 0 THEN 'open'
          WHEN c.plans_done < c.plans_total THEN 'in_progress'
          ELSE 'done'
        END AS status_auto
      FROM device_actions a
      LEFT JOIN (
        SELECT
          action_id,
          SUM(status = 'done') AS plans_done,
          COUNT(*)             AS plans_total,
          MAX(est_date)        AS max_est_date
        FROM device_action_plans
        GROUP BY action_id
      ) c ON c.action_id = a.id
      WHERE a.device_id = ?
      ORDER BY
        (CASE WHEN c.plans_total IS NULL OR c.plans_done < c.plans_total THEN 0 ELSE 1 END),
        c.max_est_date IS NULL,
        c.max_est_date ASC,
        a.id DESC
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute([$deviceId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    send_json_ok([
      'device_id' => $deviceId,
      'count'     => count($rows),
      'items'     => $rows,
    ]);

  } catch (Throwable $e) {
    error_log('[list_device_actions_v2] '.$e->getMessage());
    send_json_error('server_error', 500);
  }
}




// === Tất cả action plan (AP) của 1 ISSUE
elseif (($_GET['action'] ?? '') === 'list_action_plans') {
  header('Content-Type: application/json; charset=utf-8');

  $aid = (int)($_GET['action_id'] ?? 0);
  if ($aid <= 0) { echo json_encode([]); exit; }

  try {
    $sql = <<<SQL
SELECT
  p.id,
  COALESCE(p.plan_code, CONCAT('AP', LPAD(p.id,5,'0'))) AS plan_code,
  p.plan_text,
  p.status,
  p.est_date,
  p.est_date AS planned_completion_date,
  p.owner_user_id,
  COALESCE(p.owner_name, u.username) AS owner_name
FROM device_action_plans p
LEFT JOIN users u ON u.id = p.owner_user_id
WHERE p.action_id = ?
ORDER BY
  p.est_date IS NULL,  -- NULL xuống cuối
  p.est_date ASC,
  p.id ASC
SQL;

    $st = $pdo->prepare($sql);
    $st->execute([$aid]);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    // Chuẩn hoá status về lowercase (tuỳ thích)
    foreach ($rows as &$r) {
      if (isset($r['status'])) $r['status'] = strtolower((string)$r['status']);
    }
    unset($r);

    echo json_encode($rows, JSON_UNESCAPED_UNICODE);
  } catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
  }
  exit;
}

if (($_GET['action'] ?? '') === 'get_total_count') {
    // 1. Xác định tham số
    $recalc = (isset($_GET['recalc']) && $_GET['recalc'] == '1');
    $onlyDeviceId = $_GET['device_id'] ?? null;

    // === NHÁNH NHẸ: READ-ONLY (Trả về dữ liệu hiện tại trong DB) ===
    if (!$recalc) {
        $fields = "device_id, display_type, total_count, unit, 
                   cavity_count, total_rpm, total_cycle";
        
        $sql = "SELECT $fields FROM devices";
        $params = [];
        
        if ($onlyDeviceId) {
            $sql .= " WHERE device_id = ?";
            $params[] = $onlyDeviceId;
        }
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Format dữ liệu JSON trả về (Updated = false)
        $finalDevices = array_map(function($r) {
            return [
                'device_id'    => (string)$r['device_id'],
                'display_type' => (string)$r['display_type'],
                'total_count'  => (int)$r['total_count'],
                'unit'         => (string)$r['unit'],
                'cavity_count' => is_numeric($r['cavity_count']) ? (int)$r['cavity_count'] : null,
                'total_rpm'    => is_numeric($r['total_rpm']) ? (int)$r['total_rpm'] : null,
                'total_cycle'  => is_numeric($r['total_cycle']) ? (int)$r['total_cycle'] : null,
                'updated'      => false
            ];
        }, $rows);

        if ($onlyDeviceId && empty($finalDevices)) {
            send_json(['error' => 'not_found'], 404);
        }

        send_json([
            'status'  => 'ok',
            'updated' => 0,
            'devices' => $finalDevices
        ]);
        exit;
    }

    // === NHÁNH NẶNG: INCREMENTAL UPDATE (Đã Fix lỗi NOT NULL) ===
    
    $lockKey = 'ems_total_count_inc_lock';
    $gotLock = false;
    try {
        $stmtLock = $pdo->prepare("SELECT GET_LOCK(?, 3)");
        $stmtLock->execute([$lockKey]);
        $gotLock = (bool)$stmtLock->fetchColumn();
    } catch (Throwable $e) { }

    try {
        $pdo->beginTransaction();

        // 2. Lấy danh sách thiết bị
        $sqlDev = "SELECT device_id, display_type, total_count, unit, total_cycle,
                          COALESCE(total_count_updated_at, '2000-01-01 00:00:00') as last_update,
                          COALESCE(history_count, 0) as history_count,
                          cavities, hole_per_brush
                   FROM devices";
        
        $params = [];
        if ($onlyDeviceId) {
            $sqlDev .= " WHERE device_id = ?";
            $params[] = $onlyDeviceId;
        }
        
        $stmt = $pdo->prepare($sqlDev);
        $stmt->execute($params);
        $devices = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 3. Prepared Statements
        $stmtMold = $pdo->prepare("SELECT COUNT(*) FROM mold WHERE mold_id = ? AND datetime > ? AND cycle_time >= 20");
        $stmtTuft = $pdo->prepare("SELECT COALESCE(SUM(output), 0) FROM tuft WHERE device_id = ? AND datetime > ?");
        $stmtBlisterOut = $pdo->prepare("SELECT COALESCE(SUM(output), 0) FROM blister WHERE device_id = ? AND datetime > ?");
        $stmtBlisterCyc = $pdo->prepare("SELECT COALESCE(SUM(cyclecount), 0) FROM blister WHERE device_id = ? AND datetime > ?");

        // QUERY UPDATE - FIX: Dùng IF để giữ nguyên giá trị cũ nếu không phải loại máy đó
        $stmtUpd = $pdo->prepare("
            UPDATE devices SET 
                total_count = :new_total,
                unit = :unit,
                total_count_updated_at = :now_time,
                
                cavity_count = IF(:is_mold=1, :cavity_count, cavity_count),
                cavity_count_updated_at = IF(:is_mold=1, :now_time, cavity_count_updated_at),

                total_rpm = IF(:is_tuft=1, :total_rpm, total_rpm),
                total_rpm_updated_at = IF(:is_tuft=1, :now_time, total_rpm_updated_at),

                total_cycle = IF(:is_blister=1, :total_cycle, total_cycle),
                total_cycle_updated_at = IF(:is_blister=1, :now_time, total_cycle_updated_at)
            WHERE device_id = :eid
        ");

        $changedCount = 0;
        $resultList  = [];
        $nowTime = date('Y-m-d H:i:s');

        foreach ($devices as $d) {
            $eid = $d['device_id'];
            $type = strtolower($d['display_type'] ?? '');
            $lastUpdate = $d['last_update'];
            $isFirstRun = ($lastUpdate === '2000-01-01 00:00:00');

            // Lấy giá trị hiện tại
            $currentTotal = (float)($d['total_count'] ?? 0);
            $currentTotalCycle = (float)($d['total_cycle'] ?? 0);
            $history = (int)$d['history_count'];
            
            $addedValue = 0;
            $addedCycle = 0;
            $unit = $d['unit'] ?: ($type === 'mold' ? 'shot' : 'pcs');

            // --- TÍNH DELTA ---
            if ($type === 'mold') {
                $stmtMold->execute([$eid, $lastUpdate]);
                $addedValue = (int)$stmtMold->fetchColumn();
                if (!$d['unit']) $unit = 'shot';
            } 
            elseif ($type === 'tuft') {
                $stmtTuft->execute([$eid, $lastUpdate]);
                $addedValue = (int)$stmtTuft->fetchColumn();
                if (!$d['unit']) $unit = 'pcs';
            } 
            elseif ($type === 'blister') {
                $stmtBlisterOut->execute([$eid, $lastUpdate]);
                $addedValue = (int)$stmtBlisterOut->fetchColumn();
                
                $stmtBlisterCyc->execute([$eid, $lastUpdate]);
                $addedCycle = (int)$stmtBlisterCyc->fetchColumn();
                if (!$d['unit']) $unit = 'pcs';
            }

            // --- TÍNH TỔNG MỚI ---
            if ($isFirstRun) {
                $newTotal = $history + $addedValue;
                $newTotalCycle = ($type === 'blister') ? $addedCycle : 0; 
            } else {
                $newTotal = $currentTotal + $addedValue;
                $newTotalCycle = ($type === 'blister') ? ($currentTotalCycle + $addedCycle) : 0;
            }

            // --- TÍNH CỘT PHỤ ---
            $cavities = (int)($d['cavities'] ?? 0);
            $holes    = (int)($d['hole_per_brush'] ?? 0);

            $isMold = ($type === 'mold') ? 1 : 0;
            $isTuft = ($type === 'tuft') ? 1 : 0;
            $isBlister = ($type === 'blister') ? 1 : 0;

            // Biến cho JSON (có null)
            $jsonCavity = $isMold ? ($newTotal * $cavities) : null;
            $jsonRpm    = $isTuft ? ($newTotal * $holes)    : null;
            $jsonCycle  = $isBlister ? $newTotalCycle       : null;

            // Biến cho SQL (Dùng 0 thay vì null để tránh lỗi NOT NULL constraint)
            // Vì có hàm IF(...) trong SQL nên số 0 này sẽ bị bỏ qua nếu không đúng loại máy.
            $sqlCavity = $jsonCavity ?: 0;
            $sqlRpm    = $jsonRpm ?: 0;
            $sqlCycle  = $jsonCycle ?: 0;

            // --- UPDATE DB ---
            $stmtUpd->execute([
                ':new_total' => $newTotal,
                ':unit'      => $unit,
                ':now_time'  => $nowTime,
                ':is_mold'   => $isMold,
                ':cavity_count' => $sqlCavity,
                ':is_tuft'   => $isTuft,
                ':total_rpm' => $sqlRpm,
                ':is_blister'=> $isBlister,
                ':total_cycle'=> $sqlCycle,
                ':eid'       => $eid
            ]);

            $isUpdated = ($addedValue > 0 || $addedCycle > 0);
            if ($isUpdated) $changedCount++;

            // --- BUILD JSON OBJECT ---
            $resultList[] = [
                'device_id'    => (string)$eid,
                'display_type' => (string)$type,
                'total_count'  => (int)$newTotal,
                'unit'         => (string)$unit,
                'cavity_count' => $jsonCavity !== null ? (int)$jsonCavity : null,
                'total_rpm'    => $jsonRpm !== null ? (int)$jsonRpm : null,
                'total_cycle'  => $jsonCycle !== null ? (int)$jsonCycle : null,
                'updated'      => $isUpdated
            ];
        }

        $pdo->commit();

        if ($gotLock) {
            try { $pdo->query("SELECT RELEASE_LOCK('$lockKey')"); } catch (Throwable $e) {}
        }

        send_json([
            'status'  => 'ok',
            'updated' => $changedCount,
            'devices' => $resultList
        ]);

    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        if ($gotLock) {
            try { $pdo->query("SELECT RELEASE_LOCK('$lockKey')"); } catch (Throwable $e2) {}
        }
        send_json(['status' => 'error', 'message' => $e->getMessage()], 500);
    }
}
// if (($_GET['action'] ?? '') === 'get_total_count') {
//     $onlyDeviceId = $_GET['device_id'] ?? null;

//     // Chỉ cần lấy dữ liệu có sẵn trong bảng devices
//     // Vì file molding.php đã tự động cộng số rồi, nên ở đây chỉ cần SELECT
    
//     if ($onlyDeviceId) {
//         // Lấy 1 thiết bị
//         $stmt = $pdo->prepare("
//             SELECT 
//                 device_id, display_type,
//                 total_count, unit, total_count_updated_at,
//                 cavities, hole_per_brush,
//                 cavity_count, cavity_count_updated_at,
//                 total_rpm, total_rpm_updated_at,
//                 total_cycle, total_cycle_updated_at
//             FROM devices
//             WHERE device_id = ?
//             LIMIT 1
//         ");
//         $stmt->execute([$onlyDeviceId]);
//         $row = $stmt->fetch(PDO::FETCH_ASSOC);
        
//         if ($row) {
//             send_json($row);
//         } else {
//             send_json(['error' => 'not_found'], 404);
//         }
//     } else {
//         // Lấy tất cả thiết bị
//         $rows = $pdo->query("
//             SELECT 
//                 device_id, client, display_type,
//                 total_count, unit, total_count_updated_at,
//                 cavities, hole_per_brush,
//                 cavity_count, cavity_count_updated_at,
//                 total_rpm, total_rpm_updated_at,
//                 total_cycle, total_cycle_updated_at
//             FROM devices
//         ")->fetchAll(PDO::FETCH_ASSOC);
        
//         send_json(['status' => 'ok', 'devices' => $rows]);
//     }
//     exit;
// }
// === META: trả về mốc cập nhật gần nhất + giờ server ===
if (($_GET['action'] ?? '') === 'tc_meta') {
    header('Content-Type: application/json; charset=utf-8');
    try {
        // lấy max(total_count_updated_at) của bảng devices
        $row = $pdo->query("SELECT MAX(total_count_updated_at) AS updated_at FROM devices")
                   ->fetch(PDO::FETCH_ASSOC);

        echo json_encode([
            'db_updated_at' => $row['updated_at'] ?? null,      // "YYYY-MM-DD HH:MM:SS"
            'server_now'    => date('Y-m-d H:i:s'),              // giờ server
            'tz'            => date_default_timezone_get(),      // chỉ để debug
        ]);
    } catch (Throwable $e) {
        http_response_code(500);
        echo json_encode(['error' => $e->getMessage()]);
    }
    exit;
}

else {
    // 1. Lấy tham số
    $viewType = $_GET['view'] ?? 'mold';
    $since    = $_GET['since'] ?? null;

    // 2. Chuẩn bị query tối ưu: JOIN các bảng lại và lọc ngay bằng SQL
    // Chỉ lấy thiết bị thuộc loại đang xem (viewType) hoặc lấy hết nếu view='all'
    $params = [];
    $sqlDevices = "
        SELECT 
            d.*,
            ds.connection_status AS old_conn_status,
            ds.threshold_status  AS old_thres_status,
            ds.last_heartbeat,
            ldd.live_data        AS json_live_data,
            ldd.last_updated     AS live_updated_at
        FROM devices d
        LEFT JOIN device_status ds ON d.device_id = ds.device_id
        LEFT JOIN live_device_data ldd ON d.device_id = ldd.device_id
    ";

    if ($viewType !== 'all') {
        $sqlDevices .= " WHERE d.display_type = ? ";
        $params[] = $viewType;
    }

    $stmt = $pdo->prepare($sqlDevices);
    $stmt->execute($params);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // 3. Chuẩn bị dữ liệu thống kê Action (nếu cần badge)
    $actionStats = [];
    try {
        $stmtAS = $pdo->query("
            SELECT device_id,
                SUM(CASE WHEN status<>'cancelled' AND NOT (status='done' AND approval_status='approved') THEN 1 ELSE 0 END) AS open_count,
                SUM(CASE WHEN status<>'cancelled' AND NOT (status='done' AND approval_status='approved')
                          AND priority='urgent' AND due_date IS NOT NULL AND due_date < NOW()
                    THEN 1 ELSE 0 END) AS urgent_overdue
            FROM device_actions
            GROUP BY device_id
        ");
        foreach ($stmtAS as $r) { $actionStats[$r['device_id']] = $r; }
    } catch (Throwable $e) {}

    $finalResponse = [];
    $now = time();
    $utcTimeZone = new DateTimeZone('UTC'); // Dùng nếu cần so sánh time

    // 4. Loop xử lý nhẹ (chỉ tính toán hiển thị, KHÔNG GHI DB, KHÔNG CURL)
    foreach ($rows as $row) {
        $deviceId = $row['device_id'];
        
        // Parse JSON live data
        $live_data = null;
        if (!empty($row['json_live_data'])) {
            $live_data = json_decode($row['json_live_data'], true);
        }

        // --- Logic tính toán trạng thái (Chỉ để hiển thị UI) ---
        $is_connected = false;
        if ($live_data && isset($live_data['datetime'])) {
            // Check heartbeat dựa trên frequency
            $lastDataTime = strtotime($live_data['datetime']); // Giả sử datetime trong JSON là chuẩn
            if (($now - $lastDataTime) <= (int)$row['frequency']) {
                $is_connected = true;
            }
        }

        $connection_status = $is_connected ? 'OK' : 'DISCONNECTED';
        $threshold_status  = 'Normal'; // Mặc định
        
        // Tính toán Metrics riêng theo loại máy (nếu cần hiển thị số liệu tính toán)
        if ($live_data) {
          switch ($row['display_type']) {
              case 'mold':
                  $m = calculateMoldMetrics($live_data, $row);
                  if($m) $live_data = array_merge($live_data, $m);
                  
                  if (isset($m['efficiency'], $row['efficiency_lower_limit']) && $m['efficiency'] < (float)$row['efficiency_lower_limit']) {
                      $threshold_status = 'Breached';
                  }
                  $cyc = (float)($live_data['cycle_time'] ?? 0);
                  if ($cyc > 0) {
                      if ((float)$row['lower_limit'] > 0 && $cyc < (float)$row['lower_limit']) $threshold_status = 'Breached';
                      if ((float)$row['upper_limit'] > 0 && $cyc > (float)$row['upper_limit']) $threshold_status = 'Breached';
                  }
                  break;

              case 'tuft':
                  if (isset($live_data['output'], $row['hole_per_brush'])) {
                      $live_data['rpm'] = round((float)$live_data['output'] * (int)$row['hole_per_brush']);
                  }
                  $t = calculateTuftMetrics($live_data, $row);
                  if($t) $live_data = array_merge($live_data, $t);

                  $out = (float)($live_data['output'] ?? 0);
                  $eff = (float)($t['efficiency'] ?? 0);
                  
                  if ((float)$row['lower_limit'] > 0 && $out < (float)$row['lower_limit']) {
                      $threshold_status = 'Breached';
                  }
                  if ((float)$row['upper_limit'] > 0 && $out > (float)$row['upper_limit']) {
                      $threshold_status = 'Breached';
                  }

                  if ((float)$row['efficiency_lower_limit'] > 0 && $eff < (float)$row['efficiency_lower_limit']) {
                      $threshold_status = 'Breached';
                  }
                  break;

              case 'blister':
                  $b = calculateBlisterMetrics($live_data, $row);
                  if($b) $live_data = array_merge($live_data, $b);
                  $cc  = (float)($live_data['cyclecount'] ?? 0);
                  $eff = (float)($b['efficiency'] ?? 0);

                  if ((float)$row['lower_limit'] > 0 && $cc < (float)$row['lower_limit']) {
                      $threshold_status = 'Breached';
                  }
                  if ((float)$row['upper_limit'] > 0 && $cc > (float)$row['upper_limit']) {
                      $threshold_status = 'Breached';
                  }
                  if ((float)$row['efficiency_lower_limit'] > 0 && $eff < (float)$row['efficiency_lower_limit']) {
                      $threshold_status = 'Breached';
                  }
                  break;
          }
        }

        // Lấy status hiển thị cuối cùng
        // Ưu tiên: Disconnected > Breached > Normal
        if ($connection_status === 'DISCONNECTED') {
            $displayStatus = 'DISCONNECTED';
        } else {
            // Nếu DB đang lưu là Breached/Warning thì hiển thị Breached, trừ khi logic trên phát hiện Normal
            // Ở đây ta ưu tiên trạng thái realtime vừa tính toán:
            $displayStatus = $threshold_status;
            
            // HOẶC: Nếu bạn muốn tin tưởng DB status (do cronjob cập nhật) hơn:
            // $dbThres = strtoupper($row['old_thres_status'] ?? 'NORMAL');
            // if ($dbThres === 'WARNING' || $dbThres === 'BREACHED') $displayStatus = 'Breached';
        }

        // Filter 'since' (Logic tiết kiệm băng thông cho Frontend polling)
        // Nếu user gửi lên timestamp, chỉ trả về các máy có dữ liệu mới hơn timestamp đó
        if ($since && isset($live_data['datetime'])) {
             // So sánh string datetime hoặc timestamp
             if ($live_data['datetime'] <= $since) {
                 continue; // Bỏ qua máy này, dữ liệu chưa mới
             }
        }

        $stats = $actionStats[$deviceId] ?? [];

        // Build kết quả
        $finalResponse[] = array_merge($row, [
            'status'                => $displayStatus, 
            'connection_status'     => $connection_status, // Trả về để UI debug nếu cần
            'live_data'             => $live_data,
            'timestamp'             => $live_data['datetime'] ?? null,
            'action_count_open'     => (int)($stats['open_count'] ?? 0),
            'action_urgent_overdue' => (int)($stats['urgent_overdue'] ?? 0),
            // Loại bỏ các trường thừa không cần gửi xuống client để nhẹ JSON
            'json_live_data'        => null, 
        ]);
    }

    // 5. Trả về JSON
    $newTimestamp = gmdate('Y-m-d H:i:s'); // Timestamp hiện tại của server (UTC)
    
    // Header cache control để browser không cache kết quả API này
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'devices'      => $finalResponse,
        'newTimestamp' => $newTimestamp // Client sẽ dùng cái này cho tham số &since= lần sau
    ], JSON_NUMERIC_CHECK);
    exit;
}

    } catch (Exception $e) {
        if (isset($pdo) && $pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log("API FATAL ERROR: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine());
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]);
    }




