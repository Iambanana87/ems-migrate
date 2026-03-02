<?php
require_once __DIR__ . '/../helper/jwt.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../helper/auth_helper.php';
require_once __DIR__ . '/../middlewave/middleware_endpoint_logger.php';


// capture_current_endpoint([
//   'subsystem'      => 'ems',
//   'backend_page'   => '/ems/backend/backend.php',
//   'login_redirect' => 'login.php?return=./backend.php',
//   'cookie_name'    => 'ems_token',
// ]);

capture_current_endpoint();

// Xác định đây có phải call API hay không (POST, hoặc GET có ?action=)
$IS_API = ($_SERVER['REQUEST_METHOD'] === 'POST')
       || ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['action']));

ob_start();

if ($IS_API) {
  // API: bắt buộc Authorization header
  $AUTH = require_auth_header();
} else {
  // UI: cho phép đọc từ cookie 
  $AUTH = auth_user_or_null_ui();
  if (!$AUTH) {
    header('Location: login.php?return=./backend.php');
    exit;
  }
}

ini_set('display_errors', $IS_API ? '0' : '1');
error_reporting(E_ALL);


try {
  $pdo = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
    DB_USER, DB_PASS,
    [ PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC ]
  );
} catch (PDOException $e) {
  header('Content-Type: application/json; charset=utf-8');
  http_response_code(500);
  echo json_encode(['status'=>'error','message'=>'DB connect error: '.$e->getMessage()]);
  exit;
}
require_once __DIR__ . '/../model/audit.php';

$audit = new AuditTrail($pdo, [
  'ignore_fields' => ['created_at','updated_at']
]);

$WHO = (function($AUTH) {
  if (is_array($AUTH)  && !empty($AUTH['username'])) return (string)$AUTH['username'];
  if (is_object($AUTH) && !empty($AUTH->username))   return (string)$AUTH->username;
  return AuditTrail::current_user();
})($AUTH);

// ---- Helper: buộc phải có lý do cho mọi hành động ----
function require_reason(array $keys = ['reason','note']): string {
  foreach ($keys as $k) {
    $v = trim((string)($_POST[$k] ?? ''));
    if ($v !== '') return mb_substr($v, 0, 100);
  }
  throw new Exception('reason required');
}

// Thêm nhanh reason vào before/after để AuditTrail vẫn lưu trong JSON
function inject_reason_into(&$before, &$after, string $reason): void {
  if (is_array($before)) $before['__reason'] = $reason;
  if (is_array($after))  $after['__reason']  = $reason;
}


function send_json_response($data, int $code = 200) {
  // xoá mọi output rác trước khi gửi JSON
  if (ob_get_length()) { ob_clean(); }
  header('Content-Type: application/json; charset=utf-8');
  http_response_code($code);
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}


try {
  $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USER, DB_PASS);
  $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
  $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
  send_json_response(['status' => 'error', 'message' => 'Database Connection Error: ' . $e->getMessage()]);
}

// --- POST CRUD thiết bị & actions ---
if (
  $_SERVER['REQUEST_METHOD'] === 'POST'
  && isset($_POST['action'])
  && preg_match('/^(action_(create|update|delete)|approve_action|reject_action)$/', $_POST['action'])
) {
  try {
    $pdo->beginTransaction();
    $action = $_POST['action'];
switch ($action) {

  // ===== ACTIONS: CREATE =====
  case 'action_create': {
    requireAdmin();

    $device_id = trim($_POST['device_id'] ?? '');
    $title     = trim($_POST['title'] ?? '');
    if ($device_id === '' || $title === '') {
      throw new Exception('Missing device_id/title');
    }

    $priority  = $_POST['priority'] ?? 'medium';
    $due_date  = trim($_POST['due_date'] ?? '');
    $due_date  = $due_date !== '' ? $due_date : null;
    $assignee  = (($_POST['assigned_to_user_id'] ?? '') !== '') ? (int)$_POST['assigned_to_user_id'] : null;

    $sf_json   = $_POST['short_form'] ?? '[]';
    $sf        = json_decode($sf_json, true);
    if (!is_array($sf)) throw new Exception('short_form must be JSON array');

    // creator id safe (AUTH có thể là array hoặc object)
    $creator = is_array($AUTH)  ? (int)($AUTH['id'] ?? 0)
             : (is_object($AUTH)? (int)($AUTH->id ?? 0) : 0);

    $stmt = $pdo->prepare("INSERT INTO device_actions
      (device_id, title, short_form, status, priority, due_date, assigned_to_user_id, created_by_user_id)
      VALUES (?,?,?,?,?,?,?,?)");
    $stmt->execute([
      $device_id,
      $title,
      json_encode($sf, JSON_UNESCAPED_UNICODE),
      'open',
      $priority,
      $due_date,
      $assignee,
      $creator,
    ]);

    $newId = (int)$pdo->lastInsertId();

    // Audit (create)
    $afterRow = [
      'id'                  => $newId,
      'device_id'           => $device_id,
      'title'               => $title,
      'short_form'          => json_encode($sf, JSON_UNESCAPED_UNICODE),
      'status'              => 'open',
      'priority'            => $priority,
      'due_date'            => $due_date,
      'assigned_to_user_id' => $assignee,
      'created_by_user_id'  => $creator,
    ];

    $reason = require_reason();             
    $afterRow['__entity'] = 'device_actions:create#'.$newId;
    $audit->log('create', $reason, [], $afterRow, $WHO);

    $pdo->commit();
    send_json_response(['status'=>'success','message'=>'Action created','id'=>$newId]);
  } break;


  // ===== ACTIONS: UPDATE =====
  case 'action_update': {
    requireAdmin();

    $id = (int)($_POST['action_id'] ?? 0);
    if ($id <= 0) throw new Exception('Missing action_id');

    // before for audit
    $cur = $pdo->prepare("SELECT * FROM device_actions WHERE id=?");
    $cur->execute([$id]);
    $beforeRow = (array)$cur->fetch(PDO::FETCH_ASSOC);
    if (!$beforeRow) throw new Exception('Action not found');

    $title    = trim($_POST['title'] ?? $beforeRow['title'] ?? '');
    $priority = $_POST['priority'] ?? ($beforeRow['priority'] ?? 'medium');

    $due_in   = trim($_POST['due_date'] ?? '');
    $due_date = $due_in !== '' ? $due_in : null;

    $assignee = (($_POST['assigned_to_user_id'] ?? '') !== '')
                ? (int)$_POST['assigned_to_user_id']
                : ($beforeRow['assigned_to_user_id'] ?? null);

    $sf_json  = $_POST['short_form'] ?? ($beforeRow['short_form'] ?? '[]');
    $sf       = json_decode($sf_json, true);
    if (!is_array($sf)) throw new Exception('short_form must be JSON array');

    $status_in = strtolower(trim($_POST['status'] ?? ($beforeRow['status'] ?? 'open')));
    $allowed   = ['open','in_progress','done','cancelled'];
    $status    = in_array($status_in, $allowed, true) ? $status_in : ($beforeRow['status'] ?? 'open');

    $stmt = $pdo->prepare("UPDATE device_actions
      SET title=?, priority=?, status=?, due_date=?, assigned_to_user_id=?, short_form=?
      WHERE id=?");
    $stmt->execute([
      $title,
      $priority,
      $status,
      $due_date,
      $assignee,
      json_encode($sf, JSON_UNESCAPED_UNICODE),
      $id
    ]);

    // after for audit
    $afterRow = $beforeRow;
    $afterRow['title']               = $title;
    $afterRow['priority']            = $priority;
    $afterRow['status']              = $status;
    $afterRow['due_date']            = $due_date;
    $afterRow['assigned_to_user_id'] = $assignee;
    $afterRow['short_form']          = json_encode($sf, JSON_UNESCAPED_UNICODE);

    $reason = require_reason();
    $beforeRow['__entity'] = $afterRow['__entity'] = 'device_actions:update#'.$id; 
    $audit->log('update', $reason, $beforeRow, $afterRow, $WHO);

    $pdo->commit();
    send_json_response(['status'=>'success','message'=>'Action updated']);
  } break;


  // ===== ACTIONS: DELETE =====
  case 'action_delete': {
    capture_current_endpoint();
    requireAdmin();

    $id = (int)($_POST['action_id'] ?? 0);
    if ($id <= 0) throw new Exception('Missing action_id');

    // before for audit
    $cur = $pdo->prepare("SELECT * FROM device_actions WHERE id=?");
    $cur->execute([$id]);
    $beforeRow = (array)$cur->fetch(PDO::FETCH_ASSOC);
    if (!$beforeRow) throw new Exception('Action not found');

    $del = $pdo->prepare("DELETE FROM device_actions WHERE id=?");
    $del->execute([$id]);
    if ($del->rowCount() < 1) throw new Exception('Delete failed');

    // audit
    $reason = require_reason();
    $beforeRow['__entity'] = 'device_actions:delete#'.$id;     
    $audit->log('delete', $reason, $beforeRow, [], $WHO);

    $pdo->commit();
    send_json_response(['status'=>'success','message'=>'Action deleted']);
  } break;


  // ===== ACTIONS: APPROVE =====
  case 'approve_action': {
    capture_current_endpoint();
    requireManagerOrAdmin();

    $id   = (int)($_POST['action_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    if ($id <= 0) throw new Exception('Missing action_id');

    // before for audit
    $cur = $pdo->prepare("SELECT * FROM device_actions WHERE id=?");
    $cur->execute([$id]);
    $beforeRow = (array)$cur->fetch(PDO::FETCH_ASSOC);
    if (!$beforeRow) throw new Exception('Action not found');

    $st   = strtolower((string)($beforeRow['status'] ?? ''));
    $appr = strtolower((string)($beforeRow['approval_status'] ?? ''));

    if ($appr && $appr !== 'pending') {
      throw new Exception('Action already decided');
    }

    $approvedById   = is_array($AUTH)  ? (int)($AUTH['id'] ?? 0)
                       : (is_object($AUTH)? (int)($AUTH->id ?? 0) : 0);
    $approvedByName = is_array($AUTH)  ? ($AUTH['username'] ?? null)
                       : (is_object($AUTH)? ($AUTH->username ?? null) : null);

    $stmt = $pdo->prepare("
      UPDATE device_actions
         SET approval_status     = 'approved',
             approved_by_user_id = ?,
             approved_by_name    = ?,
             approved_at         = NOW(),
             approval_note       = ?
       WHERE id = ?
         AND (approval_status IS NULL OR approval_status='' OR approval_status='pending')
    ");
    $stmt->execute([$approvedById, $approvedByName, $note, $id]);

    // after for audit
    $afterRow = $beforeRow;
    $afterRow['approval_status']     = 'approved';
    $afterRow['approved_by_user_id'] = $approvedById;
    $afterRow['approved_by_name']    = $approvedByName;
    $afterRow['approved_at']         = date('Y-m-d H:i:s');
    $afterRow['approval_note']       = $note;

    $reason = ($note !== '') ? $note : require_reason();
    $beforeRow['__entity'] = $afterRow['__entity'] = 'device_actions:approve#'.$id; 
    $audit->log('update', $reason, $beforeRow, $afterRow, $WHO);

    $pdo->commit();
    send_json_response(['status'=>'success']);
  } break;


  // ===== ACTIONS: REJECT =====
  case 'reject_action': {
    capture_current_endpoint();
    requireManagerOrAdmin();

    $id   = (int)($_POST['action_id'] ?? 0);
    $note = trim($_POST['note'] ?? '');
    if ($id <= 0) throw new Exception('Missing action_id');

    // before for audit
    $cur = $pdo->prepare("SELECT * FROM device_actions WHERE id=?");
    $cur->execute([$id]);
    $beforeRow = (array)$cur->fetch(PDO::FETCH_ASSOC);
    if (!$beforeRow) throw new Exception('Action not found');

    $st   = strtolower((string)($beforeRow['status'] ?? ''));
    $appr = strtolower((string)($beforeRow['approval_status'] ?? ''));

    if ($appr && $appr !== 'pending') {
      throw new Exception('Action already decided');
    }

    $approvedById   = is_array($AUTH)  ? (int)($AUTH['id'] ?? 0)
                       : (is_object($AUTH)? (int)($AUTH->id ?? 0) : 0);
    $approvedByName = is_array($AUTH)  ? ($AUTH['username'] ?? null)
                       : (is_object($AUTH)? ($AUTH->username ?? null) : null);

    $stmt = $pdo->prepare("
      UPDATE device_actions
         SET approval_status     = 'rejected',
             approved_by_user_id = ?,
             approved_by_name    = ?,
             approved_at         = NOW(),
             approval_note       = ?
       WHERE id = ?
         AND (approval_status IS NULL OR approval_status='' OR approval_status='pending')
    ");
    $stmt->execute([$approvedById, $approvedByName, $note, $id]);

    // after for audit
    $afterRow = $beforeRow;
    $afterRow['approval_status']     = 'rejected';
    $afterRow['approved_by_user_id'] = $approvedById;
    $afterRow['approved_by_name']    = $approvedByName;
    $afterRow['approved_at']         = date('Y-m-d H:i:s');
    $afterRow['approval_note']       = $note;

    //audit
    $reason = ($note !== '') ? $note : require_reason();
    $beforeRow['__entity'] = $afterRow['__entity'] = 'device_actions:reject#'.$id;  
    $audit->log('update', $reason, $beforeRow, $afterRow, $WHO);

    $pdo->commit();
    send_json_response(['status'=>'success']);
  } break;


  default:
    if ($pdo->inTransaction()) $pdo->rollBack();
    send_json_response(['status'=>'error','message'=>"Unknown action \"$action\""]);
}
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    send_json_response(['status'=>'error','message'=>$e->getMessage()]);
  }
}




// [INSERT after line 33] ===== Devices action summary table =====
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'devices_action_table')) {
  $display = $_GET['display_type'] ?? 'all';
  $params = [];
  $where  = '';
  if ($display !== 'all') { 
    $where = 'WHERE d.display_type = ?'; 
    $params[] = $display; 
  }

  $sql = "
    SELECT
      d.display_type,
      d.device_id,
      d.product,
      d.process,

      -- Đếm A: còn action nếu KHÔNG (done + approved) và không bị cancelled
      SUM(CASE 
            WHEN a.status <> 'cancelled'
             AND NOT (a.status='done' AND a.approval_status='approved')
            THEN 1 ELSE 0 
          END) AS open_count,

      -- Cảnh báo (urgent hoặc quá hạn) cho các action còn hiệu lực
      SUM(CASE 
            WHEN a.status <> 'cancelled'
             AND NOT (a.status='done' AND a.approval_status='approved')
             AND (a.priority='urgent' OR (a.due_date IS NOT NULL AND a.due_date < NOW()))
            THEN 1 ELSE 0 
          END) AS urgent_overdue,

      MAX(a.due_date) AS latest_due,

      (SELECT aa.title 
         FROM device_actions aa 
        WHERE aa.device_id = d.device_id
        ORDER BY aa.id DESC 
        LIMIT 1) AS last_action_title
    FROM devices d
    LEFT JOIN device_actions a ON a.device_id = d.device_id
    $where
    GROUP BY d.display_type, d.device_id, d.product, d.process
    ORDER BY d.display_type, d.device_id
  ";

  $stmt = $pdo->prepare($sql);
  $stmt->execute($params);
  $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

  // Đồng bộ key 'title' cho JS đang đọc
  foreach ($rows as &$r) {
    if (!isset($r['title']) && isset($r['last_action_title'])) {
      $r['title'] = $r['last_action_title'];
    }
  }
  unset($r);

  send_json_response($rows);
}


// ===== List actions by device_id =====
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'actions_by_device')) {
  $device_id = trim($_GET['device_id'] ?? '');
  if ($device_id === '') { send_json_response([]); }

  $sql = "SELECT
            a.id,
            a.device_id,
            a.title,
            a.priority,
            a.status,
            a.approval_status,
            a.due_date,
            a.created_at,
            a.assigned_to_user_id,
            u.username AS assigned_to,
            GROUP_CONCAT(DISTINCT uv.username ORDER BY uv.username SEPARATOR ', ') AS verified_people,
            MAX(v.verified_at) AS last_verified_at,
            a.short_form,
            CASE
              WHEN a.status IN ('done','completed') THEN a.status
              WHEN a.approval_status = 'approved' THEN 'done'
              WHEN MAX(v.verified_at) IS NOT NULL THEN 'done'
              ELSE a.status
            END AS status_display
          FROM device_actions a
          LEFT JOIN users u ON u.id = a.assigned_to_user_id
          LEFT JOIN device_action_verifications v ON v.action_id = a.id
          LEFT JOIN users uv ON uv.id = v.user_id
          WHERE a.device_id = ?
          GROUP BY a.id
          ORDER BY a.id DESC";

  $stmt = $pdo->prepare($sql);
  $stmt->execute([$device_id]);
  $rows = $stmt->fetchAll();

  foreach ($rows as &$r) {
    $sf = $r['short_form'] ?? '[]';
    $decoded = json_decode($sf, true);
    $r['short_form'] = is_array($decoded) ? $decoded : [];
  }
  unset($r);

  send_json_response($rows);
}

// --- JSON API cho phần Config (lấy thiết bị đã group) ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && (($_GET['action'] ?? '') === 'get_devices')) {
  send_json_response(['status' => 'success', 'devices' => fetch_and_group_devices($pdo)]);
}

// [INSERT after line 33] ===== Danh sách user cho dropdown Assignee =====
if ($_SERVER['REQUEST_METHOD'] === 'GET' && ($_GET['action'] ?? '') === 'list_users') {
  $stmt = $pdo->query("SELECT id, username FROM users ORDER BY username");
  send_json_response($stmt->fetchAll());
}


// --- POST CRUD thiết bị ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
  $action = $_POST['action'];
  try {
    $pdo->beginTransaction();

    // Nếu là CRUD thiết bị thì ép quyền ngay từ đầu
    if (in_array($action, ['add','update','delete'], true)) {
      requireAdmin();
    }

    switch ($action) {
      case 'add': {
        capture_current_endpoint();
        requireAdmin();

        // 1) Lấy input an toàn + validate cơ bản
        $device_id    = trim($_POST['device_id'] ?? '');
        $display_type = $_POST['display_type'] ?? '';
        if ($device_id === '')    throw new Exception('Missing device_id');
        if ($display_type === '') throw new Exception('Missing display_type');

        // Check trùng device_id
        $stmt_check = $pdo->prepare("SELECT id FROM devices WHERE device_id = ?");
        $stmt_check->execute([$device_id]);
        if ($stmt_check->fetch()) {
          throw new Exception('Error: Device ID "' . htmlspecialchars($device_id) . '" already exists.');
        }

        // Các field tuỳ chọn (đổi '' -> NULL)
        $product       = ($_POST['product']      ?? '') !== '' ? $_POST['product']      : null;
        $client        = ($_POST['client']       ?? '') !== '' ? $_POST['client']       : null;
        $model         = ($_POST['model']        ?? '') !== '' ? $_POST['model']        : null;
        $manufacturer  = ($_POST['manufacturer'] ?? '') !== '' ? $_POST['manufacturer'] : null;

        $manufacturing_date = trim($_POST['manufacturing_date'] ?? '');
        $manufacturing_date = $manufacturing_date !== '' ? $manufacturing_date : null;

        $cavities          = ($display_type==='mold'    && (($_POST['cavities'] ?? '')!==''))
                            ? $_POST['cavities'] : null;
        $hole_per_brush    = ($display_type==='tuft'    && (($_POST['hole_per_brush'] ?? '')!==''))
                            ? $_POST['hole_per_brush'] : null;
        $brushes_per_cycle = ($display_type==='blister' && (($_POST['brushes_per_cycle'] ?? '')!==''))
                            ? $_POST['brushes_per_cycle'] : null;

        $process           = (in_array($display_type, ['mold','tuft','blister'], true) && (($_POST['process'] ?? '')!==''))
                            ? $_POST['process'] : null;
        $mold_type         = ($display_type==='mold' && (($_POST['mold_type'] ?? '')!==''))
                            ? $_POST['mold_type'] : null;

        $eff_ll  = (($_POST['efficiency_lower_limit'] ?? '') !== '') ? $_POST['efficiency_lower_limit'] : null;
        $lower   = (($_POST['lower_limit']            ?? '') !== '') ? $_POST['lower_limit']            : null;
        $target  = (($_POST['target_limit']           ?? '') !== '') ? $_POST['target_limit']           : null;
        $upper   = (($_POST['upper_limit']            ?? '') !== '') ? $_POST['upper_limit']            : null;

        $frequency        = $_POST['frequency']        ?? null;
        $freq_check_limit = $_POST['freq_check_limit'] ?? null;
        $flex             = isset($_POST['flex']) ? 1 : 0;

        $history_count = array_key_exists('history_count', $_POST)
          ? (($_POST['history_count'] === '' || $_POST['history_count'] === null)
              ? null
              : max(0, (int)$_POST['history_count']))
          : null;

        // 2) Tính capacity (dựa vào display_type + target_limit)
        $capacity = null;
        $target_limit_num = ($target !== null && $target !== '') ? (float)$target : 0.0;
        if ($target_limit_num > 0) {
          if ($display_type === 'mold') {
            $cav = (int)($cavities ?? 0);
            if ($cav > 0) $capacity = (int)floor((3600 * $cav) / $target_limit_num);
          } elseif ($display_type === 'tuft') {
            $capacity = (int)floor(60 * $target_limit_num);
          } elseif ($display_type === 'blister') {
            $bpc = (int)($brushes_per_cycle ?? 0);
            if ($bpc > 0) $capacity = (int)floor($target_limit_num * $bpc * 60);
          }
        }

        // 3) INSERT vào devices
        $sql = "INSERT INTO devices (
            device_id, display_type, client, data_source, product,
            model, manufacturer, manufacturing_date,
            cavities, hole_per_brush, brushes_per_cycle, process, mold_type,
            efficiency_lower_limit, lower_limit, target_limit, upper_limit,
            frequency, freq_check_limit, capacity, flex,
            history_count
          ) VALUES (
            :device_id, :display_type, :client, :data_source, :product,
            :model, :manufacturer, :manufacturing_date,
            :cavities, :hole_per_brush, :brushes_per_cycle, :process, :mold_type,
            :efficiency_lower_limit, :lower_limit, :target_limit, :upper_limit,
            :frequency, :freq_check_limit, :capacity, :flex,
            :history_count
          )";

        $stmt = $pdo->prepare($sql);
        $ok = $stmt->execute([
          ':device_id'               => $device_id,
          ':display_type'            => $display_type,
          ':client'                  => $client,
          ':data_source'             => $display_type, // giữ logic cũ
          ':product'                 => $product,

          ':model'                   => $model,
          ':manufacturer'            => $manufacturer,
          ':manufacturing_date'      => $manufacturing_date,

          ':cavities'                => $cavities,
          ':hole_per_brush'          => $hole_per_brush,
          ':brushes_per_cycle'       => $brushes_per_cycle,
          ':process'                 => $process,
          ':mold_type'               => $mold_type,

          ':efficiency_lower_limit'  => $eff_ll,
          ':lower_limit'             => $lower,
          ':target_limit'            => $target,
          ':upper_limit'             => $upper,

          ':frequency'               => $frequency,
          ':freq_check_limit'        => $freq_check_limit,
          ':capacity'                => $capacity,
          ':flex'                    => $flex,

          ':history_count'           => $history_count,
        ]);
        if (!$ok) throw new Exception('Insert failed');

        $newId = (int)$pdo->lastInsertId();

        // 4) Tạo trạng thái mặc định cho thiết bị mới
        $pdo->prepare("INSERT INTO device_status (device_id) VALUES (?)")->execute([$device_id]);

        // 5) AUDIT (create)
        $afterRow = [
          'id'                      => $newId,
          'device_id'               => $device_id,
          'display_type'            => $display_type,
          'client'                  => $client,
          'data_source'             => $display_type,
          'product'                 => $product,
          'model'                   => $model,
          'manufacturer'            => $manufacturer,
          'manufacturing_date'      => $manufacturing_date,
          'cavities'                => $cavities,
          'hole_per_brush'          => $hole_per_brush,
          'brushes_per_cycle'       => $brushes_per_cycle,
          'process'                 => $process,
          'mold_type'               => $mold_type,
          'efficiency_lower_limit'  => $eff_ll,
          'lower_limit'             => $lower,
          'target_limit'            => $target,
          'upper_limit'             => $upper,
          'frequency'               => $frequency,
          'freq_check_limit'        => $freq_check_limit,
          'capacity'                => $capacity,
          'flex'                    => $flex,
          'history_count'           => $history_count,
        ];
        //audit 
        $reason = require_reason();
        $afterRow['__entity'] = 'devices:create#'.$device_id;      
        $audit->log('create', $reason, [], $afterRow, $WHO);

        $pdo->commit();
        send_json_response(['status' => 'success', 'message' => 'Device added successfully.']);
      } break;



      case 'update': {
        capture_current_endpoint();

        // 1) BEFORE
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('Missing id');

        $st = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
        $st->execute([$id]);
        $beforeRow = (array)$st->fetch(PDO::FETCH_ASSOC);
        if (!$beforeRow) throw new Exception('Device not found');

        // 2) LẤY INPUT AN TOÀN (dùng ?? để tránh Warning)
        $device_id     = $_POST['device_id']     ?? $beforeRow['device_id'];
        $display_type  = $_POST['display_type']  ?? $beforeRow['display_type'];
        $product       = ($_POST['product']      ?? '') !== '' ? $_POST['product']      : null;
        $client        = ($_POST['client']       ?? '') !== '' ? $_POST['client']       : null;
        $model         = ($_POST['model']        ?? '') !== '' ? $_POST['model']        : null;
        $manufacturer  = ($_POST['manufacturer'] ?? '') !== '' ? $_POST['manufacturer'] : null;

        $manufacturing_date = trim($_POST['manufacturing_date'] ?? '');
        $manufacturing_date = $manufacturing_date !== '' ? $manufacturing_date : null;

        $cavities           = ($display_type==='mold'    && (($_POST['cavities'] ?? '')!==''))
                              ? $_POST['cavities'] : null;
        $hole_per_brush     = ($display_type==='tuft'    && (($_POST['hole_per_brush'] ?? '')!==''))
                              ? $_POST['hole_per_brush'] : null;
        $brushes_per_cycle  = ($display_type==='blister' && (($_POST['brushes_per_cycle'] ?? '')!==''))
                              ? $_POST['brushes_per_cycle'] : null;

        $process            = (in_array($display_type, ['mold','tuft','blister'], true) && (($_POST['process'] ?? '')!==''))
                              ? $_POST['process'] : null;
        $mold_type          = ($display_type==='mold' && (($_POST['mold_type'] ?? '')!==''))
                              ? $_POST['mold_type'] : null;

        $eff_ll    = (($_POST['efficiency_lower_limit'] ?? '') !== '') ? $_POST['efficiency_lower_limit'] : null;
        $lower     = (($_POST['lower_limit']            ?? '') !== '') ? $_POST['lower_limit']            : null;
        $target    = (($_POST['target_limit']           ?? '') !== '') ? $_POST['target_limit']           : null;
        $upper     = (($_POST['upper_limit']            ?? '') !== '') ? $_POST['upper_limit']            : null;

        $frequency        = $_POST['frequency']        ?? ($beforeRow['frequency']        ?? null);
        $freq_check_limit = $_POST['freq_check_limit'] ?? ($beforeRow['freq_check_limit'] ?? null);
        // flex: nếu checkbox không gửi, mặc định giữ nguyên giá trị cũ
        $flex             = array_key_exists('flex', $_POST) ? 1 : (int)($beforeRow['flex'] ?? 0);

        $history_count = array_key_exists('history_count', $_POST)
          ? (($_POST['history_count'] === '' || $_POST['history_count'] === null)
              ? null
              : max(0, (int)$_POST['history_count']))
          : ($beforeRow['history_count'] ?? null);

        // 3) TÍNH CAPACITY (dựa trên input mới/fallback)
        $capacity = null;
        $target_limit_num = ($target !== null && $target !== '') ? (float)$target : 0.0;
        if ($target_limit_num > 0) {
          if ($display_type === 'mold') {
            $cav = (int)($cavities ?? 0);
            if ($cav > 0) $capacity = (int)floor((3600 * $cav) / $target_limit_num);
          } elseif ($display_type === 'tuft') {
            $capacity = (int)floor(60 * $target_limit_num);
          } elseif ($display_type === 'blister') {
            $bpc = (int)($brushes_per_cycle ?? 0);
            if ($bpc > 0) $capacity = (int)floor($target_limit_num * $bpc * 60);
          }
        }

        // 4) UPDATE
        $sql = "UPDATE devices SET
          device_id = :device_id,
          display_type = :display_type,
          client = :client,
          data_source = :data_source,
          product = :product,
          model = :model,
          manufacturer = :manufacturer,
          manufacturing_date = :manufacturing_date,
          cavities = :cavities,
          hole_per_brush = :hole_per_brush,
          brushes_per_cycle = :brushes_per_cycle,
          process = :process,
          mold_type = :mold_type,
          efficiency_lower_limit = :efficiency_lower_limit,
          lower_limit = :lower_limit,
          target_limit = :target_limit,
          upper_limit = :upper_limit,
          frequency = :frequency,
          freq_check_limit = :freq_check_limit,
          capacity = :capacity,
          flex = :flex,
          history_count = :history_count
        WHERE id = :id";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          ':device_id'               => $device_id,
          ':display_type'            => $display_type,
          ':client'                  => $client,
          ':data_source'             => $display_type, // giữ logic cũ
          ':product'                 => $product,
          ':model'                   => $model,
          ':manufacturer'            => $manufacturer,
          ':manufacturing_date'      => $manufacturing_date,
          ':cavities'                => $cavities,
          ':hole_per_brush'          => $hole_per_brush,
          ':brushes_per_cycle'       => $brushes_per_cycle,
          ':process'                 => $process,
          ':mold_type'               => $mold_type,
          ':efficiency_lower_limit'  => $eff_ll,
          ':lower_limit'             => $lower,
          ':target_limit'            => $target,
          ':upper_limit'             => $upper,
          ':frequency'               => $frequency,
          ':freq_check_limit'        => $freq_check_limit,
          ':capacity'                => $capacity,
          ':flex'                    => $flex,
          ':history_count'           => $history_count,
          ':id'                      => $id,
        ]);

        // 5) AFTER cho audit
        $afterRow = $beforeRow;
        $afterRow['device_id']              = $device_id;
        $afterRow['display_type']           = $display_type;
        $afterRow['client']                 = $client;
        $afterRow['data_source']            = $display_type;
        $afterRow['product']                = $product;
        $afterRow['model']                  = $model;
        $afterRow['manufacturer']           = $manufacturer;
        $afterRow['manufacturing_date']     = $manufacturing_date;
        $afterRow['cavities']               = $cavities;
        $afterRow['hole_per_brush']         = $hole_per_brush;
        $afterRow['brushes_per_cycle']      = $brushes_per_cycle;
        $afterRow['process']                = $process;
        $afterRow['mold_type']              = $mold_type;
        $afterRow['efficiency_lower_limit'] = $eff_ll;
        $afterRow['lower_limit']            = $lower;
        $afterRow['target_limit']           = $target;
        $afterRow['upper_limit']            = $upper;
        $afterRow['frequency']              = $frequency;
        $afterRow['freq_check_limit']       = $freq_check_limit;
        $afterRow['capacity']               = $capacity;
        $afterRow['flex']                   = $flex;
        $afterRow['history_count']          = $history_count;

        //audit
        $reason = require_reason();
        $beforeRow['__entity'] = $afterRow['__entity'] = 'devices:update#'.$id; 
        $audit->log('update', $reason, $beforeRow, $afterRow, $WHO);

        $pdo->commit();
        send_json_response(['status' => 'success', 'message' => 'Device updated successfully.']);
      } break;





      case 'delete': {
        capture_current_endpoint();

        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) throw new Exception('Missing id');

        $st = $pdo->prepare("SELECT * FROM devices WHERE id = ?");
        $st->execute([$id]);
        $beforeRow = (array)$st->fetch(PDO::FETCH_ASSOC);
        if (!$beforeRow) throw new Exception('Device not found');

        $del = $pdo->prepare("DELETE FROM devices WHERE id = ?");
        $del->execute([$id]);
        if ($del->rowCount() < 1) {
          throw new Exception('Delete failed');
        }
        //Audit
        $reason = require_reason();
        $beforeRow['__entity'] = 'devices:delete#'.$id;            
        $audit->log('delete', $reason, $beforeRow, [], $WHO);

        //Done
        $pdo->commit();
        send_json_response(['status' => 'success', 'message' => 'Device deleted successfully.']);
      } break;


    default:
      if ($pdo->inTransaction()) $pdo->rollBack();
      send_json_response(['status'=>'error','message'=>"Unknown action \"$action\""]);

    }
  } catch (Exception $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    send_json_response(['status' => 'error', 'message' => $e->getMessage()]);
  }

  
}

