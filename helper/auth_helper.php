<?php
// helper/auth_helper.php (EMS)
// Yêu cầu: có file helper/jwt.php với hàm jwt_verify($token): array claims

require_once __DIR__ . '/jwt.php';
require_once __DIR__ . '/../backend/config.php';

// --- Role helpers: rút danh sách role name từ mọi kiểu claim ---
function auth_extract_role_names($claim): array {
  // token cũ: "admin"
  if (is_string($claim) && $claim !== '') return [strtolower($claim)];

  // token mới: [{uuid,name,description}, ...] hoặc ["admin","manager"]
  if (is_array($claim)) {
    $names = [];
    foreach ($claim as $it) {
      if (is_string($it)) {
        $names[] = strtolower($it);
      } elseif (is_array($it) && isset($it['name'])) {
        $names[] = strtolower($it['name']);
      } elseif (is_object($it) && isset($it->name)) {
        $names[] = strtolower((string)$it->name);
      }
    }
    return array_values(array_unique($names));
  }
  return [];
}

function auth_primary_role(array $names): string {
  if (in_array('admin', $names, true))   return 'admin';
  if (in_array('manager', $names, true)) return 'manager';
  if (in_array('viewer', $names, true))  return 'viewer';
  return $names[0] ?? 'user';
}

function auth_has_role(array $AUTH, string $name): bool {
  $names = array_map('strtolower', (array)($AUTH['roles'] ?? []));
  return in_array(strtolower($name), $names, true);
}

// lấy token từ Header -> Cookie(ems_token) -> ?token= (UI fallback)
function auth__get_token_with_fallback(): ?string {
  $t = auth__get_bearer_token();
  if ($t) return $t;
  if (!empty($_COOKIE['ems_token'])) return $_COOKIE['ems_token'];
  if (!empty($_GET['token'])) return $_GET['token']; 
  return null;
}

// dành cho UI (có fallback Cookie, GET)
function auth_user_or_null_ui(): ?array {
  static $cached = null, $done = false;
  if ($done) return $cached; $done = true;

  $t = auth__get_token_with_fallback();
  if (!$t) return $cached = null;

  try {
    $claims = jwt_decode($t, JWT_SECRET);
    if (!is_array($claims)) return $cached = null;
    if (isset($claims['exp']) && time() >= (int)$claims['exp']) return $cached = null;
    // GÓP token & claims thô vào AUTH
    $norm = auth__normalize($claims);
    $norm['token'] = $t;
    $norm['_raw']  = $claims;
    return $cached = $norm;
  } catch (Throwable $e) {
    return $cached = null;
  }
}


function auth__get_bearer_token(): ?string {
  $h = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
  if (stripos($h, 'Bearer ') === 0) return trim(substr($h, 7));
  return null;
}

function auth__abort(int $code, string $msg) {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode(['error'=>$msg], JSON_UNESCAPED_UNICODE);
  exit;
}

function auth__normalize(array $c): array {
  // ID DB (nếu token có user_id numeric), và UID (UUID trong 'sub')
  $id  = is_numeric($c['user_id'] ?? null) ? (int)$c['user_id'] : 0;
  $uid = isset($c['sub']) ? (string)$c['sub'] : null;

  // Roles
  $roleNames = auth_extract_role_names($c['role'] ?? ($c['roles'] ?? []));
  $primary   = auth_primary_role($roleNames);

  return [
    'id'       => $id,                  // số (có thể 0 nếu token không có user_id)
    'uid'      => $uid,                 // UUID từ 'sub'
    'sub'      => $uid ?? (string)$id,  // giữ tương thích trường 'sub'
    'username' => $c['username'] ?? ($c['name'] ?? null),
    'roles'    => $roleNames,           // vd: ['admin']
    'role'     => $primary,             // vd: 'admin'
    'exp'      => $c['exp'] ?? null,
    '_raw'     => $c,
  ];
}


function auth_user_or_null(): ?array {
  static $cached = null, $done = false;
  if ($done) return $cached;
  $done = true;

  $t = auth__get_bearer_token();
  if (!$t) return $cached = null;

  try {
    $claims = jwt_decode($t, JWT_SECRET);     
    if (!is_array($claims)) return $cached = null;
    // (tuỳ chọn) kiểm tra exp/iat nếu jwt_verify không tự kiểm
    if (isset($claims['exp']) && time() >= (int)$claims['exp']) return $cached = null;
    return $cached = auth__normalize($claims);
  } catch (Throwable $e) {
    return $cached = null;
  }
}
function require_auth_header(): array {
  $u = auth_user_or_null(); // đọc chỉ Header như cũ
  if (!$u) auth__abort(401, 'auth_required');
  return $u;
}
function require_auth(): array {
  $u = auth_user_or_null();
  if (!$u) auth__abort(401, 'auth_required');
  return $u;
}

function require_roles(array $roles): array {
  $u = require_auth();
  $userRoles = array_map('strtolower', (array)($u['roles'] ?? []));
  $need = array_map('strtolower', $roles);
  $ok = (bool) array_intersect($userRoles, $need);
  if (!$ok) auth__abort(403, 'forbidden');
  return $u;
}


// Cho API: chấp nhận cookie ems_token hoặc Authorization: Bearer
function require_user_api(): array {
  // 1) Ưu tiên luồng UI: cookie ems_token
  if (!empty($_COOKIE['ems_token'])) {
    $tok = $_COOKIE['ems_token'];
    try {
      $claims = jwt_decode($tok, JWT_SECRET); // nếu verify OK
    } catch (Throwable $e) {
      // Nếu token do service khác ký (khác secret) => vẫn cho qua tối thiểu
      // (tuỳ chính sách bảo mật của bạn)
      $claims = ['token' => $tok];
    }
    // Chuẩn hoá cấu trúc trả về giống các helper khác (id/username/role...)
    return is_array($claims) ? auth__normalize($claims) : ['token'=>$tok];
  }

  // 2) Fallback: Authorization: Bearer ...
  $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
  if ($hdr && preg_match('/Bearer\s+(.+)/i', $hdr, $m)) {
    $tok = trim($m[1]);
    try {
      $claims = jwt_decode($tok, JWT_SECRET);
    } catch (Throwable $e) {
      $claims = ['token' => $tok];
    }
    return is_array($claims) ? auth__normalize($claims) : ['token'=>$tok];
  }

  auth__abort(401, 'auth_required');
}
// == ADMIN: Ưu tiên cookie ems_token, fallback Authorization header ==
function require_admin_any(): array {
  $u = auth_user_or_null_ui();
  if (!$u) $u = auth_user_or_null();
  if (!$u) auth__abort(401, 'auth_required');
  if (!auth_has_role($u, 'admin')) auth__abort(403, 'forbidden');
  return $u;
}

// ====== Shims giữ tên cũ để khỏi sửa code gọi ======
function requireLoginOr401()         { return require_auth(); }
function requireRolesOr403($roles)   { return require_roles((array)$roles); }
function requireAdmin()              { return require_roles(['admin']); }
function requireManagerOrAdmin()     { return require_roles(['manager','admin']); }

// (tiện) helpers lấy nhanh id/username/role
function auth_id(): int              { return (int)(require_auth()['id'] ?? 0); }
function auth_username(): ?string    { $u = require_auth(); return $u['username'] ?? null; }
function auth_role(): string         { $u = require_auth(); return strtolower((string)($u['role'] ?? 'user')); }
