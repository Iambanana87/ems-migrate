<?php
session_start();
$_SESSION = [];
if (ini_get('session.use_cookies')) {
  $p = session_get_cookie_params();
  setcookie(session_name(), '', time()-42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

$cookieName = 'ems_token';

// 1) Tự động xác định HTTPS để không lỡ tay gắn Secure trên HTTP
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);

// 2) Liệt kê các path khả dĩ từng dùng
$paths = ['/', '/backend', '/api', '/api.php'];

// 3) Liệt kê domain: host hiện tại và domain mẹ (nếu có)
$host = $_SERVER['HTTP_HOST'] ?? '';
$domains = [null]; // null = HostOnly (không set domain)
if ($host && preg_match('/^[^.]+\.(.+)$/', $host, $m)) {
  $domains[] = $m[1]; // ví dụ: app.example.com -> example.com
}
$domains[] = $host; // chính xác host hiện tại

// 4) Xóa tất cả biến thể (path × domain)
foreach ($paths as $path) {
  foreach ($domains as $dom) {
    setcookie($cookieName, '', [
      'expires'  => time() - 3600,
      'path'     => $path,
      // nếu $dom === null -> HostOnly, KHÔNG truyền 'domain' để khớp loại HostOnly
      'domain'   => $dom ?? '',
      'secure'   => $isHttps,      // chỉ bật Secure nếu thực sự https
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  }
}

header('Location: login.php');
exit;
