<?php

ini_set('display_errors', 0);
ini_set('log_errors', 1);
ini_set('error_log', __DIR__ . '/php-error.log');

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Headers: Authorization, Content-Type");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

date_default_timezone_set('Asia/Ho_Chi_Minh');

/* ===== Dependencies ===== */
require_once __DIR__ . '/backend/config.php';     // phải tạo $pdo (PDO::ATTR_ERRMODE = EXCEPTION)
require_once __DIR__ . '/helper/jwt.php';
require_once __DIR__ . '/helper/auth_helper.php';


if (!isset($pdo) || !($pdo instanceof PDO)) {
  // Thử tự khởi tạo từ hằng số/biến sẵn có
  $host = defined('DB_HOST') ? DB_HOST : ($servername ?? 'localhost');
  $db   = defined('DB_NAME') ? DB_NAME : ($dbname    ?? '');
  $usr  = defined('DB_USER') ? DB_USER : ($username  ?? '');
  $pwd  = defined('DB_PASS') ? DB_PASS : ($password  ?? '');

  try {
    $pdo = new PDO("mysql:host=$host;dbname=$db;charset=utf8mb4", $usr, $pwd, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  } catch (Throwable $e) {
    send_json(['error' => 'db_connect_failed', 'detail' => $e->getMessage()], 500);
  }
}



/* ===== Utils ===== */
function send_json($data, int $code = 200) {
  while (ob_get_level()) { ob_end_clean(); }
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}
function read_json_body() {
  $raw = file_get_contents('php://input');
  if (!$raw) return [];
  $raw = trim($raw);
  $raw = preg_replace('/^\xEF\xBB\xBF/', '', $raw); // strip BOM
  $obj = json_decode($raw, true);
  return is_array($obj) ? $obj : [];
}
function must_login() {
  return require_admin_any();
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
  if ($action === 'save_layout') {
    /* POST: tạo mới / cập nhật */
    $me = must_login();
    $username = $me['username'] ?? ($me['user_id'] ?? 'unknown');

    $payload = read_json_body();
    if (!$payload) $payload = $_POST;

    $id          = isset($payload['id']) ? (int)$payload['id'] : 0;      // >0 => update
    $name        = trim($payload['name'] ?? '');
    $description = trim($payload['description'] ?? '');
    $layoutJson  = $payload['layout_json'] ?? $payload['data_json'] ?? null; // array|string
    $canvasW     = isset($payload['canvas_w']) ? (int)$payload['canvas_w'] : null;
    $canvasH     = isset($payload['canvas_h']) ? (int)$payload['canvas_h'] : null;

    if ($name === '')               throw new Exception('name_required', 400);
    if ($layoutJson === null)       throw new Exception('layout_json_required', 400);
    if (is_array($layoutJson))      $layoutJson = json_encode($layoutJson, JSON_UNESCAPED_UNICODE);

    json_decode($layoutJson);
    if (json_last_error() !== JSON_ERROR_NONE) throw new Exception('layout_json_invalid', 400);

    if ($id > 0) {
      $stmt = $pdo->prepare("UPDATE factory_layouts
         SET name=:name, description=:description,
             layout_json=:layout_json,
             canvas_w=:canvas_w, canvas_h=:canvas_h
        WHERE id=:id");
      $stmt->execute([
        ':name' => $name,
        ':description' => $description ?: null,
        ':layout_json' => $layoutJson,
        ':canvas_w' => $canvasW,
        ':canvas_h' => $canvasH,
        ':id' => $id
      ]);
      send_json(['ok' => true, 'id' => $id, 'mode' => 'update']);
    } else {
      $stmt = $pdo->prepare("INSERT INTO factory_layouts
        (name, description, layout_json, canvas_w, canvas_h, created_by)
        VALUES (:name, :description, :layout_json, :canvas_w, :canvas_h, :created_by)");
      $stmt->execute([
        ':name' => $name,
        ':description' => $description ?: null,
        ':layout_json' => $layoutJson,
        ':canvas_w' => $canvasW,
        ':canvas_h' => $canvasH,
        ':created_by' => $username
      ]);
      $newId = (int)$pdo->lastInsertId();
      send_json(['ok' => true, 'id' => $newId, 'mode' => 'create']);
    }
  }

  elseif ($action === 'list_layouts') {
    $me = must_login();
    $q     = trim($_GET['q'] ?? '');
    $limit = max(1, min(100, (int)($_GET['limit'] ?? 50)));
    $sql   = "SELECT id, name, description, canvas_w, canvas_h, created_by, updated_at
              FROM factory_layouts";
    $args  = [];
    if ($q !== '') {
      $sql .= " WHERE name LIKE :q OR description LIKE :q";
      $args[':q'] = "%$q%";
    }
    $sql .= " ORDER BY updated_at DESC LIMIT $limit";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($args);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    send_json(['items' => $rows]);
  }

  elseif ($action === 'get_layout') {
    $me = must_login();
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) throw new Exception('id_required', 400);
    $stmt = $pdo->prepare("SELECT id, name, description, layout_json, canvas_w, canvas_h, created_by, updated_at
                           FROM factory_layouts WHERE id=:id");
    $stmt->execute([':id' => $id]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) throw new Exception('not_found', 404);
    $row['layout_json'] = json_decode($row['layout_json'], true);
    send_json($row);
  }

  elseif ($action === 'delete_layout') {
    $me = must_login();
    $payload = read_json_body();
    if (!$payload) $payload = $_POST;

    $id = (int)($payload['id'] ?? 0);
    if ($id <= 0) throw new Exception('id_required', 400);

    // (tuỳ chọn) kiểm tra quyền sở hữu tại đây, ví dụ:
    $stmt = $pdo->prepare("SELECT created_by FROM factory_layouts WHERE id=:id");
    $stmt->execute([':id'=>$id]);
    $owner = $stmt->fetchColumn();
    if ($owner && $owner !== ($me['username'] ?? '')) throw new Exception('forbidden', 403);

    $stmt = $pdo->prepare("DELETE FROM factory_layouts WHERE id=:id");
    $stmt->execute([':id' => $id]);
    send_json(['ok' => true, 'id' => $id]);
  }

  else {
    send_json(['error' => 'unknown_action'], 404);
  }

} catch (Throwable $e) {
  $code = (int)($e->getCode() ?: 500);
  if ($code < 100 || $code > 599) $code = 500;
  send_json(['error' => $e->getMessage()], $code);
}
