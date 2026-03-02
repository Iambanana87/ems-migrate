<?php
// get_audit_trail.php
declare(strict_types=1);
ob_start();

// debug
$IS_DEBUG = isset($_GET['debug']);
ini_set('display_errors', $IS_DEBUG ? '1' : '0');
error_reporting(E_ALL);


require_once __DIR__ . '/../backend/config.php';
require_once __DIR__. '/../middlewave/middleware_endpoint_logger.php';
capture_current_endpoint();

header('Access-Control-Allow-Origin: *');

function json_out($payload, int $code = 200): void {
  if (ob_get_length()) { ob_clean(); }
  header('Content-Type: application/json; charset=utf-8');
  http_response_code($code);
  echo json_encode($payload, JSON_UNESCAPED_UNICODE);
  exit;
}

// (tuỳ chọn) endpoint test nhanh
if (($_GET['mode'] ?? '') === 'ping') {
  json_out(['status'=>'ok','time'=>date('c')]);
}

try {
  $pdo = new PDO(
    'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
    DB_USER,
    DB_PASS,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]
  );
} catch (Throwable $e) {
  json_out(['status'=>'error','message'=>'DB connect error','detail'=>$IS_DEBUG ? $e->getMessage() : null], 500);
}

$mode   = $_GET['mode']   ?? 'list';  // list | one | schema
$decode = (int)($_GET['decode'] ?? 1);

// ===== schema =====
if ($mode === 'schema') {
  $stmt = $pdo->prepare("
    SELECT COLUMN_NAME AS name, DATA_TYPE AS type, IS_NULLABLE AS nullable,
           COLUMN_DEFAULT AS default_value, COLLATION_NAME AS collation
    FROM INFORMATION_SCHEMA.COLUMNS
    WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'audit_trail'
    ORDER BY ORDINAL_POSITION
  ");
  $stmt->execute([':db' => DB_NAME]);
  json_out(['status'=>'success','table'=>'audit_trail','columns'=>$stmt->fetchAll()]);
}

// ===== one =====
if ($mode === 'one') {
  $id = trim((string)($_GET['id'] ?? ''));
  if ($id === '') json_out(['status'=>'error','message'=>'Missing id'], 400);

  $st = $pdo->prepare("SELECT id,type,reason,who,created_at,diff FROM audit_trail WHERE id=:id");
  $st->execute([':id'=>$id]);
  $row = $st->fetch();
  if (!$row) json_out(['status'=>'error','message'=>'Not found'], 404);

  if ($decode) {
    $tmp = json_decode($row['diff'], true);
    if (json_last_error() === JSON_ERROR_NONE) $row['diff'] = $tmp;
    else { $row['diff_raw'] = $row['diff']; $row['diff'] = null; }
  }
  json_out(['status'=>'success','item'=>$row]);
}

// ===== list (mặc định) =====
$page      = max(1, (int)($_GET['page'] ?? 1));
$page_size = max(1, min(500, (int)($_GET['page_size'] ?? 100)));
$offset    = ($page - 1) * $page_size;

$type = trim((string)($_GET['type'] ?? ''));
$who  = trim((string)($_GET['who']  ?? ''));
$q    = trim((string)($_GET['q']    ?? ''));
$from = trim((string)($_GET['from'] ?? ''));
$to   = trim((string)($_GET['to']   ?? ''));
$dir  = (strtolower($_GET['sort'] ?? 'desc') === 'asc') ? 'ASC' : 'DESC';

$where=[]; $params=[];
if ($type!==''){ $where[]='type=:type'; $params[':type']=$type; }
if ($who!==''){  $where[]='who=:who';   $params[':who']=$who;   }
if ($q!==''){    $where[]='reason LIKE :q'; $params[':q']='%'.$q.'%'; }
if ($from!==''){ $where[]='created_at >= :from'; $params[':from']=$from; }
if ($to!==''){   $where[]='created_at <= :to';   $params[':to']=$to;     }
$wsql = $where ? 'WHERE '.implode(' AND ',$where) : '';

$stc = $pdo->prepare("SELECT COUNT(*) FROM audit_trail $wsql");
$stc->execute($params);
$total = (int)$stc->fetchColumn();

$st = $pdo->prepare("
  SELECT id,type,reason,who,created_at,diff
  FROM audit_trail
  $wsql
  ORDER BY created_at $dir
  LIMIT :lim OFFSET :off
");
foreach ($params as $k=>$v){ $st->bindValue($k,$v); }
$st->bindValue(':lim',$page_size,PDO::PARAM_INT);
$st->bindValue(':off',$offset,PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

if ($decode) {
  foreach ($rows as &$r) {
    $tmp = json_decode($r['diff'], true);
    if (json_last_error()===JSON_ERROR_NONE) $r['diff']=$tmp;
    else { $r['diff_raw']=$r['diff']; $r['diff']=null; }
  } unset($r);
}

json_out([
  'status'=>'success',
  'page'=>$page,
  'page_size'=>$page_size,
  'total'=>$total,
  'items'=>$rows,
]);
