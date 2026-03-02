<?php
/**
 * AP Log Receiver
 * Ghi nhận trạng thái ONLINE/OFFLINE từ ESP32 lên database
 */

header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ====== 1) Cấu hình DB ======
$servername = "localhost";
$username   = "device";
$password   = "BtyLX96qZ4nDL!0w";
$dbname     = "central";

// ====== 2) Kết nối DB ======
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'DB connection failed: ' . $conn->connect_error]);
    exit;
}

// ====== 3) Chỉ chấp nhận POST ======
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method']);
    $conn->close();
    exit;
}

// ====== 4) Lấy & lọc dữ liệu ======
$ap_name  = trim($_POST['ap_name'] ?? '');
$location = trim($_POST['location'] ?? '');
$status   = strtoupper(trim($_POST['status'] ?? ''));

if ($ap_name === '' || $location === '' || $status === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing required POST fields']);
    $conn->close();
    exit;
}

// ====== 5) Ghi dữ liệu ======
$stmt = $conn->prepare("
    INSERT INTO ap_logs (ap_name, location, status, logged_at)
    VALUES (?, ?, ?, NOW())
");
if (!$stmt) {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => 'Prepare failed: ' . $conn->error]);
    $conn->close();
    exit;
}

$stmt->bind_param("sss", $ap_name, $location, $status);
$ok = $stmt->execute();

if ($ok) {
    echo json_encode([
        'status'    => 'success',
        'ap_name'   => $ap_name,
        'location'  => $location,
        'status_str'=> $status,
        'logged_at' => date('Y-m-d H:i:s')
    ]);
} else {
    http_response_code(500);
    echo json_encode(['status' => 'error', 'message' => $stmt->error]);
}

$stmt->close();
$conn->close();
?>
