<?php

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

// đổi PHP warning/notice → exception để bắt được trong try/catch
set_error_handler(function($sev,$msg,$file,$line){
  throw new ErrorException($msg, 0, $sev, $file, $line);
});



function getTotalCountFor($eid, PDO $pdo): int {
    // Mold
    $q1 = $pdo->prepare("SELECT COALESCE(SUM(cavities),0) AS total FROM mold WHERE mold_id = :eid AND cycle_time > 20");
    $q1->execute([':eid' => $eid]);
    $mold = (int)($q1->fetchColumn() ?: 0);
    if ($mold > 0) return $mold;

    // Tuft
    $q2 = $pdo->prepare("SELECT COALESCE(SUM(output),0) AS total FROM tuft WHERE device_id = :eid");
    $q2->execute([':eid' => $eid]);
    $tuft = (int)($q2->fetchColumn() ?: 0);
    if ($tuft > 0) return $tuft;

    // Blister
    $q3 = $pdo->prepare("SELECT COALESCE(SUM(cyclecount),0) AS total FROM blister WHERE device_id = :eid");
    $q3->execute([':eid' => $eid]);
    $blister = (int)($q3->fetchColumn() ?: 0);
    return $blister;
}
