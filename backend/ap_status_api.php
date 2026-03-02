<?php
/**
 * AP Status API
 * Trả trạng thái hiện tại của các AP dựa trên ap_logs (bản ghi mới nhất mỗi AP)
 * Query:
 *   ?names=AP01,AP02  (tùy chọn, lọc theo danh sách AP)
 *   ?stale_minutes=10 (tùy chọn, mặc định 10; nếu bản ghi cũ hơn => coi là DISCONNECTED)
 *
 * Response:
 * {
 *   "status":"success",
 *   "stale_seconds":600,
 *   "items":[
 *     {
 *       "name":"AP01",
 *       "location":"PG",
 *       "status_raw":"ONLINE",
 *       "status":"NORMAL",
 *       "ping_ms": 2.35,
 *       "last_seen":"2025-10-17 15:45:12",
 *       "age_sec":42
 *     }, ...
 *   ],
 *   "map": { "AP01": { ... }, "AP02": { ... } }
 * }
 */

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

date_default_timezone_set('Asia/Ho_Chi_Minh');

// ===== DB config (giống log_data.php) =====
$DB_HOST = 'localhost';
$DB_USER = 'device';
$DB_PASS = 'BtyLX96qZ4nDL!0w';
$DB_NAME = 'central';

try {
  $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
  ]);
} catch (Throwable $e) {
  http_response_code(500);
  echo json_encode(['status'=>'error', 'message'=>'DB connection failed', 'detail'=>$e->getMessage()]);
  exit;
}

// ===== Inputs =====
$staleMinutes = isset($_GET['stale_minutes']) ? (int)$_GET['stale_minutes'] : 60;
if ($staleMinutes <= 0) $staleMinutes = 60;
$staleSeconds = $staleMinutes * 60;

$namesParam = trim($_GET['names'] ?? '');
$names = [];
if ($namesParam !== '') {
  foreach (explode(',', $namesParam) as $n) {
    $n = trim($n);
    if ($n !== '' && preg_match('/^[A-Za-z0-9_\-]+$/', $n)) $names[] = $n;
  }
}

// ===== Query bản ghi mới nhất mỗi AP =====
// MySQL/MariaDB tương thích: join với MAX(logged_at)
$sql =
  "SELECT l.ap_name AS name,
          l.location,
          l.status AS status_raw,
          l.ping_ms,
          l.logged_at AS last_seen,
          TIMESTAMPDIFF(SECOND, l.logged_at, NOW()) AS age_sec
   FROM ap_logs l
   JOIN (
      SELECT ap_name, MAX(logged_at) AS max_ts
      FROM ap_logs
      /**WHERE_CLAUSE**/
      GROUP BY ap_name
   ) m ON m.ap_name = l.ap_name AND m.max_ts = l.logged_at
   /**WHERE_CLAUSE_2**/
   ORDER BY l.ap_name";

$params = [];
if (count($names) > 0) {
  $in = implode(',', array_fill(0, count($names), '?'));
  $sql = str_replace('/**WHERE_CLAUSE**/', "WHERE ap_name IN ($in)", $sql);
  $sql = str_replace('/**WHERE_CLAUSE_2**/', "WHERE l.ap_name IN ($in)", $sql);
  $params = array_merge($names, $names);
} else {
  $sql = str_replace('/**WHERE_CLAUSE**/', "", $sql);
  $sql = str_replace('/**WHERE_CLAUSE_2**/', "", $sql);
}

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// ===== Chuẩn hoá status cho FE (map sang NORMAL / DISCONNECTED) =====
// ===== Chuẩn hoá status cho FE (chỉ theo status_raw, KHÔNG xét tuổi bản ghi) =====
$items = [];
$map   = [];

$statusMap = [
  'ONLINE'       => 'NORMAL',
  'OK'           => 'NORMAL',
  'CONNECTED'    => 'NORMAL',
  'OFFLINE'      => 'DISCONNECTED',
  'DISCONNECT'   => 'DISCONNECTED',
  'DISCONNECTED' => 'DISCONNECTED',
  'BREACHED'     => 'BREACHED',
];

foreach ($rows as $r) {
  $statusRaw = strtoupper(trim($r['status_raw'] ?? ''));
  // Không xét ageSec nữa; chỉ map thẳng từ status_raw
  $status = $statusMap[$statusRaw] ?? 'UNKNOWN';

  $item = [
    'name'       => $r['name'],
    'location'   => $r['location'],
    'status_raw' => $statusRaw,
    'status'     => $status,                               // FE dùng trực tiếp
    'ping_ms'    => is_null($r['ping_ms']) ? null : (float)$r['ping_ms'],
    'last_seen'  => $r['last_seen'],
    'age_sec'    => isset($r['age_sec']) ? (int)$r['age_sec'] : null, // giữ field cũ cho tương thích, nhưng KHÔNG dùng
  ];
  $items[] = $item;
  $map[$r['name']] = $item;
}

// (Tuỳ chọn) nếu client truyền ?names=... mà DB không có bản ghi tương ứng,
// bạn có thể trả về bản ghi "UNKNOWN" để FE vẫn render:
if (count($names) > 0) {
  foreach ($names as $n) {
    if (!isset($map[$n])) {
      $item = [
        'name'       => $n,
        'location'   => null,
        'status_raw' => null,
        'status'     => 'UNKNOWN',   // hoặc 'DISCONNECTED' nếu bạn muốn coi là mất kết nối khi không có log
        'ping_ms'    => null,
        'last_seen'  => null,
        'age_sec'    => null,
      ];
      $items[] = $item;
      $map[$n] = $item;
    }
  }
}


echo json_encode([
  'status' => 'success',
  'stale_seconds' => $staleSeconds,
  'items' => $items,
  'map'   => $map
], JSON_UNESCAPED_UNICODE);
