<?php
// Giả định bạn cần một secret key cho việc verify JWT. Thay thế bằng key thực tế của bạn.
$jwt_secret_key = 'change_this_super_secret_key_32+chars'; // Đặt secret key JWT ở đây

// Function để decode JWT với verification (sử dụng HMAC SHA256)
function jwt_decodem_iam($token, $secret) {
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        throw new Exception('Invalid token structure');
    }

    list($header, $payload, $signature) = $parts;

    // Decode header và payload
    $header_decoded = json_decode(base64_url_decode_iam($header), true);
    $payload_decoded = json_decode(base64_url_decode_iam($payload), true);

    if (!$header_decoded || !$payload_decoded) {
        throw new Exception('Invalid token encoding');
    }

    // Verify signature
    $expected_signature = hash_hmac('sha256', $header . '.' . $payload, $secret, true);
    $expected_signature_base64 = base64_url_encode_iam($expected_signature);

    if (!hash_equals($expected_signature_base64, $signature)) {
        throw new Exception('Invalid token signature');
    }

    // Kiểm tra expiration nếu có
    if (isset($payload_decoded['exp']) && $payload_decoded['exp'] < time()) {
        throw new Exception('Token expired');
    }

    return $payload_decoded;
}

// Helper function cho base64 URL safe
function base64_url_decode_iam($input) {
    $remainder = strlen($input) % 4;
    if ($remainder) {
        $input .= str_repeat('=', 4 - $remainder);
    }
    return base64_decode(strtr($input, '-_', '+/'));
}

function base64_url_encode_iam($input) {
    return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
}

function api_request_iam($method, $path, $data = []) {
    $base_url = 'http://192.168.110.2/web_develop/iam/cip3/';
    $url = $base_url . $path;

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);

    if (strtoupper($method) === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    } elseif (strtoupper($method) === 'GET' && !empty($data)) {
        $query = http_build_query($data, '', '&', PHP_QUERY_RFC3986);
        $url .= '&' . $query;
        curl_setopt($ch, CURLOPT_URL, $url);
    }

    $response = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);

    curl_close($ch);

    $body = json_decode($response, true);

    return [
        'status' => $status,
        'body' => $body ?? $response
    ];
}