function fetch_and_group_devices($pdo) {
  $stmt = $pdo->query("SELECT * FROM devices ORDER BY device_id ASC");
  $devices = $stmt->fetchAll();
  $grouped = ['mold'=>[], 'injection'=>[], 'tuft'=>[], 'end-rounding'=>[], 'blister'=>[]];
  foreach ($devices as $d) { if (isset($grouped[$d['display_type']])) $grouped[$d['display_type']][] = $d; }
  return $grouped;
}

$grouped_devices = fetch_and_group_devices($pdo);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>System Setting - EMS</title>
  <link rel="icon" type="image/png" href="../image/icon2.png">
  <link href="../assets/css/all.min.css" rel="stylesheet">
  <link href="../assets/css/local-fonts.css" rel="stylesheet">
  <link rel="stylesheet" href="../assets/css/translation.css">
  <script src="../assets/js/auth.js" defer></script>
  <script src="../assets/js/permissions.js" defer></script>

  <script src="../assets/js/translations.js"></script>

  <script src="../assets/js/tailwindcss.js"></script>

  <!-- API path: bản đơn giản, chắc chắn -->
  <script>
    window.EMS_USERNAME = <?=
    json_encode($AUTH['username'] ?? null, JSON_UNESCAPED_UNICODE)
    ?>;
    window.API = location.pathname.includes('/backend/') ? 'backend.php' : 'api.php';
    window.API_BACKEND = 'backend.php'; 
    document.addEventListener('DOMContentLoaded', async () => {
  try {
    const u = window.EMS_USERNAME;
    if (!u) {
      // console.warn('[perm] Không có EMS_USERNAME → bỏ qua gọi IAM');
      return;
    }

    // Gọi IAM qua permissions.js
    await EMSPermission.init(u);

    // Ẩn/hiện các phần tử có data-perm / data-perm-any trong backend.php
    EMSPermission.applyVisibility(document);

    // (Tuỳ chọn) phát sự kiện cho các script khác lắng nghe
    document.dispatchEvent(new CustomEvent('permissions:ready', {
      detail: { username: u, permissions: EMSPermission._list() }
    }));

    // (Tuỳ chọn) helper để app khác dùng nhanh
    window.can    = (code) => EMSPermission.has(code);
    window.canAny = (arr)  => EMSPermission.any(arr);
    window.canAll = (arr)  => EMSPermission.all(arr);

    // (Khuyên dùng) Tự áp quyền cho mọi DOM chèn mới
    const obs = new MutationObserver(muts => {
      muts.forEach(m => m.addedNodes.forEach(node => {
        if (node.nodeType === 1 && (
            node.matches?.('[data-perm],[data-perm-any]') ||
            node.querySelector?.('[data-perm],[data-perm-any]')
        )) {
          EMSPermission.applyVisibility(node);
        }
      }));
    });
    obs.observe(document.body, { childList:true, subtree:true });

  } catch (e) {
    console.error('[perm] Init permission lỗi:', e);
  }
});
  </script>

  <style>
    .tab-content{display:none}
    .tab-content.active{display:block}
    .top-tabs{ display:flex; gap:18px; border-bottom:1px solid #e5e7eb; margin:8px 0 12px; }
    .top-tabs .tab-btn{ padding:10px 6px; cursor:pointer; color:#6b7280; border-bottom:2px solid transparent; }
    .top-tabs .tab-btn.active{ color:#111827; border-color:#2563eb; font-weight:600; }
    .subtabs{ display:flex; gap:8px; margin:8px 0 12px; }
    .subtabs .subtab{ padding:6px 10px; border:1px solid #e5e7eb; border-radius:8px; background:#fff; cursor:pointer; }
    .subtabs .subtab.active{ background:#eef2ff; border-color:#c7d2fe; }
    table{ width:100%; border-collapse:collapse; background:#fff }
    th,td{ border:1px solid #eef2f7; padding:10px 12px; white-space:nowrap; text-align:left }
    thead th{ background:#f9fafb; color:#6b7280; font-weight:600 }
    .toast{ position:fixed; bottom:30px; left:50%; transform:translateX(-50%); padding:12px 24px; border-radius:8px; color:#fff; z-index:9999; opacity:0; visibility:hidden; transition:.3s; box-shadow:0 4px 12px rgba(0,0,0,.15)}
    .toast.active{ opacity:1; visibility:visible }
    .toast-success{ background:#28a745 } .toast-error{ background:#dc3545 }
    /* Modal visibility helpers */
    #device-modal.hidden { display:none; }
    #device-modal.active { display:block; }
    .btn-action-edit, .btn-action-del {
      border: none;
      background: transparent;
      cursor: pointer;
      font-size: 18px;   /* chỉnh size icon */
    }
    .icon-btn{background:transparent;border:0;cursor:pointer;font-size:18px;line-height:1;color:#c58a00}
    .icon-btn:hover{opacity:.85}
        /* ===== FIX CUỘN (theo yêu cầu) ===== */
        html, body { height: auto; overflow: auto; }
        .table-container-scroll { max-height: 75vh; overflow: auto; }
    /* Actions table polish */
    .table-actions { table-layout: fixed; border-collapse: separate; border-spacing: 0; }
    .table-actions thead th {
      position: sticky; top: 0; z-index: 1;
      background: #f3f4f6; color: #374151; font-weight: 600; text-transform: uppercase;
      border-bottom: 1px solid #e5e7eb;
    }
    .table-actions th, .table-actions td { padding: 10px 12px; vertical-align: middle; }
    .table-actions tbody tr:nth-child(odd) { background: #fafafa; }
    .table-actions tbody tr:hover { background: #f9fafb; }

    /* Truncate long texts but keep full via title */
    .cell-issue, .cell-plan {
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    /* Column widths (feel free to tweak) */
    .col-no    { width: 64px; text-align: left; }
    .col-owner { width: 140px; }
    .col-date  { width: 160px; }
    .col-manage{ width: 140px; text-align: center; }

    /* Badges */
    .badge { display:inline-flex; align-items:center; gap:6px;
      padding: 2px 8px; font-size: 12px; border-radius: 999px; border:1px solid transparent; }
    .badge-gray   { background:#f9fafb; border-color:#9ca3af; color:#374151; }
    .badge-green  { background:#ecfdf5; border-color:#10b981; color:#047857; }
    .badge-amber  { background:#fffbeb; border-color:#f59e0b; color:#92400e; }
    .badge-red    { background:#fef2f2; border-color:#ef4444; color:#991b1b; }

    /* Icon buttons */
    .icon-btn { display:inline-flex; align-items:center; justify-content:center;
      width:28px; height:28px; border-radius:6px; border:1px solid #e5e7eb; background:#fff;
      transition:transform .08s ease, box-shadow .12s ease; }
    .icon-btn:hover { transform: translateY(-1px); box-shadow:0 2px 6px rgba(0,0,0,.06); }
    .icon-edit  { color:#d97706; } /* amber-600 */
    .icon-del   { color:#dc2626; } /* red-600   */
    .icon-yes   { color:#16a34a; } /* green-600 */
    .icon-no    { color:#f59e0b; } /* amber-500 */

    /* Small helpers */
    .gap-8px { gap:8px; }
    .btn-new-action{display:none!important}

  /* Hàng chi tiết mở rộng */
    .tr-details { display:none; background:#fafafa; }
    .tr-details.show { display:table-row; }
    .details-box { border:1px solid #e5e7eb; border-radius:8px; padding:8px; }

    /* Nút mũi tên */
    .btn-expander { display:inline-flex; align-items:center; gap:6px; }
    .btn-expander i { transition:transform .2s ease; }
    .btn-expander[aria-expanded="true"] i { transform:rotate(90deg); }

    /* Pills gọn đẹp */
    .pill { display:inline-block; padding:2px 8px; font-size:12px; border:1px solid #e5e7eb; border-radius:999px; }
    .pill-muted { color:#6b7280; background:#f9fafb; }
    .pill-green { color:#065f46; background:#ecfdf5; border-color:#a7f3d0; }
    .pill-amber { color:#92400e; background:#fffbeb; border-color:#fcd34d; }
    .progress-dots { display:inline-flex; gap:4px; vertical-align:middle; }
    .progress-dots span{ width:6px;height:6px;border-radius:999px;background:#e5e7eb;display:inline-block }
    .progress-dots span.on{ background:#10b981 }
    /* Bảng ngoài: theo colgroup, không cho tự co */
    #device-actions-table{
      table-layout: fixed;
      width: 100%;
      min-width: 1100px;
      border-collapse: separate;
    }

    .table-container-scroll{
      max-height: 90vh;
      overflow: auto;        
      overflow-y: scroll;    
      scrollbar-gutter: stable both-edges;
      padding-right: 20px;
    }

    /* Chỉ truncate ở hàng chính; hàng chi tiết cho phép xuống dòng */
    #device-actions-table > thead > tr > th,
    #device-actions-table > tbody > tr:not(.tr-details) > td{
      overflow: hidden;
      text-overflow: ellipsis;
      white-space: nowrap;
    }

    /* Hàng chi tiết (expanded) */
    #device-actions-table .tr-details > td{
      white-space: normal;
      padding: 0.75rem;
    }

    /* Bảng con trong hàng chi tiết: độc lập, không kéo co bảng ngoài */
    #device-actions-modal #device-actions-table td table{
      width: 100% !important;
      min-width: 0 !important;
      table-layout: fixed !important;
    }

    /* Nếu trước đây có rule tổng quát dính cả bảng con, hạn chế lại */
    .table-actions table:not(#device-actions-table){
      min-width: 0 !important;
      width: 100% !important;
    }

    /* Ẩn/hiện hàng chi tiết */
    #device-actions-table .tr-details{ display: none; }
    #device-actions-table .tr-details.show{ display: table-row; }

    /* Fallback khi chưa hỗ trợ scrollbar-gutter */
    @supports not (scrollbar-gutter: stable){
      .table-container-scroll{ overflow-y: scroll; }
    }

    .top-tabs { display: none !important; }

    /* Giữ layout cố định, plan xuống dòng; các cột khác gọn lại */
    #device-actions-modal .plans-table{ table-layout: fixed; width:100%; min-width:0; }
    #device-actions-modal .plans-table th,
    #device-actions-modal .plans-table td{
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    /* chỉ cột plan được xuống dòng */
    #device-actions-modal .plans-table td.col-plan{
      white-space: normal !important;
      overflow: visible; text-overflow: clip;
      word-break: break-word; overflow-wrap: anywhere;
    }
    /* thu nhỏ padding tổng thể (tùy chọn) */
    #device-actions-modal .plans-table.compact th,
    #device-actions-modal .plans-table.compact td{ padding: .35rem .5rem; }

    /* canh giữa & chốt kích thước nhỏ cho 3 cột cần thu */
    #device-actions-modal .plans-table td.col-id,
    #device-actions-modal .plans-table td.col-owner,
    #device-actions-modal .plans-table td.col-status{
      text-align: center;
    }

    /* Nếu muốn “khóa” bề rộng bằng min/max thay vì chỉ width trong colgroup */
    #device-actions-modal .plans-table col.w-id     { width:7rem;   }
    #device-actions-modal .plans-table col.w-owner  { width:7rem;   }
    #device-actions-modal .plans-table col.w-status { width:6.25rem;}
    #device-actions-table .icon-btn[disabled],
    #device-actions-table .icon-btn.is-disabled { display: none !important; }
  </style>
</head>
<body class="min-h-screen flex flex-col bg-gray-100 font-sans ">
  <header class="shrink-0 bg-white shadow-sm">
    <div class="container mx-auto px-4 py-3 flex items-center justify-between">
      <div class="flex items-center gap-4">
        <a href="backend.php" class="flex items-center gap-4"><img src="../image/icon2.png" class="h-8 md:h-10" alt="Logo"></a>
        <a href="../index.html" class="flex items-center gap-4"><img src="../image/ems_.png" class="h-7 md:h-9" alt="EMS"></a>
        <span class="text-lg text-gray-600 font-semibold ml-4">translationManager.translate('System Settings')</span>
      </div>
      <div class="relative">
        <button id="menu-toggle-btn" class="p-2 rounded-full hover:bg-gray-100 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
          <svg class="h-6 w-6 text-gray-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/></svg>
        </button>
        <div id="header-dropdown" class="hidden absolute right-0 mt-2 w-56 bg-white rounded-md shadow-xl z-20">
          <div class="px-4 py-2">
            <p class="text-sm text-gray-500">Welcome,</p>
            <p class="font-semibold text-gray-800 truncate"><?php echo htmlspecialchars(($AUTH['username'] ?? '')); ?></p>
          </div>
            <a id="menu-open-action"
              href="login.php?return=./backend.php%23action"
              class="w-full text-left flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100"
              data-perm = 'view.list.action'>
              <i class="fas fa-tasks w-5 text-center"></i><span>Action</span>
            </a>

          <hr class="my-1 border-gray-200">
          <div class="py-1">
            <button id="add-device-btn" class="w-full text-left flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100" data-perm = 'device.add.ems'><i class="fas fa-plus w-5 text-center"></i><span>Add New Device</span></button>
            <a href="../index.html" class="flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100"><i class="fas fa-chart-line w-5 text-center"></i><span>Monitoring Dashboard</span></a>
            <a href="backend.php" class="flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100"><i class="fas fa-cogs w-5 text-center"></i><span>System Settings</span></a>
            <hr class="my-1 border-gray-200">
            <a href="logout.php" id="logout-link" class="flex items-center gap-3 px-4 py-2 text-gray-700 hover:bg-gray-100"><i class="fas fa-sign-out-alt w-5 text-center"></i><span>Logout</span></a>
          </div>
        </div>
      </div>
    </div>
    
  </header>

  <main class="container mx-auto p-1">
    <div class="top-tabs">
      <div class="tab-btn active" data-top-tab="config">Config</div>
      <div class="tab-btn" data-top-tab="action">Action</div>
    </div>

    <!-- TAB: Config -->
    <div id="tab-config" class="tab-content active">
      <div class="mb-6 border-b border-gray-200 w-full h">
        <nav class="flex gap-2 flex-wrap" aria-label="Tabs" id="config-tabs">
  <button data-tab="mold"
    class="tab px-4 py-2 rounded-lg border text-sm font-medium text-gray-700 bg-white shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400">
    translationManager.translate('Mold') (<?= count($grouped_devices['mold']) ?>)
  </button>

  <button data-tab="injection"
    class="tab px-4 py-2 rounded-lg border text-sm font-medium text-gray-700 bg-white shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400">
    translationManager.translate('Injection') (<?= count($grouped_devices['injection']) ?>)
  </button>

  <button data-tab="tuft"
    class="tab px-4 py-2 rounded-lg border text-sm font-medium text-gray-700 bg-white shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400">
    translationManager.translate('Tufting') (<?= count($grouped_devices['tuft']) ?>)
  </button>

  <button data-tab="end-rounding"
    class="tab px-4 py-2 rounded-lg border text-sm font-medium text-gray-700 bg-white shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400">
    translationManager.translate('End-rounding') (<?= count($grouped_devices['end-rounding']) ?>)
  </button>

  <button data-tab="blister"
    class="tab px-4 py-2 rounded-lg border text-sm font-medium text-gray-700 bg-white shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-400">
    translationManager.translate('Blister') (<?= count($grouped_devices['blister']) ?>)
  </button>
</nav>

      </div>

      <div id="tab-content-container">
        <!-- Mold -->
        <div id="mold" class="tab-content active">
          <div class="bg-white shadow-lg rounded-lg table-container-scroll h-full ">
            <table class="min-w-full divide-y divide-gray-200">
            <thead class="bg-gray-50">
              <tr>
                <th class="text-left  text-xs font-semibold text-gray-600 uppercase">translationManager.translate('Mold') ID</th>
                <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Family</th>
                <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Client</th>
                
                <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Model</th>
                <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Manufacturer</th>
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">Mfg. Date</th>
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">Cavities</th>
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">Capacity (1h)</th>

                <!-- ⬇️ THÊM 2 CỘT MỚI -->
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">Total Count</th>
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">Unit</th>
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">History Count</th>
                <!-- ⬆️ -->

                <th class="text-center text-xs font-semibold text-gray-600 uppercase">translationManager.translate('Process')</th>
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">Mold Type</th>
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">Efficiency Limit(%)</th>
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">Limits (s) (LL/TG/UL)</th>
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">Fre. Check Connected (s)</th>
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">Fre. Check Limit (s)</th>
                <th class="text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
              </tr>
            </thead>

            <tbody class="bg-white divide-y divide-gray-200">
              <?php foreach ($grouped_devices['mold'] as $device):
                $cap = '-';
                if (!empty($device['cavities']) && !empty($device['target_limit']) && $device['target_limit'] > 0) {
                  $cap = floor((3600 * (int)$device['cavities']) / (float)$device['target_limit']);
                }
                $totalCount = isset($device['total_count']) ? (int)$device['total_count'] : null;
                $unit       = isset($device['unit']) ? (string)$device['unit'] : null;
              ?>
                <tr>
                  <td class="text-sm font-medium text-gray-900"><?= htmlspecialchars($device['device_id']) ?></td>
                  <td class="text-sm text-gray-600"><?= htmlspecialchars($device['product'] ?? 'N/A') ?></td>
                  <td class="text-sm text-gray-600"><?= htmlspecialchars($device['client'] ?? '-') ?></td>
                  <td class="text-sm text-gray-600"><?= htmlspecialchars($device['model'] ?? '-') ?></td>
                  <td class="text-sm text-gray-600"><?= htmlspecialchars($device['manufacturer'] ?? '-') ?></td>
                  <td class="text-sm text-gray-600 text-center">
                    <?= !empty($device['manufacturing_date']) ? htmlspecialchars(substr($device['manufacturing_date'], 0, 10)) : '-' ?>
                  </td>

                  <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['cavities'] ?? '-') ?></td>
                  <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($cap) ?></td>

                  <!-- ⬇️ HIỂN THỊ TOTAL COUNT & UNIT -->
                  <td class="text-sm text-gray-600 text-center">
                    <?= ($totalCount !== null) ? number_format($totalCount) : '-' ?>
                  </td>
                  <?php $displayUnit = $unit !== null && $unit !== '' ? htmlspecialchars($unit) : '-'; ?>
                  <td id="unit-cell" class="text-sm text-gray-600 text-center" data-translate-dynamic="unit">
                    <?= $displayUnit ?>
                  </td>
                  <!-- ⬆️ -->
                   <td class="text-sm text-gray-600 text-center">
                    <?= isset($device['history_count']) && $device['history_count'] !== '' 
                          ? number_format((int)$device['history_count']) 
                          : '-' ?>
                  </td>

                  <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['process'] ?? '-') ?></td>
                  <td class="text-sm text-gray-600 text-center" data-translate-dynamic="mold_type"><?= htmlspecialchars($device['mold_type'] ?? '-') ?></td>
                  <td class="text-sm text-gray-600 text-center"><?= isset($device['efficiency_lower_limit']) ? (int)$device['efficiency_lower_limit'] : '-' ?></td>
                  <td class="text-sm text-gray-600 text-center">
                    <?= htmlspecialchars($device['lower_limit'] ?? '-') ?> |
                    <?= htmlspecialchars($device['target_limit'] ?? '-') ?> |
                    <?= htmlspecialchars($device['upper_limit'] ?? '-') ?>
                  </td>
                  <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['frequency']) ?></td>
                  <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['freq_check_limit']) ?></td>
                  <td class="text-center text-sm font-medium space-x-4">
                    <button class="edit-btn text-yellow-600 hover:text-yellow-800"
                            data-perm = 'device.update.ems'
                            data-device='<?= htmlspecialchars(json_encode($device), ENT_QUOTES, 'UTF-8') ?>'>
                      <i class="fas fa-edit"></i>
                    </button>
                    <button class="delete-btn text-red-600 hover:text-red-800"
                            data-perm = 'device.delete.ems'
                            data-id="<?= (int)$device['id'] ?>"
                            data-device-id="<?= htmlspecialchars($device['device_id']) ?>">
                      <i class="fas fa-trash-alt"></i>
                    </button>
                  </td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>

          </div>
        </div>

        <!-- Injection -->
          <div id="injection" class="tab-content">
            <div class="bg-white shadow-lg rounded-lg table-container-scroll">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Injection ID</th>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Family</th>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Client</th>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Model</th>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Manufacturer</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Mfg. Date</th>

                    <!-- ⬇️ THÊM -->
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Total Count</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Unit</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">History Count</th>      
                    <!-- ⬆️ -->

                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Efficiency Limit(%)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Limits (LL/TG/UL)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Frequency Check Connected (s)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Frequency Check Limit (s)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php foreach ($grouped_devices['injection'] as $device): 
                    $totalCount = isset($device['total_count']) ? (int)$device['total_count'] : null;
                    $unit       = $device['unit'] ?? null;
                  ?>
                  <tr>
                    <td class="text-sm font-medium text-gray-900"><?= htmlspecialchars($device['device_id']) ?></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['product'] ?? 'N/A') ?></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['client'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['model'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['manufacturer'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600 text-center">
                      <?= !empty($device['manufacturing_date']) ? htmlspecialchars(substr($device['manufacturing_date'],0,10)) : '-' ?>
                    </td>

                    <!-- ⬇️ THÊM -->
                    <td class="text-sm text-gray-600 text-center">
                      <?= ($totalCount !== null) ? number_format($totalCount) : '-' ?>
                    </td>
                    <td class="text-sm text-gray-600 text-center">
                      <?= ($unit !== null && $unit !== '') ? htmlspecialchars($unit) : '-' ?>
                    </td>
                    <!-- ⬆️ -->
                    <td class="text-sm text-gray-600 text-center">
                      <?= isset($device['history_count']) && $device['history_count'] !== '' 
                            ? number_format((int)$device['history_count']) 
                            : '-' ?>
                    </td>

                    <td class="text-sm text-gray-600 text-center"><?= isset($device['efficiency_lower_limit']) ? (int)$device['efficiency_lower_limit'] : '-' ?></td>
                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['lower_limit'] ?? '-') ?> | <?= htmlspecialchars($device['target_limit'] ?? '-') ?> | <?= htmlspecialchars($device['upper_limit'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['frequency']) ?></td>
                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['freq_check_limit']) ?></td>
                    <td class="text-center text-sm font-medium space-x-4">
                      <button class="edit-btn text-yellow-600 hover:text-yellow-800" data-device='<?= htmlspecialchars(json_encode($device), ENT_QUOTES, 'UTF-8') ?>'><i class="fas fa-edit"></i></button>
                      <button class="delete-btn text-red-600 hover:text-red-800" data-id="<?= (int)$device['id'] ?>" data-device-id="<?= htmlspecialchars($device['device_id']) ?>"><i class="fas fa-trash-alt"></i></button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

        <!-- End-rounding -->
          <div id="end-rounding" class="tab-content">
            <div class="bg-white shadow-lg rounded-lg table-container-scroll">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">End-rounding ID</th>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Family</th>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Client</th>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Model</th>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Manufacturer</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Mfg. Date</th>

                    <!-- ⬇️ THÊM -->
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Total Count</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Unit</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">History Count</th>
                    <!-- ⬆️ -->

                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Efficiency Limit(%)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Limits (LL/TG/UL)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Frequency Check Connected (s)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Frequency Check Limit (s)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php foreach ($grouped_devices['end-rounding'] as $device):
                    $totalCount = isset($device['total_count']) ? (int)$device['total_count'] : null;
                    $unit       = $device['unit'] ?? null;
                  ?>
                  <tr>
                    <td class="text-sm font-medium text-gray-900"><?= htmlspecialchars($device['device_id']) ?></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['product'] ?? 'N/A') ?></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['client'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['model'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['manufacturer'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600 text-center">
                      <?= !empty($device['manufacturing_date']) ? htmlspecialchars(substr($device['manufacturing_date'],0,10)) : '-' ?>
                    </td>

                    <!-- ⬇️ THÊM -->
                    <td class="text-sm text-gray-600 text-center">
                      <?= ($totalCount !== null) ? number_format($totalCount) : '-' ?>
                    </td>
                    <td class="text-sm text-gray-600 text-center">
                      <?= ($unit !== null && $unit !== '') ? htmlspecialchars($unit) : '-' ?>
                    </td>
                    <!-- ⬆️ -->
                    <td class="text-sm text-gray-600 text-center">
                      <?= isset($device['history_count']) && $device['history_count'] !== '' 
                            ? number_format((int)$device['history_count']) 
                            : '-' ?>
                    </td>

                    <td class="text-sm text-gray-600 text-center"><?= isset($device['efficiency_lower_limit']) ? (int)$device['efficiency_lower_limit'] : '-' ?></td>
                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['lower_limit'] ?? '-') ?> | <?= htmlspecialchars($device['target_limit'] ?? '-') ?> | <?= htmlspecialchars($device['upper_limit'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['frequency']) ?></td>
                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['freq_check_limit']) ?></td>
                    <td class="text-center text-sm font-medium space-x-4">
                      <button class="edit-btn text-yellow-600 hover:text-yellow-800" data-device='<?= htmlspecialchars(json_encode($device), ENT_QUOTES, 'UTF-8') ?>'><i class="fas fa-edit"></i></button>
                      <button class="delete-btn text-red-600 hover:text-red-800" data-id="<?= (int)$device['id'] ?>" data-device-id="<?= htmlspecialchars($device['device_id']) ?>"><i class="fas fa-trash-alt"></i></button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>


        <!-- Tuft -->
          <div id="tuft" class="tab-content">
            <div class="bg-white shadow-lg rounded-lg table-container-scroll">
              <table class="min-w-full divide-y divide-gray-200">
                <thead class="bg-gray-50">
                  <tr>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Tufting ID</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Flex</th>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Family</th>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Client</th>

                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Model</th>
                    <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Manufacturer</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Mfg. Date</th>

                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Capacity</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Hole/Brush</th>

                    <!-- ⬇️ THÊM -->
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Total Count</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Unit</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">History Count</th>
                    <!-- ⬆️ -->

                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">translationManager.translate('Process')</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Efficiency Limit(%)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Limits (pcs) (LL/TG/UL)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Frequency Check Connected (s)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Frequency Check Limit (s)</th>
                    <th class="text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                  </tr>
                </thead>
                <tbody class="bg-white divide-y divide-gray-200">
                  <?php foreach ($grouped_devices['tuft'] as $device):
                    $cap='-'; if (!empty($device['target_limit']) && $device['target_limit']>0) $cap=floor(60*$device['target_limit']);
                    $totalCount = isset($device['total_count']) ? (int)$device['total_count'] : null;
                    $unit       = $device['unit'] ?? null;
                  ?>
                  <tr>
                    <td class="text-sm font-medium text-gray-900"><?= htmlspecialchars($device['device_id']) ?></td>
                    <td class="text-sm text-gray-600 text-center"><input type="checkbox" class="h-4 w-4" disabled <?= $device['flex']? 'checked':'' ?>></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['product'] ?? 'N/A') ?></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['client'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['model'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600"><?= htmlspecialchars($device['manufacturer'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600 text-center">
                      <?= !empty($device['manufacturing_date']) ? htmlspecialchars(substr($device['manufacturing_date'],0,10)) : '-' ?>
                    </td>

                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($cap) ?></td>
                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['hole_per_brush'] ?? '-') ?></td>

                    <!-- ⬇️ THÊM -->
                    <td class="text-sm text-gray-600 text-center">
                      <?= ($totalCount !== null) ? number_format($totalCount) : '-' ?>
                    </td>
                    <td class="text-sm text-gray-600 text-center">
                      <?= ($unit !== null && $unit !== '') ? htmlspecialchars($unit) : '-' ?>
                    </td>
                    <!-- ⬆️ -->
                    <td class="text-sm text-gray-600 text-center">
                      <?= isset($device['history_count']) && $device['history_count'] !== '' 
                            ? number_format((int)$device['history_count']) 
                            : '-' ?>
                    </td>

                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['process'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600 text-center"><?= isset($device['efficiency_lower_limit']) ? (int)$device['efficiency_lower_limit'] : '-' ?></td>
                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['lower_limit'] ?? '-') ?> | <?= htmlspecialchars($device['target_limit'] ?? '-') ?> | <?= htmlspecialchars($device['upper_limit'] ?? '-') ?></td>
                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['frequency']) ?></td>
                    <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['freq_check_limit']) ?></td>
                    <td class="text-center text-sm font-medium space-x-4">
                      <button class="edit-btn text-yellow-600 hover:text-yellow-800" data-device='<?= htmlspecialchars(json_encode($device), ENT_QUOTES, 'UTF-8') ?>'><i class="fas fa-edit"></i></button>
                      <button class="delete-btn text-red-600 hover:text-red-800" data-id="<?= (int)$device['id'] ?>" data-device-id="<?= htmlspecialchars($device['device_id']) ?>"><i class="fas fa-trash-alt"></i></button>
                    </td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>

        <!-- Blister -->
        <div id="blister" class="tab-content">
          <div class="bg-white shadow-lg rounded-lg table-container-scroll">
            <table class="min-w-full divide-y divide-gray-200">
              <thead class="bg-gray-50">
                <tr>
                  <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Blister ID</th>
                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">Flex</th>
                  <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Family</th>
                  <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Client</th>

                  <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Model</th>
                  <th class="text-left  text-xs font-semibold text-gray-600 uppercase">Manufacturer</th>
                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">Mfg. Date</th>

                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">Capacity</th>
                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">Brushes/Cycle</th>

                  <!-- ⬇️ THÊM -->
                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">Total Count</th>
                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">Unit</th>
                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">History Count</th>
                  <!-- ⬆️ -->

                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">Efficiency Limit(%)</th>
                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">Limits (cycles) (LL/TG/UL)</th>
                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">Frequency Check Connected (s)</th>
                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">Frequency Check Limit (s)</th>
                  <th class="text-center text-xs font-semibold text-gray-600 uppercase">Actions</th>
                </tr>
              </thead>
              <tbody class="bg-white divide-y divide-gray-200">
                <?php foreach ($grouped_devices['blister'] as $device):
                  $cap='-'; if (!empty($device['brushes_per_cycle']) && !empty($device['target_limit'])) $cap=floor(intval($device['brushes_per_cycle'])*intval($device['target_limit'])*60);
                  $totalCount = isset($device['total_count']) ? (int)$device['total_count'] : null;
                  $unit       = $device['unit'] ?? null;
                ?>
                <tr>
                  <td class="text-sm font-medium text-gray-900"><?= htmlspecialchars($device['device_id']) ?></td>
                  <td class="text-sm text-gray-600 text-center"><input type="checkbox" class="h-4 w-4" disabled <?= $device['flex']? 'checked':'' ?>></td>
                  <td class="text-sm text-gray-600"><?= htmlspecialchars($device['product'] ?? 'N/A') ?></td>
                  <td class="text-sm text-gray-600"><?= htmlspecialchars($device['client'] ?? '-') ?></td>
                  <td class="text-sm text-gray-600"><?= htmlspecialchars($device['model'] ?? '-') ?></td>
                  <td class="text-sm text-gray-600"><?= htmlspecialchars($device['manufacturer'] ?? '-') ?></td>
                  <td class="text-sm text-gray-600 text-center">
                    <?= !empty($device['manufacturing_date']) ? htmlspecialchars(substr($device['manufacturing_date'],0,10)) : '-' ?>
                  </td>

                  <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($cap) ?></td>
                  <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['brushes_per_cycle'] ?? '-') ?></td>

                  <!-- ⬇️ THÊM -->
                  <td class="text-sm text-gray-600 text-center">
                    <?= ($totalCount !== null) ? number_format($totalCount) : '-' ?>
                  </td>
                  <td class="text-sm text-gray-600 text-center">
                    <?= ($unit !== null && $unit !== '') ? htmlspecialchars($unit) : '-' ?>
                  </td>
                  <!-- ⬆️ -->
                  <td class="text-sm text-gray-600 text-center">
                    <?= isset($device['history_count']) && $device['history_count'] !== '' 
                          ? number_format((int)$device['history_count']) 
                          : '-' ?>
                  </td>

                  <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['efficiency_lower_limit'] ?? '-') ?></td>
                  <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['lower_limit'] ?? '-') ?> | <?= htmlspecialchars($device['target_limit'] ?? '-') ?> | <?= htmlspecialchars($device['upper_limit'] ?? '-') ?></td>
                  <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['frequency']) ?></td>
                  <td class="text-sm text-gray-600 text-center"><?= htmlspecialchars($device['freq_check_limit']) ?></td>
                  <td class="text-center text-sm font-medium space-x-4">
                    <button class="edit-btn text-yellow-600 hover:text-yellow-800" data-device='<?= htmlspecialchars(json_encode($device), ENT_QUOTES, 'UTF-8') ?>'><i class="fas fa-edit"></i></button>
                    <button class="delete-btn text-red-600 hover:text-red-800" data-id="<?= (int)$device['id'] ?>" data-device-id="<?= htmlspecialchars($device['device_id']) ?>"><i class="fas fa-trash-alt"></i></button>
                  </td>
                </tr>
                <?php endforeach; ?>
              </tbody>
            </table>
          </div>
        </div>

      </div>

      <!-- ========== MODAL + FORM CONFIG (ADD / EDIT) ========== -->
<div id="device-modal" class="fixed inset-0 z-50 hidden">
  <div class="absolute inset-0 bg-black bg-opacity-40"></div>
  <div class="relative mx-auto my-8 w-[min(1100px,95vw)] max-h-[90vh] overflow-auto rounded-xl bg-white p-5 shadow-2xl">
    <div class="flex items-center justify-between">
      <h3 id="modal-title" class="text-xl font-semibold text-gray-800" data-perm = 'device.add.ems'>Add New Device</h3>
      <button id="modal-close" class="p-2 rounded hover:bg-gray-100">
        <i class="fas fa-times"></i>
      </button>
    </div>
    <hr class="my-3"/>

    <form id="device-form" class="space-y-6">
  <input type="hidden" id="form-action" name="action" value="add">
  <input type="hidden" id="id" name="id" value="">

  <!-- ROW 1 -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label id="label-device-id" class="block text-sm text-gray-600 mb-1">Device ID (Unique)</label>
      <input id="device_id" name="device_id" class="w-full border rounded-lg px-3 py-2" required>
    </div>
    <div>
      <label class="block text-sm text-gray-600 mb-1">Machine Type</label>
      <select id="display_type" name="display_type" class="w-full border rounded-lg px-3 py-2" required>
        <option value="mold">Mold</option>
        <option value="injection">Injection</option>
        <option value="tuft">Tufting</option>
        <option value="end-rounding">End-rounding</option>
        <option value="blister">Blister</option>
      </select>
    </div>
  </div>

  <!-- ROW 2 -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm text-gray-600 mb-1">Family</label>
      <input id="product" name="product" class="w-full border rounded-lg px-3 py-2">
    </div>
    
    <div>
      <label class="block text-sm text-gray-600 mb-1">Client</label>
      <select id="client" name="client" class="w-full border rounded-lg px-3 py-2">
        <option value="">-- Select Client --</option>
        <option value="P&G">P&G</option>
        <option value="Unilever">Unilever</option>
        <option value="Wisdom">Wisdom</option>
        <option value="Jordan">Jordan</option>
        <option value="AK Alpha">AK Alpha</option>
        <option value="Alpha">Alpha</option>
      </select>
    </div>

    <!-- Process (by type) -->
    <div id="process_mold_wrap" class="form-section" style="display:none;">
      <label class="block text-sm text-gray-600 mb-1" >translationManager.translate('Process')</label>
      <!-- giữ name="process" như server expect -->
       <script>
          document.addEventListener("DOMContentLoaded", () => {
            let selectProcessString = window.translationManager.translate('Select') + " " + window.translationManager.translate('Process');
            document.querySelector("#process_mold option:first-child").textContent = "-- " + selectProcessString + " --";
            let selectTypeString = window.translationManager.translate('Select') + " " + window.translationManager.translate('Type');
            document.querySelector("#mold_type option:first-child").textContent = "-- " + selectTypeString + " --";
          });
       </script>
      <select id="process_mold" name="process" class="w-full border rounded-lg px-3 py-2">
        <option value=""></option>
        <option>1st</option>
        <option>2nd</option>
        <option>3rd</option>
      </select>
    </div>
    <div id="process_tuft_wrap" class="form-section" style="display:none;">
      <label class="block text-sm text-gray-600 mb-1" data-translate="Process">Process</label>
      <input id="process_tuft" class="w-full border rounded-lg px-3 py-2" placeholder="e.g. Trim, Drill">
    </div>
    <div id="process_blister_wrap" class="form-section" style="display:none;">
      <label class="block text-sm text-gray-600 mb-1" data-translate="Process">Process</label>
      <input id="process_blister" class="w-full border rounded-lg px-3 py-2" placeholder="e.g. Pack">
    </div>
  </div>

  <!-- ROW 3 (type-specific) -->
  <div id="mold-fields" class="form-section" style="display:none;">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-600 mb-1">Number of Cavities</label>
        <input id="cavities" name="cavities" type="number" min="0" class="w-full border rounded-lg px-3 py-2">
      </div>
      <div>
        <label class="block text-sm text-gray-600 mb-1">Mold Type</label>
        <select id="mold_type" name="mold_type" class="w-full border rounded-lg px-3 py-2">
          <option value=""></option>
          <option>Shift Insert</option>
          <option>Manual</option>
          <option>Auto</option>
        </select>
      </div>
    </div>
  </div>

  <div id="tuft-fields" class="form-section" style="display:none;">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-600 mb-1">Hole / Brush</label>
        <input id="hole_per_brush" name="hole_per_brush" type="number" min="0" class="w-full border rounded-lg px-3 py-2">
      </div>
      <div id="flex-field" style="display:none;">
        <label class="block text-sm text-gray-600 mb-1">Flex</label>
        <div class="h-[42px] flex items-center">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" id="flex" name="flex" class="h-4 w-4">
            <span class="text-sm text-gray-700">Enable flexible line</span>
          </label>
        </div>
      </div>
    </div>
  </div>

  <div id="blister-fields" class="form-section" style="display:none;">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <div>
        <label class="block text-sm text-gray-600 mb-1">Brushes / Cycle</label>
        <input id="brushes_per_cycle" name="brushes_per_cycle" type="number" min="0" class="w-full border rounded-lg px-3 py-2">
      </div>
      <div id="flex-field-bl" style="display:none;">
        <label class="block text-sm text-gray-600 mb-1">Flex</label>
        <div class="h-[42px] flex items-center">
          <label class="inline-flex items-center gap-2">
            <input type="checkbox" id="flex_bl" class="h-4 w-4">
            <span class="text-sm text-gray-700">Enable flexible line</span>
          </label>
        </div>
      </div>
    </div>
  </div>

  <!-- Divider -->
  <hr class="border-gray-200"/>

  <!-- Efficiency + Limits -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm text-gray-600 mb-1">Efficiency Limit (%)</label>
      <input id="efficiency_lower_limit" name="efficiency_lower_limit" type="number" min="0" max="100" class="w-full border rounded-lg px-3 py-2">
    </div>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
  <div>
    <label class="block text-sm text-gray-600 mb-1">History Count</label>
    <input id="history_count" name="history_count" type="number" min="0" class="w-full border rounded-lg px-3 py-2" placeholder="e.g. 1000">
  </div>
</div>
  </div>

  <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
    <div>
    <label class="block text-sm text-gray-600 mb-1">Model</label>
    <input id="model" name="model" class="w-full border rounded-lg px-3 py-2">
  </div>
  <div>
    <label class="block text-sm text-gray-600 mb-1">Manufacturer</label>
    <input id="manufacturer" name="manufacturer" class="w-full border rounded-lg px-3 py-2">
  </div>
  <div>
    <label class="block text-sm text-gray-600 mb-1">Manufacturing Date</label>
    <input id="manufacturing_date" name="manufacturing_date" type="date" class="w-full border rounded-lg px-3 py-2">
  </div>
    <div>
      <label id="label-lower" class="block text-sm text-gray-600 mb-1" data-translate="Lower Limit (s)">Lower Limit (s)</label>
      <input id="lower_limit" name="lower_limit" type="number" step="any" class="w-full border rounded-lg px-3 py-2">
    </div>
    <div>
      <label id="label-target" class="block text-sm text-gray-600 mb-1" data-translate="Target (s)">Target (s)</label>
      <input id="target_limit" name="target_limit" type="number" step="any" class="w-full border rounded-lg px-3 py-2">
    </div>
    <div>
      <label id="label-upper" class="block text-sm text-gray-600 mb-1" data-translate="Upper Limit (s)">Upper Limit (s)</label>
      <input id="upper_limit" name="upper_limit" type="number" step="any" class="w-full border rounded-lg px-3 py-2">
    </div>
  </div>

  <!-- Divider -->
  <hr class="border-gray-200"/>

  <!-- Frequency -->
  <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
    <div>
      <label class="block text-sm text-gray-600 mb-1">Frequency Check Connected (s)</label>
      <input id="frequency" name="frequency" type="number" min="0" class="w-full border rounded-lg px-3 py-2" value="300">
    </div>
    <div>
      <label class="block text-sm text-gray-600 mb-1">Frequency Check Limit (s)</label>
      <input id="freq_check_limit" name="freq_check_limit" type="number" min="0" class="w-full border rounded-lg px-3 py-2" value="60">
    </div>
  </div>
<div class="grid grid-cols-1">
  <div>
    <label class="block text-sm text-gray-600 mb-1" data-translate="Reason">Reason</label>
    <textarea id="reason" name="reason" required
      class="w-full border rounded-lg px-3 py-2 min-h-[70px]"
      placeholder="Nhập lý do cho thao tác này..."></textarea>
  </div>
</div>
  <!-- FOOTER -->
  <div class="flex items-center justify-end gap-3 pt-2">
    <button type="button" id="btn-cancel" class="px-4 py-2 rounded border bg-white hover:bg-gray-50">Cancel</button>
        <button type="submit" id="btn-save" class="px-4 py-2 rounded bg-blue-600 text-white hover:bg-blue-700">
          <i class="fas fa-save mr-2"></i>Save</button>
        </div>
    </form>
  </div>
</div>
    </div>
    <!-- TAB: Action (tóm tắt) -->
    <div id="tab-action" class="tab-content ">
      <div class="table-container-scroll">
        <!-- ========================= Action Board (view-only) ========================= -->
<div id="action-board-wrap" class="mt-4">
  <div class="flex items-center gap-2 mb-3">
    <!-- Filter process -->
    <div class="flex gap-2" id="proc-filters">
      <button class="px-3 py-1 rounded border bg-gray-800 text-white" data-proc="all">All</button>
      <button class="px-3 py-1 rounded border" data-proc="mold">Mold</button>
      <button class="px-3 py-1 rounded border" data-proc="injection">Injection</button>
      <button class="px-3 py-1 rounded border" data-proc="tuft">Tufting</button>
      <button class="px-3 py-1 rounded border" data-proc="blister">Blister</button>
      <!-- <button class="px-3 py-1 rounded border" data-proc="end-rounding">End-rounding</button> -->
    </div>

    <!-- Tabs -->
    <div class="ml-auto flex gap-2">
      <button id="tabActive" class="px-3 py-1 rounded bg-blue-600 text-white">In progress</button>
      <button id="tabDone"   class="px-3 py-1 rounded border">Complete</button>
    </div>
  </div>

  <div class="rounded-md overflow-hidden border overflow-x-auto">
  <table id="action-board" class="min-w-full text-sm bg-white">
    <thead class="bg-gray-100 text-gray-600">
      <tr>
        <th class="p-2 text-left">Machine Type</th>
        <th class="p-2 text-left">Machine ID</th>
        <th class="p-2 text-left">Issue Type</th>
        <th class="p-2 text-left">Issue ID</th>
        <th class="p-2 text-left">Description Of Issue</th>
        <th class="p-2 text-center" data-translate="Created Date">Created Date</th>
        <th class="p-2 text-center whitespace-nowrap" data-translate="Planned completion date">Planned Completion Date</th>
        <th class="p-2 text-center whitespace-nowrap">Creator</th>
        <th class="p-2 text-center">Status</th>
        <th class="p-2 text-center">Action Plans</th>
        <th class="p-2 text-center">Action</th>
      </tr>
    </thead>
    <tbody id="action-board-tbody">
      <tr><td colspan="9" class="p-3 text-center text-gray-500">Loading…</td></tr>
    </tbody>
  </table>
          </div>
        </div>
      </div>
    </div>
  </main>


<script>





(function () {
  function getCookie(name){
    return document.cookie.split('; ').find(x => x.startsWith(name+'='))?.split('=')[1] || '';
  }
  function getToken() {
    try { return localStorage.getItem('ems_token') || getCookie('ems_token') || ''; } catch { return ''; }
  }

  window.authFetch = async function (url, opts = {}) {
    const t = getToken();
    const headers = new Headers(opts.headers || {});
    if (t && !headers.has('Authorization')) headers.set('Authorization', 'Bearer ' + t);

    const res = await fetch(url, {
      credentials: 'include',
      cache: 'no-store',
      ...opts,
      headers
    });

    if (res.status === 401 && !opts.noAuthRedirect) {
      try { localStorage.removeItem('ems_token'); } catch {}
      const ret = opts.returnTo ?? (location.pathname + location.search + location.hash);
      location.href = `login.php?return=${encodeURIComponent(ret)}`;
    }

    if (res.status === 403 && !opts.noAuthRedirect) {
    }

    return res;
  };
})();

document.getElementById('logout-link')?.addEventListener('click', () => {
  try { localStorage.removeItem('ems_token'); } catch {}

});



(function(){
  const API = window.API_BASE || '../api.php';  
  // Helpers
  const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));
  const fmt = s => {
    if (!s) return '-';
    const m = String(s).match(/^(\d{4}-\d{2}-\d{2})/);
    return m ? m[1] : String(s);
  };
  const fmtDT = (s) => {
  if (!s) return '-';
  const d = new Date(String(s).replace(' ', 'T'));
  if (isNaN(d)) return esc(s);           
  const p = n => String(n).padStart(2,'0');
  return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
};

function computeStatusFromPlans(done, total) {
  done  = +done  || 0;
  total = +total || 0;
  if (total <= 0 || done === 0) return 'open';
  if (done < total) return 'in_progress';
  return 'complete';
}
function normalizeStatus(raw, done, total){
  const t = String(raw || '').toLowerCase();
  if (t === 'complete' || t === 'completed' || t === 'done') return 'complete';
  if (t === 'in_progress' || t === 'inprogress' || t === 'progress' || t === 'ongoing') return 'in_progress';
  if (t === 'open' || t === 'new' || t === 'pending') return 'open';
  // nếu server không gửi hợp lệ -> suy ra từ done/total
  return computeStatusFromPlans(done, total);
}
const STATUS_MAP = {
  open:        { txt: 'Open',        cls: 'bg-slate-100 text-slate-700 border-slate-300' },
  in_progress: { txt: 'In progress', cls: 'bg-amber-100 text-amber-700 border-amber-300' },
  complete:    { txt: 'Complete',    cls: 'bg-green-100 text-green-700 border-green-300' }
};

function renderBackendIssues(list){
  const tb = document.getElementById('action-board-tbody') || document.querySelector('#action-board tbody');
  if (!tb) return;

  const rows  = Array.isArray(list) ? list : [];
  const stage = String(window.__stage || 'in_progress').toLowerCase(); 
  const proc  = String(window.__proc  || 'all').toLowerCase();

  // lọc theo tab + process
  const filtered = rows.filter(r=>{
    const type = String(r.type || r.display_type || '').toLowerCase();
    if (proc !== 'all' && type !== proc) return false;

    const total  = +r.plans_total || 0;
    const done   = +r.plans_done  || 0;
    const skey   = normalizeStatus(r.action_status || r.status, done, total);

    return stage === 'complete' ? (skey === 'complete') : (skey !== 'complete');
  });

  if (!filtered.length){
    tb.innerHTML = `<tr><td colspan="11" class="p-3 text-center text-gray-500">No data.</td></tr>`;
    return;
  }

  const toTs = s => {
    if (!s) return Number.MAX_SAFE_INTEGER;
    const t = Date.parse(String(s).replace(' ', 'T'));
    return Number.isNaN(t) ? Number.MAX_SAFE_INTEGER : t;
  };
  filtered.sort((a,b)=>{
    const ad = toTs(a.planned_completion_date);
    const bd = toTs(b.planned_completion_date);
    if (ad !== bd) return ad - bd;
    return String(a.device || a.device_id || '').localeCompare(String(b.device || b.device_id || ''));
  });

  const fmt = s => {
    if (!s) return '-';
    const m = String(s).match(/^(\d{4}-\d{2}-\d{2})/);
    return m ? m[1] : String(s);
  };
  const fmtDT = s => {
    if (!s) return '-';
    const d = new Date(String(s).replace(' ','T'));
    if (isNaN(d)) return String(s);
    const p = n => String(n).padStart(2,'0');
    return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())} ${p(d.getHours())}:${p(d.getMinutes())}:${p(d.getSeconds())}`;
  };
  const esc = s => String(s ?? '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

  const html = filtered.map(r=>{
    const machineType = r.type || r.display_type || '';
    const device      = r.device || r.device_id || '';
    const issueType   = r.issue_type || r.issue_category || r.priority || '-';
    const issueId     = r.issue_id || '';
    const desc        = esc(r.description_of_issue || '');
    const createdDay  = fmtDT(r.created_at || r.created || '');
    const planDate    = fmt(r.planned_completion_date || '');
    const creator     = r.created_by_display || r.created_by_name || r.created_by || r.creator || '-';
    const total       = +r.plans_total || 0;
    const done        = +r.plans_done  || 0;

    const statusKey   = normalizeStatus(r.action_status || r.status, done, total);
    const sObj        = STATUS_MAP[statusKey] || STATUS_MAP.open; // 🔒 fallback an toàn
    const statusTxt   = window.translationManager.translate(sObj.txt);
    const statusCls   = sObj.cls;

    return `
      <tr>
        <td class="p-2">${esc(window.translationManager.translate( machineType))}</td>
        <td class="p-2">${esc(device)}</td>
        <td class="p-2">${esc(window.translationManager.translate( issueType))}</td>
        <td class="p-2">${esc(issueId)}</td>
        <td class="p-2" title="${desc}">${desc}</td>
        <td class="p-2">${createdDay}</td>
        <td class="p-2 text-center">${planDate}</td>
        <td class="p-2 text-center">${esc(creator)}</td>
        <td class="p-2 text-center">
          <span class="inline-block px-2 py-0.5 text-xs border rounded ${statusCls}">${statusTxt}</span>
        </td>
        <td class="p-2 text-center"><span class="font-semibold">${done} / ${total}</span></td>
        <td class="p-2 text-center whitespace-nowrap">
          <button type="button" class="px-2 py-1 border rounded btn-view-actions"
                  data-device="${esc(device)}" aria-label="View actions" title="View actions">
            <i class="fa-solid fa-eye"></i>
          </button>
        </td>
      </tr>`;
  }).join('');

  tb.innerHTML = html;
}


  async function loadBackendIssues(){
    const tb = document.getElementById('action-board-tbody');
    if (tb) tb.innerHTML = `<tr><td colspan="11" class="p-3 text-center text-gray-500">Loading…</td></tr>`;
    try {
      const r = await authFetch(`${API}?action=list_backend_issues`);
      if (!r.ok) throw new Error('HTTP ' + r.status);
      const ct = r.headers.get('content-type') || '';
      if (!ct.includes('application/json')) throw new Error('Unexpected response (not JSON)');
      const list = await r.json();
      window.__issuesCache = Array.isArray(list) ? list : [];
      renderBackendIssues(window.__issuesCache);
    } catch (e) {
      if (tb) tb.innerHTML = `<tr><td colspan="11" class="p-3 text-center text-red-600">${esc(e.message)}</td></tr>`;
    }
  }
// Cache & stage
window.__issuesCache = [];
window.__stage = 'in_progress';

(function hookStage(){
  const btnActive = document.getElementById('tabActive');
  const btnDone   = document.getElementById('tabDone');

  function setStage(st){
    window.__stage = st;
    if (btnActive && btnDone){
      // bật/tắt style 2 nút
      const on = (el, ok)=>{ el.classList.toggle('bg-blue-600', ok); el.classList.toggle('text-white', ok); el.classList.toggle('border', !ok); };
      on(btnActive, st === 'in_progress');
      on(btnDone,   st === 'complete');
    }
    // re-render từ cache
    if (typeof renderBackendIssues === 'function') renderBackendIssues(window.__issuesCache || []);
  }

  btnActive?.addEventListener('click', ()=> setStage('in_progress'));
  btnDone  ?.addEventListener('click', ()=> setStage('complete'));
})();
 // Khởi tạo mặc định
  window.__proc  = window.__proc  || 'all';
  window.__stage = window.__stage || 'in_progress';

  // Lắng nghe click các nút process
  document.getElementById('proc-filters')?.addEventListener('click', (e) => {
    const btn = e.target.closest('button[data-proc]');
    if (!btn) return;

    // set filter hiện hành
    window.__proc = String(btn.dataset.proc || 'all').toLowerCase();

    // đổi style active cho nút
    document.querySelectorAll('#proc-filters button').forEach(b => {
      const active = b === btn;
      b.classList.toggle('bg-gray-800', active);
      b.classList.toggle('text-white',   active);
      b.classList.toggle('bg-white',    !active);
    });

    // re-render từ cache hiện có (không cần fetch lại)
    if (typeof renderBackendIssues === 'function') {
      renderBackendIssues(window.__issuesCache || []);
    }
  });
  // 👉 Xuất ra global để setTopTab() gọi được
  window.loadBackendIssues = loadBackendIssues;

  // Tự load nếu tab Action đang mở sẵn
  document.addEventListener('DOMContentLoaded', () => {
    const isActionVisible = document.getElementById('tab-action')?.classList?.contains('active');
    if (isActionVisible) loadBackendIssues();

    const td = document.getElementById("unit-cell");
    td.textContent = window.translationManager.translate("<?= $displayUnit ?>");
  });
})();
</script>


  <script>
    // ======== Menu dropdown ========
    document.getElementById('menu-toggle-btn')?.addEventListener('click', (e)=>{
      e.stopPropagation(); document.getElementById('header-dropdown')?.classList.toggle('hidden');
    });
    window.addEventListener('click', (e)=>{
      const d = document.getElementById('header-dropdown');
      const b = document.getElementById('menu-toggle-btn');
      if (d && !d.classList.contains('hidden') && !d.contains(e.target) && !b.contains(e.target)) d.classList.add('hidden');
    });

    // ======== Top tabs ========
    const tabCfg = document.getElementById('tab-config');
    const tabAct = document.getElementById('tab-action');
    const btnCfg = document.querySelector('[data-top-tab="config"]');
    const btnAct = document.querySelector('[data-top-tab="action"]');

    function setTopTab(which){
      const isAction = which === 'action';
      btnCfg.classList.toggle('active', !isAction);
      btnAct.classList.toggle('active', isAction);
      tabCfg.classList.toggle('active', !isAction);
      tabAct.classList.toggle('active', isAction);
      if (isAction) loadBackendIssues();

    }

    document.querySelectorAll('.top-tabs .tab-btn').forEach(btn=>{
      btn.addEventListener('click', ()=> setTopTab(btn.dataset.topTab));
    });

    // ======== Subtabs (Action filter) ========
    document.querySelectorAll('#tab-action .subtab').forEach(btn=>{
      btn.addEventListener('click', ()=>{
        document.querySelectorAll('#tab-action .subtab').forEach(b=>b.classList.remove('active'));
        btn.classList.add('active');
        window.__currentType = btn.dataset.type || 'all';
        loadBackendIssues();
      });
    });
    function _norm(v){ return (v ?? '').toString().trim().toLowerCase(); }
    function canVerifyAction(row, opts = {}){
      const stText = _norm(row?.status ?? row?.action_status ?? row?.state ?? row?.status_text ?? row?.status_name);
      const stNum  = String(row?.status_id ?? row?.status_code ?? row?.status_int ?? '').trim();
      const isDone = ['done','completed','complete','closed','finish','finished'].includes(stText) || ['2','3','4'].includes(stNum);
      const appr   = _norm(row?.approval_status ?? row?.approval ?? row?.verify_status ?? row?.verified_status) || 'pending';
      if (opts.force || window.__forceVerify) return true;
      return isDone && (appr === '' || appr === 'pending');
    }
    // ======== Loader & Renderer cho Action Summary ========
    function fmtDateShort(x){ if (!x) return '-'; const d = new Date(String(x).replace(' ','T')); if (isNaN(d)) return x; const p=n=>String(n).padStart(2,'0'); return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`; }

    function renderDevicesActionSummary(rows){
  const tb = document.querySelector('#devices-action-summary tbody');
  if (!tb) return;

  if (!Array.isArray(rows) || rows.length === 0){
    tb.innerHTML = `<tr><td colspan="8" style="text-align:center;color:#888">No data</td></tr>`;
    return;
  }
// --- Sort: devices with action first, then more overdue, earlier due, then device_id ---
const toTs = (s) => {
  if (!s) return Number.MAX_SAFE_INTEGER;
  const t = Date.parse(String(s).replace(' ', 'T'));
  return Number.isNaN(t) ? Number.MAX_SAFE_INTEGER : t;
};

const sorted = rows.slice().sort((a, b) => {
  const ao = +((a.open_count ?? a.open) || 0);
  const bo = +((b.open_count ?? b.open) || 0);
  const au = +((a.urgent_overdue ?? a.urgent) || 0);
  const bu = +((b.urgent_overdue ?? b.urgent) || 0);

  // Có action (open/urgent) thì lên đầu
  const aHas = (ao + au) > 0 ? 1 : 0;
  const bHas = (bo + bu) > 0 ? 1 : 0;
  if (bHas !== aHas) return bHas - aHas;

  // Nhiều urgent/overdue hơn đứng trước
  if (bu !== au) return bu - au;

  // Due sớm hơn đứng trước (null xuống cuối)
  const aDue = toTs(a.latest_due || a.due_date);
  const bDue = toTs(b.latest_due || b.due_date);
  if (aDue !== bDue) return aDue - bDue;

  // Cuối cùng theo device_id
  return String(a.device_id || '').localeCompare(String(b.device_id || ''));
});

  const html = sorted.map(r=>{
    const type   = r.display_type || r.type || '';
    const device = r.device_id || r.device || '';
    const prod   = r.product || r.family || 'Common';
    const proc   = r.process || '';
    const open   = r.open_count ?? r.open ?? 0;
    const urg    = r.urgent_overdue ?? r.urgent ?? 0;
    const latest = fmtDateShort(r.latest_due || r.due_date);
    const assign = r.assignee_name || r.assignee || '-';

    return `
    <tr>
      <td>${type}</td>
      <td>${device}</td>
      <td>${prod} | ${proc}</td>
      <td>${open}</td>
      
      <td>${latest}</td>
      
      <td class="text-center whitespace-nowrap">
        <!-- Edit (mở modal danh sách action của device) -->
        <button type="button" class="icon-btn btn-view-actions" data-device="${device}" title="View & edit actions">
          <i class="fa-solid fa-pen-to-square"></i>
        </button>
        <!-- Delete (mở modal để xóa action ở trong đó) -->
        <button type="button" class="icon-btn btn-view-actions ml-2" data-device="${device}" title="View & delete actions">
          <i class="fa-solid fa-trash-can" style="color:#dc2626"></i>
        </button>
      </td>
    </tr>
  `;

  }).join('');

  tb.innerHTML = html;
}
    async function loadActionTable(displayType='all'){
      const tb = document.querySelector('#devices-action-summary tbody');
      if (tb) tb.innerHTML = `<tr><td colspan="8">Đang tải…</td></tr>`;
      try{
        const url = `${window.API}?action=devices_action_table&display_type=${encodeURIComponent(displayType)}`;
        const r = await authFetch(url);
        if (!r.ok) throw new Error('HTTP '+r.status);
        const rows = await r.json();
        renderDevicesActionSummary(rows);
      }catch(e){ if (tb) tb.innerHTML = `<tr><td colspan=\"8\" style=\"color:#b91c1c\">Lỗi tải dữ liệu: ${e.message}</td></tr>`; }
    }



// Close modal
document.getElementById('dam-close')?.addEventListener('click', () => {
  document.getElementById('device-actions-modal')?.classList.add('hidden');
});
document.getElementById('actions-add-btn')?.addEventListener('click', () => {
  const devId = document.getElementById('device-actions-modal')?.dataset.deviceId || '';
  openActionModal(false, { device_id: devId, priority: 'medium', status: 'open' });
});
// Edit/Delete handlers
document.addEventListener('click', (e)=>{
  const editBtn = e.target.closest('.btn-action-edit');
  if (editBtn) {
    const raw = editBtn.getAttribute('data-row') || '%7B%7D';
    const data = JSON.parse(decodeURIComponent(raw));
    // Chuẩn hóa dữ liệu cho modal Add/Edit action sẵn có
    openActionModal(true, {
      id: data.id,
      device_id: data.device_id,
      title: data.title,
      priority: data.priority || 'medium',
      status: data.status || 'open',
      due_date: data.due_date,              // hàm openActionModal đã chuyển sang input type=datetime-local
      short_form: data.short_form || [],
      assigned_to_user_id: data.assigned_to_user_id ?? ''
    });
  }

  const delBtn = e.target.closest('.btn-action-del');
  if (delBtn) {
    const id = delBtn.getAttribute('data-id');
    deleteAction(id);
  }
});

    // ======== (1) Inner tabs trong Config (BỔ SUNG) ========
    (function initConfigInnerTabs(){
      const configRoot = document.getElementById('tab-config');
      if (!configRoot) return;
      const nav = configRoot.querySelector('nav[aria-label="Tabs"]');
      const panels = configRoot.querySelectorAll('#tab-content-container > .tab-content');
      if (!nav || panels.length === 0) return;
      nav.addEventListener('click', (e) => {
        const btn = e.target.closest('button[data-tab]');
        if (!btn) return;
        const target = btn.getAttribute('data-tab');
        nav.querySelectorAll('button[data-tab]').forEach(b => b.classList.toggle('active', b === btn));
        panels.forEach(p => p.classList.toggle('active', p.id === target));
      });
    })();

    // ======== (2) Event delegation cho Edit/Delete (BỔ SUNG) ========
    document.addEventListener('click', async (e) => {
      const editBtn = e.target.closest('.edit-btn');
      if (editBtn) {
        e.preventDefault();
        const raw = editBtn.getAttribute('data-device') || '{}';
        const deviceData = JSON.parse(raw.replace(/&apos;/g, "'").replace(/&quot;/g, '"'));
        const deviceForm   = document.getElementById('device-form');
        const deviceModal  = document.getElementById('device-modal');
        const modalTitle   = document.getElementById('modal-title');
        if (!deviceForm || !deviceModal || !modalTitle) { alert('Modal/Form cấu hình chưa có trong DOM.'); return; }
        deviceForm.reset();
        document.getElementById('form-action').value = 'update';
        modalTitle.textContent = `Edit Configuration: ${deviceData.device_id}`;
        for (const k in deviceData) {
        const el = document.getElementById(k);
        if (!el) continue;
        if (k === 'flex') {
          el.checked = (Number(deviceData[k]) === 1);
        } else if (k === 'manufacturing_date' && el.type === 'date') {
          el.value = String(deviceData[k] || '').slice(0,10); // 🔧 đảm bảo đúng định dạng
        } else {
          el.value = deviceData[k] ?? '';
        }
        if (document.getElementById('client')) {
            document.getElementById('client').value = deviceData.client || '';
        }
      }

        if (deviceData.display_type === 'mold'   && document.getElementById('process_mold'))    document.getElementById('process_mold').value = deviceData.process || '';
        if (deviceData.display_type === 'tuft'   && document.getElementById('process_tuft'))    document.getElementById('process_tuft').value = deviceData.process || '';
        if (deviceData.display_type === 'blister'&& document.getElementById('process_blister')) document.getElementById('process_blister').value = deviceData.process || '';
        // Ẩn/hiện theo type + đổi nhãn + đơn vị limit
      updateTypeSections(deviceData.display_type || 'mold');

      // Nếu process_mold là <select> và giá trị hiện có chưa nằm trong list → thêm tạm để set value
      (function ensureSelectValue(id, val){
        const sel = document.getElementById(id);
        if (!sel || !val) return;
        const exists = Array.from(sel.options).some(o => o.value == val);
        if (!exists){
          const opt = document.createElement('option');
          opt.value = val; opt.textContent = val;
          sel.appendChild(opt);
        }
        sel.value = val;
      })('process_mold', deviceData.process);

      // Hiển thị modal
      deviceModal.classList.remove('hidden');
      deviceModal.classList.add('active');

        return;
      }

    const delBtn = e.target.closest('.delete-btn');
    if (delBtn) {
      e.preventDefault();
      const id   = delBtn.getAttribute('data-id');
      const code = delBtn.getAttribute('data-device-id');
      if (!id) return;

      // NEW: confirm + lý do
      const res = await confirmDialog(
        `Delete device "${code}"?`, 'Delete device',
        { requireReason: true, placeholder: 'Nhập lý do xóa thiết bị...' }
      );
      if (!res.ok) return;

      const fd = new FormData();
      fd.append('action','delete');
      fd.append('id', id);
      fd.append('reason', res.reason);
      fd.append('note',   res.reason); 

      try{
        const r = await authFetch('backend.php', { method:'POST', body: fd });
        const j = await r.json();
        alert(j.message || (j.status==='success'?'Deleted':'Error'));
        if (j.status === 'success') {
          if (typeof refreshUITables === 'function') refreshUITables(); else location.reload();
        }
      }catch(err){ alert('Error: ' + (err.message || err)); }
      return;
    }

    });
    // Mặc định mở Config; khi user bấm Action sẽ nạp dữ liệu
    (function () {
        const qsTab = new URLSearchParams(location.search).get('tab');
        const hashTab = (location.hash || '').replace('#', '').trim();
        const initial = hashTab || qsTab || 'config';
        setTopTab(initial);
      })();

  // ===== Modal refs =====
  const modal = document.getElementById('device-modal');
  const modalClose = document.getElementById('modal-close');
  const btnCancel = document.getElementById('btn-cancel');
  const addBtn = document.getElementById('add-device-btn');
  const form = document.getElementById('device-form');

  // ===== Open Add Modal =====
  function openAddModal(){
    if (!modal) return;
    form?.reset();
    document.getElementById('form-action').value = 'add';
    document.getElementById('id').value = '';
    document.getElementById('modal-title').textContent = window.translationManager.translate('Add New Device');

    // default Machine Type là Mold
    const dt = document.getElementById('display_type');
    if (dt) dt.value = 'mold';
    updateTypeSections('mold');

    // mặc định frequency
    document.getElementById('frequency').value = '300';
    document.getElementById('freq_check_limit').value = '60';

    // ➕ [HISTORY_COUNT] reset về rỗng
    const hc = document.getElementById('history_count');
    if (hc) hc.value = '';

    modal.classList.remove('hidden'); modal.classList.add('active');
  }


  // ===== Close Modal =====
  function closeModal(){
    modal?.classList.remove('active'); modal?.classList.add('hidden');
  }

  addBtn?.addEventListener('click', (e)=>{ e.preventDefault(); closeHeaderMenu(); openAddModal(); });
  modalClose?.addEventListener('click', closeModal);
  btnCancel?.addEventListener('click', closeModal);
  window.addEventListener('keydown', (e)=>{ if (e.key === 'Escape') closeModal(); });
  modal?.addEventListener('click', (e)=>{ if (e.target === modal.firstElementChild) closeModal(); }); // click overlay

  // helper: close header menu if open
  function closeHeaderMenu(){
    const d = document.getElementById('header-dropdown');
    if (d && !d.classList.contains('hidden')) d.classList.add('hidden');
  }

  // ===== Show/Hide sections by type + dynamic units =====
  const dtEl = document.getElementById('display_type');
  function setLimitLabels(type){
  const lower = document.getElementById('label-lower');
  const target = document.getElementById('label-target');
  const upper = document.getElementById('label-upper');
  let unit = '(s)';
  if (type === 'tuft') unit = '(pcs)';
  if (type === 'blister') unit = '(cycles)';
  if (lower)  lower.textContent  = window.translationManager.translate(`Lower Limit ${unit}`);
  if (target) target.textContent = window.translationManager.translate(`Target ${unit}`);
  if (upper)  upper.textContent  = window.translationManager.translate(`Upper Limit ${unit}`);
  console.log(upper.textContent);
  
}

  function updateTypeSections(type){
  // ẩn tất cả
  document.querySelectorAll('.form-section').forEach(s => s.style.display = 'none');

  // process fields
  document.getElementById('process_mold_wrap').style.display     = (type === 'mold') ? 'block' : 'none';
  document.getElementById('process_tuft_wrap').style.display     = (type === 'tuft') ? 'block' : 'none';
  document.getElementById('process_blister_wrap').style.display  = (type === 'blister') ? 'block' : 'none';

  // special sections by type
  if (type === 'mold')       document.getElementById('mold-fields').style.display    = 'block';
  if (type === 'tuft')       document.getElementById('tuft-fields').style.display    = 'block';
  if (type === 'blister')    document.getElementById('blister-fields').style.display = 'block';

  // flex checkbox hiển thị cho tuft/blister
  const flex1 = document.getElementById('flex-field');
  const flex2 = document.getElementById('flex-field-bl');
  if (flex1) flex1.style.display = (type === 'tuft') ? 'block' : 'none';
  if (flex2) flex2.style.display = (type === 'blister') ? 'block' : 'none';

  // đổi nhãn ID theo ảnh
  const idLabel = document.getElementById('label-device-id');
  if (idLabel) idLabel.textContent = window.translationManager.translate((type === 'mold') ? 'Mold ID (Unique)' : 'Device ID (Unique)');

  setLimitLabels(type || '');
}

document.getElementById('display_type')?.addEventListener('change', (e)=> updateTypeSections(e.target.value));
  dtEl?.addEventListener('change', (e)=> updateTypeSections(e.target.value));


  // ===== Normalize & collect payload before submit =====
  function collectPayload(){
    const action = document.getElementById('form-action').value || 'add';
    const payload = new FormData();

    payload.append('action', action);
    const id = document.getElementById('id').value?.trim();
    if (action === 'update') payload.append('id', id);

    const device_id = document.getElementById('device_id').value?.trim();
    const display_type = document.getElementById('display_type').value;
    const product = document.getElementById('product').value?.trim();
    const client = document.getElementById('client').value?.trim();

    // 3 field model/manufacturer/manufacturing_date
    const model = document.getElementById('model').value?.trim();
    const manufacturer = document.getElementById('manufacturer').value?.trim();
    const manufacturing_date = document.getElementById('manufacturing_date').value?.trim();

    const efficiency_lower_limit = document.getElementById('efficiency_lower_limit').value;
    const lower_limit  = document.getElementById('lower_limit').value;
    const target_limit = document.getElementById('target_limit').value;
    const upper_limit  = document.getElementById('upper_limit').value;
    const frequency = document.getElementById('frequency').value;
    const freq_check_limit = document.getElementById('freq_check_limit').value;

    // ➕ [HISTORY_COUNT] đọc giá trị
    const history_count_raw = document.getElementById('history_count')?.value ?? '';
    // ép về số nguyên >= 0 (nếu nhập rỗng thì không gửi)
    const history_count = history_count_raw === '' ? '' : Math.max(0, Math.floor(Number(history_count_raw)));

    payload.append('device_id', device_id);
    payload.append('display_type', display_type);
    if (product) payload.append('product', product);
    if (client) payload.append('client', client);

    if (model) payload.append('model', model);
    if (manufacturer) payload.append('manufacturer', manufacturer);
    if (manufacturing_date) payload.append('manufacturing_date', manufacturing_date);

    if (efficiency_lower_limit !== '') payload.append('efficiency_lower_limit', efficiency_lower_limit);
    if (lower_limit !== '') payload.append('lower_limit', lower_limit);
    if (target_limit !== '') payload.append('target_limit', target_limit);
    if (upper_limit !== '') payload.append('upper_limit', upper_limit);
    if (frequency !== '') payload.append('frequency', frequency);
    if (freq_check_limit !== '') payload.append('freq_check_limit', freq_check_limit);

    // ➕ [HISTORY_COUNT] đưa vào FormData
    if (history_count !== '' && Number.isFinite(history_count)) {
      payload.append('history_count', String(history_count));
    }

    // process & field theo từng loại
    if (display_type === 'mold'){
      const pr = document.getElementById('process_mold').value?.trim();
      if (pr) payload.append('process', pr);
      const cavities = document.getElementById('cavities').value;
      const mold_type = document.getElementById('mold_type').value?.trim();
      if (cavities !== '') payload.append('cavities', cavities);
      if (mold_type) payload.append('mold_type', mold_type);
    } else if (display_type === 'tuft'){
      const pr = document.getElementById('process_tuft').value?.trim();
      if (pr) payload.append('process', pr);
      const hole = document.getElementById('hole_per_brush').value;
      if (hole !== '') payload.append('hole_per_brush', hole);
      if (document.getElementById('flex')?.checked) payload.append('flex', '1');
    } else if (display_type === 'blister'){
      const pr = document.getElementById('process_blister').value?.trim();
      if (pr) payload.append('process', pr);
      const bpc = document.getElementById('brushes_per_cycle').value;
      if (bpc !== '') payload.append('brushes_per_cycle', bpc);
      if (document.getElementById('flex_bl')?.checked) payload.append('flex', '1');
    }
    return payload;
  }
// ===== Optimistic row patch (with rollback) =====
(function(){
  const F = s => s ? String(s).slice(0,10) : '-';
  const LIM = d => [d.lower_limit||'-', d.target_limit||'-', d.upper_limit||'-'].join(' | ');
  const CAP = (t,d) => { const tg=+d.target_limit||0; if(!tg) return '-';
    if(t==='mold'){ const c=+d.cavities||0; return c?Math.floor((3600*c)/tg):'-'; }
    if(t==='tuft') return Math.floor(60*tg);
    if(t==='blister'){ const b=+d.brushes_per_cycle||0; return b?Math.floor(tg*b*60):'-'; }
    return '-';
  };

  // Cấu hình: cột -> giá trị
const MAP = {
      mold: {
        1: 'product',
        2: 'client', // [MỚI]
        3: 'model',
        4: 'manufacturer',
        5: d => F(d.manufacturing_date),
        6: 'cavities',
        7: d => CAP('mold', d),
        // Các cột sau bị đẩy lùi index +1
        11: 'process',
        12: 'mold_type',
        13: d => d.efficiency_lower_limit ? parseInt(d.efficiency_lower_limit, 10) : '-',
        14: d => LIM(d),
        15: 'frequency',
        16: 'freq_check_limit'
      },
      tuft: {
        2: 'product',
        3: 'client', // [MỚI]
        4: 'model',
        5: 'manufacturer',
        6: d => F(d.manufacturing_date),
        7: d => CAP('tuft', d),
        8: 'hole_per_brush',
        // Các cột sau bị đẩy lùi index +1
        12: 'process',
        13: d => d.efficiency_lower_limit ? parseInt(d.efficiency_lower_limit, 10) : '-',
        14: d => LIM(d),
        15: 'frequency',
        16: 'freq_check_limit',
        1: (d, fd) => `<input type="checkbox" class="h-4 w-4" disabled ${fd.has('flex')?'checked':''}>`
      },
      blister: {
        2: 'product',
        3: 'client', // [MỚI]
        4: 'model',
        5: 'manufacturer',
        6: d => F(d.manufacturing_date),
        7: d => CAP('blister', d),
        8: 'brushes_per_cycle',
        // Các cột sau bị đẩy lùi index +1
        12: d => d.efficiency_lower_limit ? parseInt(d.efficiency_lower_limit, 10) : '-',
        13: d => LIM(d),
        14: 'frequency',
        15: 'freq_check_limit',
        1: (d, fd) => `<input type="checkbox" class="h-4 w-4" disabled ${fd.has('flex')?'checked':''}>`
      },
      injection: {
        1: 'product',
        2: 'client', // [MỚI]
        3: 'model',
        4: 'manufacturer',
        5: d => F(d.manufacturing_date),
        // Các cột sau bị đẩy lùi index +1
        8: d => d.history_count ?? '-',
        9: d => d.efficiency_lower_limit ? parseInt(d.efficiency_lower_limit, 10) : '-',
        10: d => LIM(d),
        11: 'frequency',
        12: 'freq_check_limit'
      },
      'end-rounding': {
        1: 'product',
        2: 'client', // [MỚI]
        3: 'model',
        4: 'manufacturer',
        5: d => F(d.manufacturing_date),
        // Các cột sau bị đẩy lùi index +1
        8: d => d.history_count ?? '-',
        9: d => d.efficiency_lower_limit ? parseInt(d.efficiency_lower_limit, 10) : '-',
        10: d => LIM(d),
        11: 'frequency',
        12: 'freq_check_limit'
      }
    };

  function findRowByTypeAndId(type, id){
    const tb = document.querySelector(`#${type} table tbody`);
    if (!tb) return null;
    return tb.querySelector(`tr[data-device-id="${CSS.escape(id)}"]`)
        || [...tb.querySelectorAll('tr')].find(r => r.firstElementChild?.textContent.trim() === id) || null;
  }

  // Trả về {undo, commit} để dùng trong submit handler
  window.optimisticPatchDeviceRow = function(fd){
    const d = Object.fromEntries(fd.entries());
    const type = (d.display_type||'').trim();
    const id   = (d.device_id||'').trim();
    if (!type || !id) return { undo:()=>{}, commit:()=>{} };

    // Nếu user đổi "type" so với bảng hiện tại, để đơn giản: bỏ qua optimistic (tránh chèn/xóa hàng)
    const tr = findRowByTypeAndId(type, id);
    const map = MAP[type];
    if (!tr || !map) return { undo:()=>{}, commit:()=>{} };

    // Lưu snapshot cũ
    const snapshot = {};
    Object.keys(map).forEach(i=>{
      const cell = tr.children[+i];
      if (!cell) return;
      snapshot[i] = { html: cell.innerHTML, text: cell.textContent };
    });

    // Vá ngay UI
    Object.entries(map).forEach(([i, rule])=>{
      const cell = tr.children[+i]; if (!cell) return;
      const val = (typeof rule === 'function') ? rule(d, fd) : (d[rule] ?? '-');
      // cột checkbox (tuft/blister col 1) dùng innerHTML, còn lại text
      if (i==='1' && (type==='tuft'||type==='blister')) cell.innerHTML = val;
      else cell.textContent = String(val);
    });

    // Đánh dấu đang sync (mờ nhẹ)
    tr.classList.add('opacity-60');
    tr.dataset.optimistic = '1';

    return {
      undo: () => {
        if (!tr) return;
        Object.entries(snapshot).forEach(([i, snap])=>{
          const cell = tr.children[+i]; if (!cell) return;
          cell.innerHTML = snap.html;  // đủ để khôi phục (giữ nguyên ô checkbox nếu có)
        });
        tr.classList.remove('opacity-60');
        delete tr.dataset.optimistic;
      },
      commit: () => {
        if (!tr) return;
        tr.classList.remove('opacity-60');
        delete tr.dataset.optimistic;
        // nháy nhẹ báo thành công
        tr.classList.add('ring-2','ring-emerald-400');
        setTimeout(()=> tr.classList.remove('ring-2','ring-emerald-400'), 350);
      }
    };
  };
})();

  // ===== Submit ADD/UPDATE =====
form?.addEventListener('submit', async (e) => {
  e.preventDefault();

  // Lấy toàn bộ input trong #device-form (đã có name="reason")
  const fd = new FormData(form);

  // (Nếu bạn dùng các select process khác name)
  const type = fd.get('display_type');
  if (type === 'mold')   fd.set('process', document.getElementById('process_mold')?.value || '');
  if (type === 'tuft')   fd.set('process', document.getElementById('process_tuft')?.value || '');
  if (type === 'blister')fd.set('process', document.getElementById('process_blister')?.value || '');

  // BẮT BUỘC reason có giá trị trước khi gửi
  const reason = (fd.get('reason') || '').toString().trim();
  if (!reason) {
    showToast('Vui lòng nhập lý do (reason).', 'error');
    return;
  }

  try {
    const r = await authFetch('backend.php', { method: 'POST', body: fd });
    const j = await r.json();
    if (j.status === 'success') {
      showToast(j.message || 'Saved!', 'success');
      closeModal();
      if (typeof refreshUITables === 'function') refreshUITables();
    } else {
      showToast(j.message || 'Error', 'error');
    }
  } catch (err) {
    showToast(err.message || 'Network error', 'error');
  }
});



  // ===== expose refresh (optional) =====
  window.refreshUITables = function(){
    // reload current inner tab to reflect changes
    // location.reload();
  }


let __usersLoaded = false;
async function loadUsers(){
  if (__usersLoaded) return;
  const r = await authFetch(`${window.API}?action=list_users`, {credentials:'include'});
  const users = await r.json();

  // hỗ trợ cả id cũ và mới, và CHỈ set nếu có element
  const sel = document.getElementById('action_assigned_to_user_id')
            || document.getElementById('action_assignee');
  if (!sel) { __usersLoaded = true; return; } // không có dropdown → thôi

  sel.innerHTML = `<option value="">--</option>` + users.map(u =>
    `<option value="${u.id}">${u.fullname || u.username || u.id}</option>`
  ).join('');

  __usersLoaded = true;
}


async function openActionModal(isEdit = false, data = {}) {
  const m = document.getElementById('action-modal') || document.getElementById('actions-modal');
  if (!m) return;

  // Helpers
  const q  = (id) => document.getElementById(id);
  const sv = (id, val) => { if (q(id)) q(id).value = val ?? ''; };
  const toInputDT = (dbDate) => dbDate ? dbDate.replace(' ', 'T').slice(0, 16) : '';
  const pickSF = (sf, labels) => {
    if (!Array.isArray(sf)) return '';
    const lows = labels.map(s => String(s).toLowerCase());
    const hit  = sf.find(x => lows.includes(String(x.label || '').toLowerCase()));
    return hit ? (hit.value || '') : '';
  };

  // đảm bảo dropdown users có trước khi set owner
  if (typeof loadUsers === 'function') {
    try { await loadUsers(); } catch {}
  }

  // Reset các control cơ bản
  sv('action_id', isEdit ? (data.id ?? '') : '');
  if (q('action_id_display')) {
    sv('action_id_display', isEdit ? (data.id ?? '') : '(auto)');
  }

  // Device
  const devId = (data.device_id || document.getElementById('device-actions-modal')?.dataset?.deviceId || '').trim();
  sv('action_device_id', devId);

  // Title/Priority (nếu form vẫn còn dùng)
  if (q('action_title'))    sv('action_title',    data.title || '');
  if (q('action_priority')) sv('action_priority', data.priority || 'medium');

  // Due date
  sv('action_due', toInputDT(data.due_date || ''));

  // Status: hiển thị khi edit, ẩn khi create (theo hành vi cũ)
  const stsRow = q('status-row');
  const stsEl  = q('action_status');
  if (stsRow && stsEl) {
    if (isEdit) {
      stsRow.classList.remove('hidden');
      const val = String(data.status || 'open').toLowerCase();
      sv('action_status', ['open','in_progress','done','cancelled'].includes(val) ? val : 'open');
    } else {
      stsRow.classList.add('hidden');
      sv('action_status', 'open');
    }
  }

  // Owner: ưu tiên field mới 'action_assigned_to_user_id', fallback field cũ 'action_assignee'
  const ownerId = (data.assigned_to_user_id ?? '').toString();
  if (q('action_assigned_to_user_id')) {
    sv('action_assigned_to_user_id', ownerId);
  } else if (q('action_assignee')) {
    sv('action_assignee', ownerId);
  }

  // short_form → Issue/Plan
  let sf = [];
  try { sf = Array.isArray(data.short_form) ? data.short_form : JSON.parse(data.short_form || '[]'); } catch {}
  const issue = getIssueText(row);
  const plan  = pickSF(sf, ['Action plan', 'Plan', 'Immediate Action', 'Kế hoạch']);
  if (q('sf_issue')) sv('sf_issue', issue);
  if (q('sf_plan'))  sv('sf_plan',  plan);

  // Created (readonly) & Verified (readonly)
  if (q('action_created_at'))     sv('action_created_at', data.created_at || '');
  if (q('action_verified_people')) sv('action_verified_people', data.verified_people || data.verified_by || '');

  // Nếu bạn còn UI cũ #sf-list, dọn nhẹ cho khỏi trùng:
  const sfList = document.getElementById('sf-list');
  if (sfList) sfList.innerHTML = ''; // chuyển sang 2 field cố định

  m.classList.remove('hidden');
}


function closeActionModal(){ document.getElementById('action-modal').classList.add('hidden'); }
document.getElementById('action-cancel')?.addEventListener('click', closeActionModal);
document.getElementById('btn-new-action')?.addEventListener('click', () => openActionModal(false));


// short-form rows
function addSfRow(label='', value='') {
  const row = document.createElement('div');
  row.className = 'grid grid-cols-2 gap-2';
  row.innerHTML = `
    <input class="sf-label border rounded px-2 py-1" placeholder="Label" value="${label}">
    <div class="flex gap-2">
      <input class="sf-value border rounded px-2 py-1 flex-1" placeholder="Value" value="${value}">
      <button type="button" class="px-2 border rounded btn-del">✕</button>
    </div>`;
  row.querySelector('.btn-del').onclick = ()=> row.remove();
  document.getElementById('sf-list').appendChild(row);
}
const btnAddSf = document.getElementById('btn-add-sf');
if (btnAddSf) btnAddSf.addEventListener('click', ()=> addSfRow('', ''));

// submit (create / update)
document.getElementById('action-form')?.addEventListener('submit', async (e) => {
  e.preventDefault();

  // Helpers
  const q  = (id) => document.getElementById(id);
  const gv = (id) => q(id)?.value?.trim() ?? '';
  const exists = (id) => !!q(id);
  const fromInputDT = (inputDT) => inputDT ? (inputDT.includes('T') ? inputDT.replace('T',' ') + ':00' : inputDT) : '';

  const id       = gv('action_id');
  const isCreate = !id;
  const body     = new FormData();

  // hành động
  body.append('action', isCreate ? 'action_create' : 'action_update');
  if (!isCreate) body.append('action_id', id);

  // device
  body.append('device_id', gv('action_device_id'));

// status: open/in_progress/done/completed/cancelled
let status = (gv('action_status') || 'open').toLowerCase();
if (status === 'complete') status = 'completed';
const allowed = ['open','in_progress','done','completed','cancelled'];
if (!allowed.includes(status)) status = 'open';
body.append('status', status);


  // owner
  // Ưu tiên field mới 'action_assigned_to_user_id', fallback 'action_assignee' (cũ)
  const ownerId = gv('action_assigned_to_user_id') || gv('action_assignee');
  if (ownerId !== '') body.append('assigned_to_user_id', ownerId);

  // est. date (due_date)
  const due = gv('action_due');
  if (due) body.append('due_date', fromInputDT(due));

  // title / priority (tuỳ form bạn có dùng hay không)
  if (exists('action_title'))    body.append('title',    gv('action_title'));
  if (exists('action_priority')) body.append('priority', gv('action_priority'));

  // short_form: dùng nhãn mới "Description of issue" & "Action plan"
  // Nếu 2 field mới không có/để trống thì fallback đọc từ #sf-list (cũ) để tương thích
  const issue = gv('sf_issue');
  const plan  = gv('sf_plan');
  const sfArr = [];
  if (issue) sfArr.push({ label: 'Description of issue', value: issue });
  if (plan)  sfArr.push({ label: 'Action plan',          value: plan  });

  if (sfArr.length === 0 && document.querySelector('#sf-list .grid')) {
    const legacy = [...document.querySelectorAll('#sf-list .grid')].map(row => ({
      label: row.querySelector('.sf-label')?.value?.trim() ?? '',
      value: row.querySelector('.sf-value')?.value?.trim() ?? ''
    })).filter(x => x.label || x.value);
    legacy.forEach(x => sfArr.push(x));
  }
  body.append('short_form', JSON.stringify(sfArr));

  try {
    const r = await authFetch('backend.php', { method: 'POST', body, credentials: 'include' });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    const j = await r.json();

    if (j.status === 'success' || j.result === 'success' || j.success === true) {
      showToast(isCreate ? 'Created action': 'Updated action', 'success');

      // Phát sự kiện + đồng bộ UI
      document.dispatchEvent(new CustomEvent('action:changed', {
        detail: { device_id: gv('action_device_id') }
      }));

      closeActionModal?.();
      // Tùy hệ thống của bạn:
      loadActionTable?.(window.__currentType || 'all');
      if (window.__currentDeviceInModal) {
        refreshDeviceActions?.(window.__currentDeviceInModal);
      }
    } else {
      showToast(j.message || 'Save failed', 'error');
    }
  } catch (err) {
    console.error(err);
    showToast(err.message || 'Connection error', 'error');
  }
});

// xóa action
async function deleteAction(id){
  const res = await confirmDialog(
    'Delete this action?', 'Delete',
    { requireReason: true, placeholder: '' }
  );
  if (!res.ok) return;

  const fd = new FormData();
  fd.append('action','action_delete');
  fd.append('action_id', id);
  fd.append('reason', res.reason); // 👈 gửi reason
  fd.append('note',   res.reason); // 👈 tương thích

  try {
    const r = await authFetch('backend.php', { method:'POST', body: fd, credentials:'include' });
    if (!r.ok) throw new Error(`HTTP ${r.status}`);
    const j = await r.json();

    if (j.status === 'success') {
      showToast('Successfully deleted', 'success');
      loadBackendIssues();
      if (window.__currentDeviceInModal) refreshDeviceActions(window.__currentDeviceInModal);
      document.dispatchEvent(new CustomEvent('action:changed', { detail: { device_id: window.__currentDeviceInModal || '' } }));
    } else {
      showToast(j.message || 'Delete failed', 'error');
    }
  } catch(err){
    showToast(err.message || 'Delete error', 'error');
  }
}



// Thiết bị đang xem trong modal (để biết cần refresh cái nào)
window.__currentDeviceInModal = null;

function closeDeviceActionsModal() {
  document.getElementById('device-actions-modal')?.classList.add('hidden');
  window.__currentDeviceInModal = null;
}

let actionsAbortCtrl = null;
let actionsReqToken = 0;

window.__forceVerify = window.__forceVerify ?? false;
window.API_BASE = '../api.php';
                 
async function refreshDeviceActions(deviceId){
  const tb = document.querySelector('#device-actions-table tbody, #device-actions-tbody');
  const COLSPAN = document.querySelectorAll('#device-actions-table thead th').length || 8;
  if (!tb) return;

  tb.innerHTML = `<tr><td colspan="${COLSPAN}" class="p-3 text-gray-500">Loading…</td></tr>`;
  window.__currentDeviceInModal = deviceId;

  // helpers
  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
                                  .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  const toDateTime = s => s ? (s.includes('T') ? s.replace('T',' ') : s).slice(0,19) : '-';
    const fmtDate = s => {
    if (!s) return '-';
    const d = new Date(String(s).replace(' ', 'T'));
    if (isNaN(d)) return String(s).slice(0, 10);
    const p = n => String(n).padStart(2, '0');
    return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`;
  };

  const getIssueText = (row) => {
    if (row.desc && String(row.desc).trim() !== '') return row.desc;
    if (row.title && String(row.title).trim() !== '') return row.title;
    let sf = row.short_form;
    if (!Array.isArray(sf)) { try{ sf = JSON.parse(sf || '[]'); } catch { sf = []; } }
    const hit = sf.find(x => String(x.label||'').toLowerCase().includes('issue'));
    return (hit?.value || '-');
  };
  const plansSummary = (row) => {
    const done  = Number(row.plans_done  ?? 0);
    const total = Number(row.plans_total ?? 0);
    const dots  = Array.from({length: Math.max(total, 3)})
                       .map((_,i)=>`<span class="${i<done?'on':''}"></span>`).join('');
    const state = total>0 && done===total ? 'pill-green' : (done>0 ? 'pill-amber' : 'pill-muted');
    const txt   = total ? `${done}/${total} plan${total>1?'s':''}` : 'no plan';
    // const txt2 = total > 1 ? total + window.translationManager.translate('plans')
    return `<span class="pill ${state}">${txt}</span> <span class="progress-dots">${dots}</span>`;
  };

  try {
    let approval = (document.getElementById('dam-approval')?.value || '').trim();
    if (approval === 'all') approval = '';
    const url = `${window.API_BASE}?action=list_device_actions_v2&device_id=${encodeURIComponent(deviceId)}`
              + (approval ? `&approval=${encodeURIComponent(approval)}` : '')
              + `&_=${Date.now()}`;

    const r = await authFetch(url, { headers:{ 'Accept':'application/json' } });
    if (!r.ok) throw new Error('HTTP '+r.status);

    const ct = (r.headers.get('content-type') || '').toLowerCase();
    if (!ct.includes('application/json')) throw new Error('Unexpected response (not JSON)');

    const j = await r.json();

    // Chuẩn hoá: hỗ trợ mọi biến thể (items/data/rows/list/array)
    if (j && j.status && j.status !== 'success') {
      throw new Error(j.message || 'API error');
    }
    const rows = Array.isArray(j)               ? j
              : Array.isArray(j.items)         ? j.items
              : Array.isArray(j.data?.items)   ? j.data.items
              : Array.isArray(j.data)          ? j.data
              : Array.isArray(j.rows)          ? j.rows
              : Array.isArray(j.list)          ? j.list
              : [];

    if (rows.length === 0) {
      tb.innerHTML = `<tr><td colspan="${COLSPAN}" class="p-3 text-center text-gray-500">No actions.</td></tr>`;
      document.dispatchEvent(new CustomEvent('device_actions:rendered', { detail: { device_id: deviceId } }));
      return;
    }

    // sort: open -> in-progress -> complete, rồi đến due-date, created, id
    const progWeight = r => {
      const t = Number(r.plans_total||0), d = Number(r.plans_done||0);
      if (t>0 && d===t) return 2;              // complete
      if (t>0 && d>0 && d<t) return 1;         // in-progress
      return 0;                                // open
    };
    rows.sort((a,b)=>{
      const dw = progWeight(a) - progWeight(b); if (dw) return dw;
      const da = a.due_date ? Date.parse(a.due_date) : Number.POSITIVE_INFINITY;
      const db = b.due_date ? Date.parse(b.due_date) : Number.POSITIVE_INFINITY;
      if (da!==db) return da - db;
      const ca = a.created_at ? Date.parse(a.created_at) : 0;
      const cb = b.created_at ? Date.parse(b.created_at) : 0;
      if (ca!==cb) return cb - ca;
      return (b.id||0) - (a.id||0);
    });

    // render 2 hàng/issue: hàng chính + hàng details (plans)
    tb.innerHTML = rows.map(row => {
      const issue = esc(getIssueText(row));
      const creator = esc(row.created_by_display || row.created_by_name || row.created_by || '-');
      const created = esc(toDateTime(row.created_at));
      const donePlans  = Number(row.plans_done||0);
      const totalPlans = Number(row.plans_total||0);
      const complete   = totalPlans>0 && donePlans===totalPlans;
      const inProgress = totalPlans>0 && donePlans>0 && donePlans<totalPlans;

      const statusBadge = complete
        ? `<span class="pill pill-green" data-translate="Complete" >complete</span>`
        : inProgress
          ? `<span class="pill pill-amber" data-translate="In progress">in progress</span>`
          : `<span class="pill pill-muted" data-translate="Open">open</span>`;

      const appr  = String(row.approval_status||'pending').toLowerCase();
      const apprCls = appr==='approved' ? 'pill-green' : (appr==='rejected' ? 'pill-amber' : 'pill-muted');
      const apprBadge = `<span class="pill ${apprCls}">${window.translationManager.translate(appr)}</span>`;

      const issueId = esc(row.action_code || ('ISS' + String(row.id||0).padStart(5,'0')));
      const expander = `
        <button class="btn-expander js-expand" data-aid="${row.id}" aria-expanded="false">
          <i class="fa-solid fa-chevron-right"></i>
          <span class="underline decoration-dotted">${issueId}</span>
        </button>`;

      const plannedDate = esc(fmtDate(row.planned_completion_date || row.due_date));

      const main = `
        <tr class="border-b hover:bg-gray-50">
          <td class="p-2 col-no">${expander}</td>
          <td class="p-2 cell-issue" title="${issue}">${issue}</td>
          <td class="p-2 cell-plan">${plansSummary(row)}</td>
          <td class="p-2 col-date text-center">${plannedDate}</td>
          <td class="p-2 col-owner text-center">${creator}</td>
          <td class="p-2 col-date text-center">${created}</td>
          <td class="p-2 text-center" data-col="status">${statusBadge}</td>
          <td class="p-2 text-center" data-col="verification">
            ${
              (()=> {
              const canVerify = complete && (appr === '' || appr === 'pending');
              if (canVerify) {
                return `
                  <div class="inline-flex items-center gap-2 justify-center">
                    ${apprBadge}
                    <button type="button" class="icon-btn btn-approve" data-perm="approve.action.ems" data-id="${row.id}" title="Approve">
                      <i class="fa-solid fa-check icon-yes"></i>
                    </button>
                    <button type="button" class="icon-btn btn-reject" data-perm="reject.action.ems" data-id="${row.id}" title="Reject">
                      <i class="fa-solid fa-xmark icon-no"></i>
                    </button>
                  </div>`;
              }
              return apprBadge || '-';
              })()
            }
          </td>
          <td class="p-2 col-manage">
            <div class="inline-flex gap-2">
              <button type="button" class="icon-btn btn-action-del" data-perm = 'delete.action.ems' data-id="${row.id}" title="Delete">
                <i class="fa-solid fa-trash-can icon-del"></i>
              </button>
            </div>
          </td>
        </tr>`;

      const details = `
        <tr class="tr-details" data-details="${row.id}">
          <td colspan="${COLSPAN}" class="p-2">
            <div class="details-box p-3">
              <div class="text-xs text-gray-500 mb-2 text-center">Action Plans</div>
              <div class="min-h-[32px]" id="plans-${row.id}">
                <span class="text-gray-400">Click to load…</span>
              </div>
            </div>
          </td>
        </tr>`;

      return main + details;
    }).join('');

    // 🔔 QUAN TRỌNG: phát event để block auto-expand “nghe” được
    document.dispatchEvent(new CustomEvent('device_actions:rendered', { detail: { device_id: deviceId } }));

  } catch (err) {
    tb.innerHTML = `<tr><td colspan="${COLSPAN}" class="p-3 text-red-600">Error: ${esc(String(err.message||err))}</td></tr>`;
    document.dispatchEvent(new CustomEvent('device_actions:rendered', { detail: { device_id: deviceId } }));
  }
}


/* CSS guard – phòng trường hợp pointer-events bị chặn */
(function ensureClick(){
  const css = `
#device-actions-modal .icon-btn{ pointer-events:auto; }
#device-actions-modal .icon-btn i{ pointer-events:none; }`;
  const tag = document.createElement('style');
  tag.textContent = css;
  document.head.appendChild(tag);
})();


// đăng ký filter chỉ 1 lần, ngoài hàm (tránh add nhiều listener)
document.getElementById('dam-approval')?.addEventListener('change', () => {
  const dev = window.__currentDeviceInModal;
  if (dev) refreshDeviceActions(dev);
});

let __tblRO = null;

function openDeviceActionsModal(deviceId){
  const modal = document.getElementById('device-actions-modal');
  modal.classList.remove('hidden');
  
  // Khóa theo bề rộng tại thời điểm MỞ (baseline)
  requestAnimationFrame(() => lockActionsTableWidth({ init: true }));

  window.__expandAfterRefresh = 'all';
  window.__currentDeviceInModal = deviceId;
  modal.dataset.deviceId = deviceId;
  document.getElementById('dam-device-id').textContent = deviceId;

  refreshDeviceActions(deviceId);
}

// Sau khi render xong (kể cả khi bạn expand), gọi lại
document.addEventListener('device_actions:rendered', () => {
  lockActionsTableWidth();      // sẽ KHÔNG co vì đã có baseline
});

// Khi đóng modal: mở khóa + reset baseline
document.addEventListener('click', (e)=>{
  if (e.target.id === 'dam-close' || e.target.closest('#dam-close')) {
    unlockActionsTableWidth();
    document.getElementById('device-actions-modal')?.classList.add('hidden');
  }
});

// Nếu cửa sổ thay đổi kích thước khi modal đang mở → cho phép NỞ ra (không co)
window.addEventListener('resize', () => {
  const m = document.getElementById('device-actions-modal');
  if (!m || m.classList.contains('hidden')) return;
  lockActionsTableWidth(); // Math.max(base, measured)
});


// sau khi bạn render xong bảng
document.addEventListener('device_actions:rendered', ()=> lockActionsTableWidth(true));

// khi đóng modal: mở khóa + ngừng theo dõi
document.addEventListener('click', (e)=>{
  if (e.target.id === 'dam-close' || e.target.closest('#dam-close')) {
    __tblRO?.disconnect(); __tblRO = null;
    lockActionsTableWidth(false);
    document.getElementById('device-actions-modal')?.classList.add('hidden');
  }
});

// nếu modal đang mở và user resize cửa sổ
window.addEventListener('resize', ()=>{
  const m = document.getElementById('device-actions-modal');
  if (!m || m.classList.contains('hidden')) return;
  lockActionsTableWidth(true);
});


// đã có sẵn:
document.addEventListener('device_actions:rendered', ()=> lockActionsTableWidth(true));

// khi đóng:
document.addEventListener('click', (e)=>{
  if (e.target.id === 'dam-close' || e.target.closest('#dam-close')){
    lockActionsTableWidth(false);
    document.getElementById('device-actions-modal')?.classList.add('hidden');
  }
});




// Mở modal khi bấm icon ở cột Action
document.addEventListener('click', (e)=>{
  const btn = e.target.closest('.btn-view-actions');
  if (!btn) return;
  const deviceId = btn.getAttribute('data-device')?.trim();
  if (deviceId) openDeviceActionsModal(deviceId);
});

// Đóng modal khi bấm Close hoặc click overlay tối
document.addEventListener('click', (e)=>{
  if (e.target.id === 'dam-close' || e.target.closest('#dam-close')) {
    closeDeviceActionsModal();
    return;
  }
  const modal = document.getElementById('device-actions-modal');
  if (!modal || modal.classList.contains('hidden')) return;

  const overlay = modal.querySelector('.absolute.inset-0'); // div overlay
  const panel   = modal.querySelector('.relative.bg-white'); // khung trắng
  // nếu click vào overlay (không phải vào trong panel) thì đóng
  if (overlay && e.target === overlay) closeDeviceActionsModal();
});
// Bất kỳ khi nào có thay đổi action, đồng bộ lại bảng tổng và modal
document.addEventListener('action:changed', (e)=>{
  loadBackendIssues();

  if (window.__currentDeviceInModal) {
    // Nếu sửa đúng thiết bị đang mở, refresh list trong modal
    const changedId = e.detail?.device_id;
    if (!changedId || changedId === window.__currentDeviceInModal) {
      refreshDeviceActions(window.__currentDeviceInModal);
    }
  }
});
(function(){
  const rootId = 'toast-root';
  function ensureRoot(){
    let r = document.getElementById(rootId);
    if (!r){
      r = document.createElement('div');
      r.id = rootId; r.setAttribute('aria-live','polite'); r.setAttribute('aria-atomic','true');
      document.body.appendChild(r);
    }
    return r;
  }

  // window.showToast(message, type='success'|'error'|'info', ms=2500)
  // window.showToast(message, type='success'|'error'|'info', ms=2500)
window.showToast = function(msg, type='success', timeout=2500){
  const root = (function ensureRoot(){
    let r = document.getElementById('toast-root');
    if (!r){
      r = document.createElement('div');
      r.id = 'toast-root';
      r.setAttribute('aria-live','polite');
      r.setAttribute('aria-atomic','true');
      document.body.appendChild(r);
    }
    return r;
  })();

  const el = document.createElement('div');
  el.className = `toast toast-${type}`;
  el.innerHTML = `
    <span>${msg}</span>
    <button class="close" aria-label="Close">×</button>
  `;
  root.appendChild(el);

  // Hiển thị: dùng .active (phù hợp CSS hiện có). Đồng thời thêm .show để tương thích nếu sau này dùng.
  requestAnimationFrame(()=> { el.classList.add('active'); el.classList.add('show'); });

  const remove = ()=> {
    el.classList.remove('active'); el.classList.remove('show');
    setTimeout(()=> el.remove(), 250);
  };
  el.querySelector('.close').onclick = remove;

  const t = setTimeout(remove, timeout);
  el.addEventListener('mouseenter', ()=> clearTimeout(t), {once:true});
};

})();
document.addEventListener('click', (e) => {
  const addBtn = e.target.closest('#actions-add-btn');
  if (!addBtn) return;

  // Lấy deviceId đang mở từ dataset (đã set ở bước 1) hoặc fallback biến global
  const devId = document.getElementById('device-actions-modal')?.dataset.deviceId
                || window.__currentDeviceInModal || '';

  // Mở popup Edit Action ở chế độ tạo mới
  openActionModal(false, {
    device_id: devId,
    priority: 'medium',
    status: 'open'
  });
});

  </script>
<!-- [INSERT] Modal: Device Actions List -->
    <div id="device-actions-modal" class="hidden fixed inset-0 z-40 flex items-center justify-center">
      <div class="absolute inset-0 bg-black/40"></div>
      <div class="relative bg-white rounded-lg shadow-lg w-[95%] max-w-[100%] p-4">
        <div class="flex items-center justify-between mb-3">
            <script>
              document.addEventListener("DOMContentLoaded", () => {
                let issueLabelStatic = window.translationManager.translate('Issue') + " - <span id='dam-device-id'></span>";
                document.querySelector("#issue-label-static").innerHTML = issueLabelStatic;
              });
          </script>
          <h3 class="text-lg font-semibold" id="issue-label-static"><span id="dam-device-id"></span></h3>
          <div class="flex items-center gap-2 mb-2">
        </div>

          <button id="dam-close" type="button" class="px-3 py-1 border rounded">Close</button>

        </div>
        <div class="table-container-scroll" style="max-height:60vh;overflow-y:scroll;overflow-x:auto;scrollbar-gutter:stable both-edges">

         <table id="device-actions-table"
          class="w-full min-w-[1100px] text-sm bg-white rounded-md shadow table-actions"
          style="table-layout:fixed; min-width:1100px; width:100%;">




  <!-- NEW: colgroup kiểm soát rộng cột -->
    <colgroup>
      <col style="width: 12rem;">   <!-- Issue ID -->
      <col style="width: auto;">    <!-- Description -->
      <col style="width: 14rem;">   <!-- Action plan -->
      <col style="width: 12rem;">   <!-- Planned completion -->
      <col style="width: 10rem;">   <!-- Created by -->
      <col style="width: 12rem;">   <!-- Created -->
      <col style="width: 11rem;">   <!-- Action status -->
      <col style="width: 13rem;">   <!-- Approval -->
      <col style="width: 8rem;">    <!-- Manage -->
    </colgroup>

    <thead class="bg-gray-100 text-xs uppercase">
      <tr>
        <th class="p-2 text-left col-no">Issue ID</th>
        <th class="p-2 text-left">Description Of Issue</th>
        <th class="p-2 text-center">Action plan</th>
        <th class="p-2 text-left col-date">Planned completion date</th>
        <th class="p-2 text-center col-owner">Created by</th>
        <th class="p-2 text-center col-date">Created</th>
        <th class="p-2 text-center col-owner">Action Status</th>
        <th class="p-2 text-center">Approval</th>
        <th class="p-2 text-center col-manage">Manage</th>
      </tr>
    </thead>

  <tbody id="device-actions-tbody"></tbody>
    </table>
        </div>
      </div>
    </div>
    <div id="toast-root" aria-live="polite" aria-atomic="true"></div>
    <script>
      window.API_BASE = window.API_BASE || window.API || window.API_BACKEND || './api.php';
function toast(msg, ok=true){ console.log(ok?'✅':'❌', msg); }

// ===== Load users cho dropdown assignee =====
async function loadAssignees() {
  const res = await authFetch(`${window.API_BACKEND}?action=list_users`);
  const users = await res.json();
  const sel = document.getElementById('action_assignee');
  if (!sel) return;
  sel.innerHTML = `<option value="">-- Unassigned --</option>` + users.map(u=>(
    `<option value="${u.id}">${u.username}</option>`
  )).join('');
}

// ===== Mở modal tạo action =====
const actionModal = document.getElementById('action-modal');
const btnNew = document.getElementById('btn-new-action');
const actionForm = document.getElementById('action-form');
function closeActionModal(){ actionModal.classList.add('hidden'); }

// Render short form rows
function renderShortForm(items=[]){
  const wrap = document.getElementById('sf-list');
  wrap.innerHTML = '';
  items.forEach(addSfRow);
}
function addSfRow(row={key:'', value:''}){
  const wrap = document.getElementById('sf-list');
  const div = document.createElement('div');
  div.className = 'grid grid-cols-12 gap-2';
  div.innerHTML = `
    <input class="col-span-5 border rounded px-2 py-1 sf-key"   placeholder="key"   value="${row.key ?? ''}">
    <input class="col-span-6 border rounded px-2 py-1 sf-value" placeholder="value" value="${row.value ?? ''}">
    <button type="button" class="col-span-1 border rounded px-2 py-1 btn-del-sf">&times;</button>
  `;
  wrap.appendChild(div);
}
document.getElementById('btn-add-sf')?.addEventListener('click', ()=> addSfRow('', ''));
document.getElementById('sf-list')?.addEventListener('click', (e)=>{
  if (e.target.closest('.btn-del-sf')) e.target.closest('.grid').remove();
});

// Nút + New Action
btnNew?.addEventListener('click', async ()=>{
  await loadAssignees();
  openActionModal('create');
});
document.getElementById('action-cancel')?.addEventListener('click', closeActionModal);

// Submit form (create/update)
actionForm?.addEventListener('submit', async (e)=>{
  e.preventDefault();
  const mode = document.getElementById('action_id').value ? 'update' : 'create';
  const sf = Array.from(document.querySelectorAll('#sf-list .grid')).map(row=>({
    key:   row.querySelector('.sf-key').value.trim(),
    value: row.querySelector('.sf-value').value.trim()
  })).filter(x=>x.key!=='' || x.value!=='');

  const payload = new URLSearchParams({
    action: mode==='create' ? 'action_create' : 'action_update',
    action_id: document.getElementById('action_id').value,
    device_id: document.getElementById('action_device_id').value.trim(),
    title:     document.getElementById('action_title').value.trim(),
    priority:  document.getElementById('action_priority').value,
    due_date:  document.getElementById('action_due').value.replace('T',' '),
    assigned_to_user_id: document.getElementById('action_assignee').value || '',
    short_form: JSON.stringify(sf),
    status:    (document.getElementById('action_status')?.value || 'open')
  });

  const res = await authFetch(window.API_BACKEND, {
    method: 'POST',
    headers: {'Content-Type':'application/x-www-form-urlencoded'},
    body: payload.toString()
  });
  const json = await res.json();
  if (json.status === 'success') {
    toast(json.message, true);
    closeActionModal();
    // Reload bảng summary ở tab Action hiện tại
    loadBackendIssues();

  } else {
    toast(json.message || 'Error', false);
  }
});


</script>

<div id="confirm-modal" class="fixed inset-0 z-[9999] hidden">
  <div class="absolute inset-0 bg-black/50"></div>
  <div class="relative mx-auto my-10 w-[92vw] max-w-sm bg-white rounded-lg shadow-xl p-4">
    <h3 id="confirm-title" class="text-lg font-semibold mb-2">Please confirm</h3>
    <p id="confirm-message" class="text-sm text-gray-700 mb-4">Are you sure?</p>

    <!-- NEW: Reason field (ẩn mặc định, bật khi requireReason=true) -->
    <div id="confirm-reason-wrap" class="mb-4 hidden">
      <label for="confirm-reason" class="block text-sm text-gray-700" data-translate="Reason">Reason</label>
      <textarea id="confirm-reason"
                class="mt-1 w-full border rounded px-2 py-1"
                rows="3"
                maxlength="100"
                placeholder="Nhập lý do..."></textarea>
      <div class="text-xs text-gray-500 mt-1">
        <span id="confirm-reason-count">0</span>/100
      </div>
    </div>

    <div class="flex justify-end gap-2">
      <button id="confirm-cancel" class="px-3 py-1.5 rounded border">Cancel</button>
      <button id="confirm-ok" class="px-3 py-1.5 rounded bg-blue-600 text-white">Confirm</button>
    </div>
  </div>
</div>

<script>
// Reusable confirm dialog
  function confirmDialog(message, title = 'Please confirm', opts = {}) {
    // opts: { requireReason?: boolean, placeholder?: string, minLength?: number, maxLength?: number }
    return new Promise(resolve => {
      const dlg    = document.getElementById('confirm-modal');
      const t      = document.getElementById('confirm-title');
      const m      = document.getElementById('confirm-message');
      const ok     = document.getElementById('confirm-ok');
      const cancel = document.getElementById('confirm-cancel');

      const wrap   = document.getElementById('confirm-reason-wrap');
      const input  = document.getElementById('confirm-reason');
      const count  = document.getElementById('confirm-reason-count');

      t.textContent = title;
      m.textContent = message;

      // setup reason UI
      const needReason = !!opts.requireReason;
      wrap.classList.toggle('hidden', !needReason);
      input.value = '';
      input.placeholder = opts.placeholder || 'Nhập lý do...';
      input.maxLength = Number.isFinite(opts.maxLength) ? opts.maxLength : 100;
      if (count) count.textContent = '0';
      input.oninput = () => { if (count) count.textContent = String(input.value.length); };

      dlg.classList.remove('hidden');

      const cleanup = () => {
        ok.removeEventListener('click', onOk);
        cancel.removeEventListener('click', onCancel);
        dlg.removeEventListener('click', onOverlay);
      };
      const close = (res) => {
        dlg.classList.add('hidden');
        cleanup();
        resolve(res);
      };
      const onOk = () => {
        if (needReason) {
          const min = Number.isFinite(opts.minLength) ? opts.minLength : 1;
          const val = input.value.trim();
          if (val.length < min) { input.focus(); return; }
          close({ ok: true, reason: val });
        } else {
          close({ ok: true, reason: '' });
        }
      };
      const onCancel = () => close({ ok: false, reason: '' });
      const onOverlay = (e) => { if (e.target === dlg.firstElementChild) close({ ok:false, reason:'' }); };

      ok.addEventListener('click', onOk);
      cancel.addEventListener('click', onCancel);
      dlg.addEventListener('click', onOverlay);
    });
  }

// === Replace the old prompt() flow for Approve / Reject ===

document.addEventListener('click', async (e) => {
  const approveBtn = e.target.closest('.btn-approve');
  const rejectBtn  = e.target.closest('.btn-reject');
  if (!approveBtn && !rejectBtn) return;

  const id = (approveBtn || rejectBtn).dataset.id;

  const res = await confirmDialog(
    approveBtn ? 'Approve this action?' : 'Reject this action?',
    'Verification',
    { requireReason: true, placeholder: approveBtn ? 'Lý do approve...' : 'Lý do reject...' }
  );
  if (!res.ok) return;

  const body = new URLSearchParams();
  body.set('action', approveBtn ? 'approve_action' : 'reject_action');
  body.set('action_id', id);
  body.set('reason', res.reason); // 👈 gửi reason
  body.set('note',   res.reason); // 👈 tương thích

  const r = await authFetch('backend.php', {
    method: 'POST',
    headers: { 'Content-Type':'application/x-www-form-urlencoded' },
    body: body.toString()
  });
  const j = await r.json().catch(()=> ({}));

  if (j?.status === 'success') {
    updateActionRowUI(approveBtn || rejectBtn,
                      approveBtn ? 'approved' : 'rejected');
    document.dispatchEvent(new CustomEvent('action:changed'));
  } else {
    alert(j?.message || j?.error || 'Operation failed');
  }
});




function updateActionRowUI(srcBtn, newApproval){
  const tr = srcBtn.closest('tr');
  if (!tr) return;

  // Xác định 2 ô cần sửa (dùng data-col trước, sai mới fallback index)
  const tdStatus = tr.querySelector('[data-col="status"]')       || tr.children[6];
  const tdVerify = tr.querySelector('[data-col="verification"]') || tr.children[7];

  // Badge theo trạng thái mới
  const cls = newApproval === 'approved' ? 'pill-green'
            : newApproval === 'rejected' ? 'pill-amber'
            : 'pill-muted';
  const badgeHtml = `<span class="pill ${cls} appr-badge">${window.translationManager.translate(newApproval)}</span>`;

  // 1) Status cell: loại bỏ badge cũ (nếu có) rồi gắn badge mới
  tdStatus.querySelectorAll('.appr-badge').forEach(x => x.remove());
  tdStatus.insertAdjacentHTML('beforeend', ' ' + badgeHtml);

  // 2) Verification cell: xóa hẳn nút ✔ ✖, chỉ để lại badge
  tdVerify.querySelectorAll('.btn-approve, .btn-reject').forEach(b => b.remove());
  const curBadge = tdVerify.querySelector('.pill');
  if (curBadge) curBadge.replaceWith(badgeHtml);
  else tdVerify.innerHTML = badgeHtml;

  // 3) Phòng xa: nếu còn nút nào sót trong cùng hàng → disable + ẩn
  tr.querySelectorAll('.btn-approve, .btn-reject').forEach(b=>{
    b.setAttribute('disabled','disabled');
    b.classList.add('is-disabled','opacity-40','pointer-events-none');
  });
}



// Gắn một lần ở document để không mất sau re-render
async function expandIssueRow(aid, forceShow = true) {
  const modal = document.getElementById('device-actions-modal');
  if (!modal || modal.classList.contains('hidden')) return;

  const tbody = modal.querySelector('#device-actions-table tbody, #device-actions-tbody');
  const trDetails = tbody?.querySelector(`.tr-details[data-details="${aid}"]`);
  const btn = tbody?.querySelector(`.js-expand[data-aid="${aid}"]`);
  if (!trDetails || !btn) return;

  const willShow = forceShow ? true : !trDetails.classList.contains('show');
  trDetails.classList.toggle('show', willShow);
  btn.setAttribute('aria-expanded', willShow ? 'true' : 'false');

  const icon = btn.querySelector('i');
  if (icon){
    icon.classList.remove('fa-chevron-right','fa-chevron-down','fa-caret-right','fa-caret-down','fas');
    icon.classList.add('fa-solid', willShow ? 'fa-chevron-down' : 'fa-chevron-right');
  }
  if (!willShow) { 
    // đóng lại thì thôi, mở khóa nếu bạn có dùng lock
    try { lockActionsTableWidth(false); } catch(_) {}
    return; 
  }

  const box = document.getElementById(`plans-${aid}`);
  if (!box) return;

  // khóa trước khi expand để tránh "nhảy cột", nhưng sẽ mở khóa sau khi render xong
  try { lockActionsTableWidth(true); } catch(_) {}

  // helpers
  const esc = s => String(s ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;')
                                  .replace(/>/g,'&gt;').replace(/"/g,'&quot;').replace(/'/g,'&#39;');
  const fmtDate = x => {
    if (!x) return '-';
    const d = new Date(String(x).replace(' ','T'));
    if (isNaN(d)) return esc(x);
    const p = n => String(n).padStart(2,'0');
    return `${d.getFullYear()}-${p(d.getMonth()+1)}-${p(d.getDate())}`;
  };

  // hàm render bảng con – luôn bọc overflow-x-auto + table-fixed + colgroup
  const renderPlansTable = (plans=[]) => {
    const rows = plans.map(p => {
      const owner = p.owner_name || p.owner || (p.owner_user_id != null ? ('#'+p.owner_user_id) : '-');
      const when  = p.est_date || p.planned_completion_date || p.plan_date || p.due_date || '';
      return `
        <tr class="border-b last:border-0 align-top">
          <td class="p-1 whitespace-nowrap">${esc(p.plan_code || ('AP'+String(p.id||0).padStart(5,'0')))}</td>
          <td class="p-1 break-words whitespace-normal">${esc(p.plan_text||'')}</td>
          <td class="p-1 whitespace-nowrap">${fmtDate(when)}</td>
          <td class="p-1 whitespace-nowrap">${esc(owner)}</td>
          <td class="p-1 whitespace-nowrap">
            <span class="pill ${
              String(p.status||'').toLowerCase()==='done' ? 'pill-green' :
              String(p.status||'').toLowerCase()==='in_progress' ? 'pill-amber' : 'pill-muted'
            }">${esc(window.translationManager.translate( p.status) ||'-')}</span>
          </td>
        </tr>`;
    }).join('');

    box.innerHTML = `
      <div class="overflow-x-auto">
        <table class="min-w-[1000px] text-xs table-fixed">
          <colgroup>
            <col style="width:8rem;">   <!-- ACTION ID -->
            <col style="width:auto;">   <!-- ACTION PLAN -->
            <col style="width:12rem;">  <!-- PLANNED DATE -->
            <col style="width:10rem;">  <!-- OWNER -->
            <col style="width:8rem;">   <!-- STATUS -->
          </colgroup>
          <thead class="bg-gray-50">
            <tr>
              <th class="p-1 text-left" data-translate="Action ID">Action ID</th>
              <th class="p-1 text-center" data-translate="Action plan">Action Plan</th>
              <th class="p-1 text-left" data-translate="Planned completion date">Planned completion date</th>
              <th class="p-1 text-center" data-translate="Owner">Owner</th>
              <th class="p-1 text-center" data-translate="Status">Status</th>
            </tr>
          </thead>
          <tbody>${rows}</tbody>
        </table>
      </div>`;

    // rất quan trọng: mở khóa SAU khi đã render xong để trình duyệt tính lại layout
    requestAnimationFrame(() => { try { lockActionsTableWidth(false); } catch(_) {} });
  };

  // nếu đã nạp trước đó thì chỉ cần đảm bảo wrapper đúng & mở khóa
  if (box.dataset.loaded === '1') {
    // bảo đảm có wrapper overflow-x-auto (phòng lần render cũ chưa có)
    if (!box.querySelector('.overflow-x-auto')) renderPlansTable([]);
    requestAnimationFrame(() => { try { lockActionsTableWidth(false); } catch(_) {} });
    return;
  }

  box.innerHTML = `<span class="text-gray-400">Loading…</span>`;
  try {
    const url  = `${window.API_BASE}?action=list_action_plans&action_id=${encodeURIComponent(aid)}&_=${Date.now()}`;
    const resp = await authFetch(url, { credentials:'include', cache:'no-store', headers:{'Accept':'application/json'} });
    if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
    const ct = resp.headers.get('content-type') || '';
    if (!ct.includes('application/json')) throw new Error(`Non-JSON (${ct})`);
    const plans = await resp.json();

    if (!Array.isArray(plans) || plans.length === 0) {
      box.innerHTML = `<span class="text-gray-500">No action plans</span>`;
      box.dataset.loaded = '1';
      requestAnimationFrame(() => { try { lockActionsTableWidth(false); } catch(_) {} });
      return;
    }
    renderPlansTable(plans);
    box.dataset.loaded = '1';
  } catch (err) {
    box.innerHTML = `<span class="text-red-600">Load plans failed: ${esc(err.message||err)}</span>`;
    requestAnimationFrame(() => { try { lockActionsTableWidth(false); } catch(_) {} });
  }
}


if (!window.__bindPlanExpander) {
  window.__bindPlanExpander = true;
  document.addEventListener('click', async (e) => {
    const modal = document.getElementById('device-actions-modal');
    if (!modal || modal.classList.contains('hidden')) return;

    const btn = e.target.closest('.js-expand');
    if (!btn || !modal.contains(btn)) return;

      lockActionsTableWidth(true);        // <-- THÊM DÒNG NÀY
      const aid = btn.dataset.aid;
      await expandIssueRow(aid, false);   // toggle
  });
}
document.addEventListener('device_actions:rendered', async (ev) => {
  const mode = window.__expandAfterRefresh;
  if (!mode) return;

  const modal = document.getElementById('device-actions-modal');
  const tbody = modal?.querySelector('#device-actions-table tbody, #device-actions-tbody');
  if (!tbody) return;

  if (mode === 'all') {
    const btns = tbody.querySelectorAll('.js-expand');
    for (const b of btns) {
      await expandIssueRow(b.dataset.aid, true);
    }
  } else if (mode === 'first') {
    const first = tbody.querySelector('.js-expand');
    if (first) await expandIssueRow(first.dataset.aid, true);
  }

  // clear cờ – lần refresh sau chỉ auto-expand khi bạn set lại
  window.__expandAfterRefresh = '';
  lockActionsTableWidth(true);
});
function lockActionsTableWidth(lock = true){
}

document.addEventListener('click', async (e) => {
  const btn = e.target.closest('.js-expand');
  const m   = document.getElementById('device-actions-modal');
  if (!btn || !m || m.classList.contains('hidden')) return;
  lockActionsTableWidth(true);          // khóa TRƯỚC khi bung
  // ... gọi expandIssueRow như bạn đang làm
});
document.addEventListener('click', (e)=>{
  if (e.target.id === 'dam-close' || e.target.closest('#dam-close')) {
    lockActionsTableWidth(false);       // mở khóa khi đóng modal
  }
});



function unlockActionsTableWidth(){
  const tbl = document.getElementById('device-actions-table');
  if (!tbl) return;
  tbl.style.minWidth = '1100px';
  tbl.style.width    = 'auto';
  delete tbl.dataset.lockedWidth;
}







// Sau khi bảng render xong (event bạn đã bắn), khóa width
document.addEventListener('device_actions:rendered', () => {
  lockActionsTableWidth(true);
});

// Khi đóng modal, mở khóa để lần mở sau tính lại theo khung hiện tại
document.addEventListener('click', (e)=>{
  if (e.target.id === 'dam-close' || e.target.closest('#dam-close')) {
    lockActionsTableWidth(false);
  }
});

// Nếu người dùng resize khi modal đang mở: khóa lại theo kích thước mới
window.addEventListener('resize', () => {
  const modal = document.getElementById('device-actions-modal');
  if (!modal || modal.classList.contains('hidden')) return;
  lockActionsTableWidth(true);
});

</script>

<script>
(function(){
  const TAB_BTN_SEL = '.tab-btn[data-top-tab]';
  // coi tab-content là “panel”; vẫn hỗ trợ top-tab-panel nếu còn
  const CONTENT_SEL = '.tab-content';
  const PANEL_SEL   = '.top-tab-panel[data-top-tab]';

  let ready = false;
  const queue = [];
  let observer = null;

  function getState(){
    return {
      btns:    document.querySelectorAll(TAB_BTN_SEL),
      contents:document.querySelectorAll(CONTENT_SEL),
      panels:  document.querySelectorAll(PANEL_SEL),
    };
  }

  function reallySetTopTab(name){
    const {btns, contents, panels} = getState();
    if (!btns.length || (!contents.length && !panels.length)){
      console.warn('[safeSetTopTab] Tabs not ready, skip');
      return;
    }

    // bật/tắt nút
    btns.forEach(b => {
      const active = (b?.dataset?.topTab === name);
      b?.classList.toggle('active',  !!active);
    });

    // show/hide nội dung .tab-content
    contents.forEach(c => {
      const on = (c.id === 'tab-' + name);
      c.classList.toggle('active', on);     // bạn đã có CSS .tab-content.active {display:block}
    });

    // nếu còn dùng .top-tab-panel thì cũng sync luôn (không bắt buộc)
    panels.forEach(p => {
      const on = (p?.dataset?.topTab === name);
      p?.classList.toggle('hidden', !on);
    });

    // gọi load nếu là tab action
    if (name === 'action' && typeof window.loadBackendIssues === 'function'){
      window.loadBackendIssues();
    }
  }

  function safeSetTopTab(name){
    if (ready) { reallySetTopTab(name); }
    else { queue.push(name); }
  }
  window.setTopTab = safeSetTopTab;

  document.addEventListener('DOMContentLoaded', () => {
    const tryReady = () => {
      const {btns, contents, panels} = getState();
      // chỉ cần có nút + (tab-content hoặc panels) là coi như sẵn sàng
      if (btns.length && (contents.length || panels.length)){
        ready = true;
        while (queue.length) reallySetTopTab(queue.shift());
        observer?.disconnect(); observer = null;
        return true;
      }
      return false;
    };

    if (tryReady()) return;
    observer = new MutationObserver(tryReady);
    observer.observe(document.body, {childList:true, subtree:true});

    const initial = (location.hash || '').replace('#','').trim();
    if (initial) safeSetTopTab(initial);
    window.addEventListener('hashchange', () => {
      const h = (location.hash || '').replace('#','').trim();
      if (h) safeSetTopTab(h);
    });
  });
})();
</script>

</body>
</html>
