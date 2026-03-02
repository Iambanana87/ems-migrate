<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../helper/jwt.php';
require_once __DIR__ . '/../helper/api_call.php';
require_once __DIR__ . '/../helper/auth_helper.php';

/* ========= API mode detection ========= */
$wants_json =
  stripos($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') !== false
  || (($_GET['format'] ?? '') === 'json')
  || (($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest');

/* Tránh warning chen vào JSON */
ini_set('display_errors', $wants_json ? 0 : 1);
ini_set('log_errors', 1);
error_reporting(E_ALL);

/* ========= DB config ========= */
if (defined('DB_HOST')) {
  $DB_HOST = DB_HOST; $DB_USER = DB_USER; $DB_PASS = DB_PASS; $DB_NAME = DB_NAME;
} else {
  $DB_HOST = $servername ?? 'localhost';
  $DB_USER = $username  ?? '';
  $DB_PASS = $password  ?? '';
  $DB_NAME = $dbname    ?? '';
}

/* ========= Helpers ========= */
function safe_return($raw) {
  if (!$raw) return '../index.html';
  if (preg_match('~^(?:https?:)?//~i', $raw)) return '../index.html';
  return str_replace(["\r","\n"], '', $raw);
}
function append_query($url, array $params) {
  $hash = ''; $pos = strpos($url, '#');
  if ($pos !== false) { $hash = substr($url, $pos); $url = substr($url, 0, $pos); }
  $sep = (strpos($url, '?') !== false) ? '&' : '?';
  return $url . $sep . http_build_query($params) . $hash;
}
function normalize_return_for_role($ret, $role) {
  $ret = safe_return($ret);
  $trimmed = ltrim($ret, '/');
  $role = strtolower((string)$role);
  if ($role !== 'admin' && preg_match('~^backend/~i', $trimmed)) return '../index.html';
  return $ret;
}

function validateInput($username, $password) {
  if (strlen($username) < 4 || strlen($username) > 20 || !preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
    return "Username phải 4–20 ký tự và chỉ gồm chữ/số/gạch dưới.";
  }
  if (strlen($password) < 8) {
    return "Mật khẩu phải ít nhất 8 ký tự.";
  }
  return "";
}
function respond_json_ok(string $token, array $userRow, array $payload): void {
  // Chuẩn hoá roles từ payload
  $roles = auth_extract_role_names($payload['role'] ?? ($payload['roles'] ?? []));
  $primary = auth_primary_role($roles);

  header('Content-Type: application/json; charset=utf-8');
  header('Vary: Accept');
  echo json_encode([
    "status" => "ok",
    "token"  => $token,
    "user"   => [
      "id"          => (int)($userRow['id'] ?? 0),          // id DB nếu có
      "uid"         => $payload['sub'] ?? ($userRow['uid'] ?? null), // UUID từ token
      "username"    => $userRow['username'] ?? ($payload['username'] ?? null),
      "role"        => $primary,                            // primary role (string)
      "roles"       => $roles,                              // mảng role name
      "exp"         => (int)($payload['exp'] ?? 0)
    ]
  ], JSON_UNESCAPED_UNICODE);
  exit;
}

function respond_json_error(string $message, int $code = 400): void {
  http_response_code($code);
  header('Content-Type: application/json; charset=utf-8');
  header('Vary: Accept');
  echo json_encode(["status" => "error", "message" => $message], JSON_UNESCAPED_UNICODE);
  exit;
}

/* ========= Chuẩn hoá input JSON cho POST ========= */
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== false) {
  $jsonBody = json_decode(file_get_contents('php://input'), true);
  if (is_array($jsonBody)) {
    $_POST = $jsonBody + $_POST;
  }
}

/* ========= Chuẩn hoá return/redirect ========= */
$rawParam = $_GET['return'] ?? $_POST['return'] ?? $_GET['redirect'] ?? $_POST['redirect'] ?? '../index.html';
$return   = safe_return($rawParam);
if (stripos($return, '/backend/') === 0) {
  $return = './' . substr($return, strlen('/backend/'));
}

/* ========= 1) Nếu là POST có credentials → luôn đăng nhập (mint token mới) qua IAM ========= */
if ($_SERVER['REQUEST_METHOD'] === 'POST'
    && isset($_POST['username'], $_POST['password'])
    && $_POST['username'] !== '' && $_POST['password'] !== '') {

  $login_username = trim($_POST['username']);
  $login_password = trim($_POST['password']);
  $otp            = trim($_POST['otp'] ?? '');
  $return         = safe_return($_POST['return'] ?? $_POST['redirect'] ?? $rawParam);

  // (Tuỳ IAM, nếu username có dấu/chấm/email thì nới rule validateInput theo nhu cầu)
  if ($err = validateInput($login_username, $login_password)) {
    if ($wants_json) respond_json_error($err, 400);
    $error = $err; // cho UI mode
  } else {
    // 1) Gọi Auth service (IAM)
    $result = api_request(
      'POST',
      '?c=AuthController&m=login',
      [
        'username' => $login_username,
        'password' => $login_password,
        'otp'      => $otp
      ]
    );

    // 2) Chuẩn hoá lỗi & lấy token
    $token   = '';
    $payload = null;
    if (!is_array($result)) {
      $error = 'Auth service unreachable.';
    } else {
      $status = (int)($result['status'] ?? 0);
      $body   = is_array($result['body'] ?? null) ? $result['body'] : [];
      $rawObj = json_decode($result['raw'] ?? '', true);

      if ($status !== 200) {
        $apiErr = $body['error'] ?? ($rawObj['error'] ?? 'auth_failed');
        $error  = (stripos((string)$apiErr, 'otp') !== false) ? 'OTP error!' : (string)$apiErr;
      } else {
        $token = (string)($body['accessToken'] ?? '');
        if ($token === '') {
          $apiErr = $body['error'] ?? ($rawObj['error'] ?? 'Missing accessToken.');
          $error  = (stripos((string)$apiErr, 'otp') !== false) ? 'OTP error!' : (string)$apiErr;
        } else {
          try {
            $payload = jwt_decode($token, JWT_SECRET);
            if (!is_array($payload)) {
              $error = 'Invalid or expired token.'; $payload = null;
            }
          } catch (Throwable $e) {
            $error = 'Invalid or expired token.'; $payload = null;
          }
        }
      }
    }

    // 3) JSON mode → trả luôn, KHÔNG đụng DB cũ
    if ($wants_json) {
      if (!empty($error)) {
        respond_json_error($error, 401);
      } else {
        // Xác định role chính từ payload (ưu tiên roles[])
        $roles = auth_extract_role_names($payload['role'] ?? ($payload['roles'] ?? []));
        $primary = auth_primary_role($roles);

        $userRow = [
          'id'       => 0, // không dùng DB local
          'uid'      => $payload['sub'] ?? null,
          'username' => $payload['username'] ?? $login_username,
          'role'     => $primary,
        ];
        respond_json_ok($token, $userRow, $payload);
      }
    }

    // 4) UI mode → set cookie + redirect (KHÔNG đụng DB cũ)
    if (empty($error)) {
      $roles   = auth_extract_role_names($payload['role'] ?? ($payload['roles'] ?? []));
      $primary = auth_primary_role($roles);

      setcookie('ems_token', $token, [
        'expires'  => (int)(($payload['exp'] ?? 0) ?: (time()+3600)),
        'path'     => '/',
        'secure'   => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'httponly' => true,
        'samesite' => 'Lax',
      ]);

      $success_token = $token;
      $success_user  = [
        "id"       => 0,
        "username" => $payload['username'] ?? $login_username,
        "role"     => $primary,
        "exp"      => (int)($payload['exp'] ?? (time()+3600)),
      ];

      $target = normalize_return_for_role($return, $primary);
      $target = append_query($target, ['notice' => 'login_success', 'user' => $success_user['username'] ?? '']);
      $GLOBALS['_LOGIN_REDIRECT'] = $target;

    } else {
      // HTML mode lỗi: hiển thị $error bên dưới theo bố cục sẵn có (nếu có)
    }
  }
}


/* ========= 2) Nếu đã có cookie (đăng nhập sẵn) mà KHÔNG POST credentials ========= */
$AUTH = auth_user_or_null_ui(); // đọc token từ cookie/GET/header đã chuẩn hoá
if ($AUTH) {
  if ($wants_json) {
    header('Cache-Control: no-store'); // tránh cache response auth

    $token = $AUTH['token'] ?? ($_COOKIE['ems_token'] ?? ($_GET['token'] ?? ''));
    $userRow = [
      'id'       => $AUTH['id'] ?? 0,
      'uid'      => $AUTH['uid'] ?? null,
      'username' => $AUTH['username'] ?? null,
    ];
    // payload tối thiểu để respond_json_ok xuất role/roles/exp
    $payload = $AUTH['_raw'] ?? [
      'sub'  => $AUTH['uid'] ?? null,
      'role' => $AUTH['roles'] ?? [],
      'exp'  => $AUTH['exp']  ?? null
    ];
    respond_json_ok($token, $userRow, $payload);
  }

  // UI mode → redirect như cũ (dựa vào primary role đã chuẩn hoá)
  $rawParam = $_GET['return'] ?? $_GET['redirect'] ?? '../index.html';
  $ret = normalize_return_for_role(safe_return($rawParam), $AUTH['role'] ?? 'user');
  header('Location: ' . $ret);
  exit;
}


/* ========= 3) UI mode: nếu vừa đăng nhập ok thì trả HTML redirect ========= */
if (!empty($success_token)) {
  $redirect = $GLOBALS['_LOGIN_REDIRECT'] ?? '../index.html';
  ?>
  <!doctype html>
  <meta charset="utf-8">
  <script>
  (function(){
    try { localStorage.setItem('ems_token', <?= json_encode($success_token) ?>); } catch(e){}
    location.replace(<?= json_encode($redirect) ?>);
  })();
  </script>
  <noscript>Login thành công. Vui lòng bấm vào
    <a href="<?= htmlspecialchars($redirect, ENT_QUOTES) ?>">đây</a> để tiếp tục.</noscript>
  <?php
  exit;
}

/* ========= 4) Nếu là API mode mà tới đây (chưa có cookie & không POST credentials) → yêu cầu auth ========= */
if ($wants_json) {
  respond_json_error('auth_required', 401);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - EMS</title>
    <script src="../assets/js/tailwindcss.js"></script>
    <link rel="stylesheet" href="../assets/css/translation.css">
<script src="../assets/js/translations.js" defer></script>
</head>
<body class="bg-gray-100 flex items-center justify-center min-h-screen">
    <div class="bg-white p-8 rounded-lg shadow-lg w-full max-w-md">
        <div class="flex items-center space-x-3">
            <img src="../image/icon2.png" alt="Logo" style="max-height: 40px;">
        </div>
        <h2 class="text-2xl font-bold text-center mb-6">LOGIN EMS</h2>
    <?php if (isset($error)): ?>
        <p id="login-error"
          class="text-red-500 text-center mb-4 transition-opacity duration-700 ease-in-out">
          <?php echo htmlspecialchars($error); ?>
        </p>
        <script>
          // Ẩn thông báo sau 3 giây
          setTimeout(() => {
            const el = document.getElementById('login-error');
            if (el) {
              el.style.opacity = '0';
              setTimeout(() => el.remove(), 700); // Xóa hẳn sau hiệu ứng mờ
            }
          }, 3000);
        </script>
    <?php endif; ?>

        <form method="POST" action="">
            <div class="mb-4">
                <label for="username" class="block text-sm font-medium text-gray-700">Username</label>
                <input type="text" id="username" name="username" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-6">
                <label for="password" class="block text-sm font-medium text-gray-700">Password</label>
                <input type="password" id="password" name="password" required
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <div class="mb-6">
                <label for="otp" class="block text-sm font-medium text-gray-700">Otp</label>
                <input type="otp" id="otp" name="otp" 
                       class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-blue-500 focus:border-blue-500">
            </div>
            <button type="submit"
                    class="w-full bg-blue-600 text-white py-2 px-4 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                Login
            </button>
        </form>
    </div>
</body>
</html>