// Thay log_message bằng error_log
function log_message($level, $message) {
    error_log("[$level] $message");
}
function capture_current_endpoint() {
  // ====== Cấu hình checkpoint cho UI ======
  $backendPagePath = '/ems/backend/backend.php'; // UI page cần “nới” lấy token từ cookie
  $cookieName      = 'ems_token';                // tên cookie chứa JWT
  $loginRedirect   = 'login.php?return=./backend.php';

  // ========= XÁC ĐỊNH PATH GỐC =========
  $raw_uri = $_SERVER['REQUEST_URI'] ?? '/';
  $path    = ltrim(parse_url($raw_uri, PHP_URL_PATH) ?? '/', '/'); // không có slash đầu
  $method  = $_SERVER['REQUEST_METHOD'] ?? 'GET';

  // ========= LẤY ACTION: ƯU TIÊN query -> POST form -> JSON body =========
  $action = null;

  // 1) query
  if (!empty($_SERVER['QUERY_STRING'])) {
    parse_str($_SERVER['QUERY_STRING'], $qs);
    if (!empty($qs['action'])) $action = $qs['action'];
  }

  // 2) POST form
  if ($action === null && $method === 'POST' && isset($_POST['action']) && $_POST['action'] !== '') {
    $action = $_POST['action'];
  }

  // 3) JSON body
  if ($action === null && $method === 'POST') {
    $ct = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
    if (strpos($ct, 'application/json') !== false) {
      if (!isset($GLOBALS['__M_RAW_BODY'])) {
        $GLOBALS['__M_RAW_BODY'] = file_get_contents('php://input'); // chỉ đọc 1 lần
      }
      $j = json_decode($GLOBALS['__M_RAW_BODY'] ?? '', true);
      if (is_array($j) && !empty($j['action'])) $action = $j['action'];
    }
  }

  // ========= NORMALIZE ENDPOINT THEO ỨNG DỤNG =========
  $normalized = '/' . $path; // luôn thêm slash đầu
  $normalized = preg_replace('#^/(web_develop|staging|dev)(?=/)#i', '', $normalized);

  if (stripos($normalized, 'api.php') !== false) {
    $normalized = '/ems/api.php' . ($action ? ('?action=' . $action) : '');
  } elseif (stripos($normalized, 'backend/backend.php') !== false) {
    $normalized = $backendPagePath . ($action ? ('?action=' . $action) : '');
  } elseif (stripos($normalized, 'backend/login.php') !== false) {
    $normalized = '/ems/backend/login.php';
  } else {
    if ($action) $normalized .= ('?action=' . $action);
  }

  $current_endpoint = $normalized;

  // Xác định có phải UI trang backend (GET, không có ?action=) không
  $is_backend_page = (
    stripos($current_endpoint, $backendPagePath) === 0
    && (strpos($current_endpoint, '?action=') === false)
    && $method === 'GET'
  );

  // ========= CÁC API PUBLIC (KHÔNG CẦN QUYỀN) =========
  $public_patterns = [
    '/ems/backend/login.php',
    '/ems/api.php?action=ping',
    // nếu cần: '/ems/api.php?action=whoami',
  ];

  foreach ($public_patterns as $pat) {
    if (fnmatch($pat, $current_endpoint)) {
      log_message('info', "Public endpoint: {$current_endpoint}");
      return; // bỏ qua kiểm quyền
    }
  }

  // ========= LẤY TOKEN =========
  // API (có ?action=) => CHỈ header; UI backend page => header trước, không có thì cookie
  $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
  $token = null;

  if ($authHeader && preg_match('/Bearer\s+(\S+)/i', $authHeader, $m)) {
    $token = $m[1];
  } elseif ($is_backend_page && !empty($_COOKIE[$cookieName])) {
    // CHỈ cho phép cookie khi là trang UI backend
    $token = $_COOKIE[$cookieName];
  }

  if (!$token) {
    if ($is_backend_page) {
      // Trang UI: thiếu token -> đưa đi login
      header('Location: ' . $loginRedirect);
      exit;
    }
    log_message('error', "Missing token for {$current_endpoint}");
    _respond_unauthorized('Authorization token not found');
  }

  // ========= DECODE JWT =========
  global $jwt_secret_key;
  try {
    $decoded = jwt_decodem_iam($token, $jwt_secret_key);
  } catch (Throwable $e) {
    if ($is_backend_page) {
      header('Location: ' . $loginRedirect);
      exit;
    }
    log_message('error', 'JWT decode failed: ' . $e->getMessage());
    _respond_unauthorized('Invalid or expired token');
  }

  // ========= LẤY DANH SÁCH ROLE =========
  $roles = [];
  if (!empty($decoded['role']) && is_array($decoded['role'])) {
    $roles = array_values(array_filter(array_map(function ($r) {
      if (is_array($r) && !empty($r['name'])) return $r['name'];
      if (is_string($r)) return $r;
      return null;
    }, $decoded['role'])));
  }

  log_message('info', "Checking permission for endpoint: {$current_endpoint}, roles: " . implode(',', $roles));

  // ========= GỌI IAM LẤY QUYỀN =========
  $response = api_request_iam(
    'GET',
    '?c=PermissionController&m=getPermissionWithRoles',
    ['roles' => $roles]
  );

  if (!is_array($response) || empty($response['status'])) {
    log_message('error', 'Permission API returned invalid response');
    if ($is_backend_page) {
      http_response_code(403);
      echo 'Forbidden by IAM (invalid response)';
      exit;
    }
    _respond_forbidden('Internal permission check failed');
  }

  $allowed_patterns = [];
  if ($response['status'] == 200 && !empty($response['body']['data'])) {
    foreach ($response['body']['data'] as $row) {
      if (isset($row['endpoint']) && $row['endpoint'] !== '') {
        $allowed_patterns[] = $row['endpoint'];
      }
    }
  }

  // ========= SO KHỚP ENDPOINT HIỆN TẠI (exact / wildcard / bỏ query) =========
  $candidates = [];
  $candidates[] = $current_endpoint;             // VD: /ems/backend/backend.php?notice=login_success
  $base = strtok($current_endpoint, '?');        // VD: /ems/backend/backend.php
  if ($base !== $current_endpoint) $candidates[] = $base;

  $ok = false;
  foreach ($candidates as $cand) {
    foreach ($allowed_patterns as $pat) {
      $hasWildcard = (strpos($pat, '*') !== false) || (strpos($pat, '?') !== false);
      if ($hasWildcard) {
        if (fnmatch($pat, $cand)) { $ok = true; break 2; }
      } else {
        if (strcasecmp($pat, $cand) === 0) { $ok = true; break 2; }
      }
    }
  }

  if (!$ok) {
    log_message('error', "Permission denied for {$current_endpoint}");
    if ($is_backend_page) {
      http_response_code(403);
      header('Content-Type: text/html; charset=utf-8');
      echo '<!doctype html><meta charset="utf-8"><title>403</title>Forbidden by IAM';
      exit;
    }
    _respond_forbidden(
      'Permission denied for this API endpoint.',
      ['current' => $current_endpoint, 'allow' => $allowed_patterns]
    );
  }

  // ====== Passed ======
  log_message('info', "IAM OK for {$current_endpoint}");
}


/**
 * Helper: Trả về lỗi 401
 */
function _respond_unauthorized($message) {
    header('Content-Type: application/json', true, 401);
    echo json_encode([
        'status' => 401,
        'error' => 'Unauthorized',
        'message' => $message
    ]);
    exit;
}

/**
 * Helper: Trả về lỗi 403
 */
function _respond_forbidden($message, $endpoint = null) {
    header('Content-Type: application/json', true, 403);
    echo json_encode([
        'status' => 403,
        'error' => 'Forbidden',
        'message' => $message,
        'endpoint' => $endpoint
    ]);
    exit;
}