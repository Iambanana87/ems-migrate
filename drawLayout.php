<?php
require_once __DIR__ . '/helper/jwt.php';
require_once __DIR__ . '/helper/auth_helper.php';

// function require_user_api(): array {
//   // 1) Ưu tiên cookie (UI flow)
//   if (!empty($_COOKIE['ems_token'])) {
//     $tok = $_COOKIE['ems_token'];
//     try {
//       // Nếu bạn verify được:
//       $payload = jwt_decode($tok, JWT_SECRET); // hoặc jwt_verify(...)
//     } catch (Throwable $e) {
//       // Không verify được (khác secret)? vẫn coi là có token, tuỳ bạn:
//       $payload = ['token' => $tok];
//     }
//     return is_array($payload) ? $payload : (array)$payload;
//   }

//   // 2) Fallback: Authorization: Bearer ...
//   $hdr = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['Authorization'] ?? '';
//   if ($hdr && preg_match('/Bearer\s+(.+)/i', $hdr, $m)) {
//     $tok = trim($m[1]);
//     try { $payload = jwt_decode($tok, JWT_SECRET); }
//     catch (Throwable $e) { $payload = ['token' => $tok]; }
//     return is_array($payload) ? $payload : (array)$payload;
//   }

//   throw new Exception('auth_required', 401);
// }

$token = $_COOKIE['ems_token'] ?? null;
if (!$token) {
  $redirect = $_SERVER['REQUEST_URI']; // ví dụ: /web_develop/ems/drawLayout.php
  header('Location: backend/login.php?redirect=' . urlencode($redirect), true, 302);
  exit;
}

// 2) Hết hạn? -> xoá cookie & yêu cầu login lại
function _jwt_payload_noverify($jwt) {
  $parts = explode('.', $jwt);
  if (count($parts) < 2) return null;
  $b = strtr($parts[1], '-_', '+/');               // base64url -> base64
  $b .= str_repeat('=', (4 - strlen($b) % 4) % 4); // padding
  $obj = json_decode(base64_decode($b), true);
  return is_array($obj) ? $obj : null;
}
$pl = _jwt_payload_noverify($token);
if ($pl && isset($pl['exp']) && time() >= (int)$pl['exp']) {
  setcookie('ems_token','',[
    'expires'=>time()-3600,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']),
    'httponly'=>true,'samesite'=>'Lax'
  ]);
  header('Location: backend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']), true, 302);
  exit;
}

// 3) (tuỳ chọn) refresh hạn cookie để phiên dùng mượt hơn
setcookie('ems_token', $token, [
  'expires'  => $pl['exp'] ?? (time()+3600),
  'path'     => '/',
  'secure'   => !empty($_SERVER['HTTPS']),
  'httponly' => true,
  'samesite' => 'Lax',
]);
?>



<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="image/design.png">
<title>Factory Layout Editor Pro - Modern UI</title>
<style>
* {
    margin: 0;
    padding: 0;
    box-sizing: border-box;
}

body {
    background: linear-gradient(135deg, #f5f7fa 0%, #c3cfe2 100%);
    overflow: hidden;
    font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
}

/* TOOLBOX PANEL */
#toolbox {
    position: fixed;
    left: 0;
    top: 0;
    width: 300px;
    height: 100vh;
    background: white;
    border-right: 1px solid rgba(58, 140, 255, 0.2);
    overflow-y: auto;
    z-index: 1000;
    box-shadow: 4px 0 20px rgba(58, 140, 255, 0.1);
    transition: transform 0.3s ease;
}

#toolbox.collapsed {
    transform: translateX(-300px);
}

#toolbox-toggle {
    position: fixed;
    left: 300px;
    top: 20px;
    width: 40px;
    height: 50px;
    background: rgb(58, 140, 255);
    border: none;
    border-radius: 0 12px 12px 0;
    cursor: pointer;
    z-index: 1001;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
    color: white;
    transition: all 0.3s ease;
    box-shadow: 4px 0 15px rgba(58, 140, 255, 0.3);
}

#toolbox-toggle:hover {
    background: rgb(48, 120, 220);
    transform: translateX(3px);
}

#toolbox.collapsed + #toolbox-toggle {
    left: 0;
}

#toolbox-header {
    background: linear-gradient(135deg, rgb(58, 140, 255), rgb(48, 120, 220));
    color: white;
    padding: 20px;
    font-size: 18px;
    font-weight: 600;
    text-align: center;
    position: sticky;
    top: 0;
    z-index: 10;
    box-shadow: 0 2px 10px rgba(58, 140, 255, 0.2);
}

.tool-category {
    margin: 15px 10px;
}

.category-title {
    color: rgb(58, 140, 255);
    font-size: 13px;
    font-weight: 600;
    margin-bottom: 10px;
    padding: 12px 15px;
    background: rgba(58, 140, 255, 0.05);
    border-left: 4px solid rgb(58, 140, 255);
    border-radius: 0 8px 8px 0;
    cursor: pointer;
    user-select: none;
    transition: all 0.3s ease;
}

.category-title:hover {
    background: rgba(58, 140, 255, 0.1);
    padding-left: 20px;
}

.category-title::before {
    content: '▼ ';
    font-size: 10px;
    margin-right: 5px;
}

.category-title.collapsed::before {
    content: '► ';
}

.category-content {
    max-height: 2000px;
    overflow: hidden;
    transition: max-height 0.3s ease;
}

.category-content.collapsed {
    max-height: 0;
}

.tool-item {
    background: white;
    border: 2px solid rgba(58, 140, 255, 0.2);
    border-radius: 12px;
    padding: 15px;
    margin: 10px 5px;
    cursor: pointer;
    transition: all 0.3s ease;
    color: #333;
    font-size: 12px;
    text-align: center;
    font-weight: 600;
}

.tool-item:hover {
    background: rgba(58, 140, 255, 0.05);
    border-color: rgb(58, 140, 255);
    transform: translateY(-3px);
    box-shadow: 0 8px 20px rgba(58, 140, 255, 0.2);
}

.tool-item:active {
    transform: translateY(-1px);
}

.tool-preview {
    width: 70px;
    height: 70px;
    margin: 0 auto 10px;
    border: 2px solid rgba(58, 140, 255, 0.2);
    border-radius: 10px;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    background: linear-gradient(135deg, rgba(58, 140, 255, 0.05), rgba(58, 140, 255, 0.1));
    transition: all 0.3s ease;
}

.tool-item:hover .tool-preview {
    background: linear-gradient(135deg, rgba(58, 140, 255, 0.1), rgba(58, 140, 255, 0.15));
    transform: scale(1.05);
}

/* CANVAS CONTAINER */
#canvas-container {
    position: fixed;
    left: 300px;
    top: 0;
    right: 0;
    height: 100vh;
    overflow: auto;
    background: linear-gradient(135deg, #f5f7fa 0%, #e8ecf1 100%);
    transition: left 0.3s ease;
}

#toolbox.collapsed ~ #canvas-container {
    left: 0;
}

#factory-layout {
    width: 20000px;
    height: 10000px;
    background: white;
    position: relative;
    border: none;
    box-shadow: 0 0 50px rgba(58, 140, 255, 0.1);
    cursor: crosshair;
    transform-origin: 0 0;
    transition: transform 0.1s;
    --zoom:1; --zoomInv:1; --browserInv:1;
}

/* ZOOM CONTROLS */
#zoom-controls {
    position: fixed;
    right: 20px;
    bottom: 80px;
    background: white;
    border: 1px solid rgba(58, 140, 255, 0.2);
    border-radius: 16px;
    padding: 12px;
    z-index: 1000;
    display: flex;
    flex-direction: column;
    gap: 8px;
    box-shadow: 0 8px 30px rgba(58, 140, 255, 0.15);
}

.zoom-btn {
    width: 45px;
    height: 45px;
    background: linear-gradient(135deg, rgb(58, 140, 255), rgb(48, 120, 220));
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-size: 20px;
    font-weight: bold;
    transition: all 0.3s ease;
    box-shadow: 0 4px 12px rgba(58, 140, 255, 0.3);
}

.zoom-btn:hover {
    background: linear-gradient(135deg, rgb(48, 120, 220), rgb(38, 100, 200));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(58, 140, 255, 0.4);
}

.zoom-btn:active {
    transform: translateY(0);
}

#zoom-level {
    text-align: center;
    color: rgb(58, 140, 255);
    font-size: 13px;
    font-weight: 700;
    margin: 5px 0;
}

/* CONTROL PANEL */
#control-panel {
  position: fixed;
  right: 20px;
  top: 20px;
  background: white;
  border: 1px solid rgba(58, 140, 255, 0.2);
  border-radius: 16px;
  padding: 20px;
  z-index: 1000;
  box-shadow: 0 8px 30px rgba(58, 140, 255, 0.15);
  min-width: 200px;
  max-height: 80vh;
  overflow-y: auto;
  transition: transform 0.35s ease, opacity 0.35s ease;
}

/* Hiệu ứng ẩn */
#control-panel.hidden {
  transform: translateY(-10px);
  opacity: 0;
  pointer-events: none;
}

/* Nút toggle hình bánh răng */
#panel-toggle-btn {
  position: fixed;
  right: 25px;
  top: 25px;
  z-index: 1100;
  width: 42px;
  height: 42px;
  border-radius: 50%;
  border: none;
  cursor: pointer;
  background: linear-gradient(135deg, rgb(58, 140, 255), rgb(48, 120, 220));
  color: white;
  font-size: 18px;
  font-weight: bold;
  box-shadow: 0 4px 12px rgba(58, 140, 255, 0.4);
  transition: transform 0.3s ease, box-shadow 0.3s ease;
}
#panel-toggle-btn:hover {
  transform: rotate(90deg) scale(1.1);
  box-shadow: 0 6px 18px rgba(58, 140, 255, 0.6);
}

/* Responsive giữ nguyên màu & hiệu ứng như trước */
@media (max-width: 1023.98px){
  #control-panel{
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px;
    max-width: 520px;
  }
}
@media (max-width: 767.98px){
  #control-panel{
    left: 10px;
    right: 10px;
    bottom: 10px;
    top: auto;
    display: flex;
    gap: 8px;
    overflow-x: auto;
    border-radius: 12px;
    padding: 10px;
  }
  #panel-toggle-btn {
    right: 15px;
    bottom: 80px;
    top: auto;
  }
}


.control-btn {
    display: block;
    width: 100%;
    padding: 12px 20px;
    margin: 6px 0;
    background: linear-gradient(135deg, rgb(58, 140, 255), rgb(48, 120, 220));
    color: white;
    border: none;
    border-radius: 10px;
    cursor: pointer;
    font-weight: 600;
    transition: all 0.3s ease;
    font-size: 13px;
    box-shadow: 0 4px 12px rgba(58, 140, 255, 0.3);
}

.control-btn:hover {
    background: linear-gradient(135deg, rgb(48, 120, 220), rgb(38, 100, 200));
    transform: translateY(-2px);
    box-shadow: 0 6px 20px rgba(58, 140, 255, 0.4);
}

.control-btn:active {
    transform: translateY(0);
}

.control-btn.danger {
    background: linear-gradient(135deg, #ff4757, #e84118);
    box-shadow: 0 4px 12px rgba(255, 71, 87, 0.3);
}

.control-btn.danger:hover {
    background: linear-gradient(135deg, #e84118, #c23616);
    box-shadow: 0 6px 20px rgba(255, 71, 87, 0.4);
}

/* PROPERTIES PANEL */
#properties-panel {
  position: fixed;
  right: 20px;
  bottom: 20px;                /* 🔽 chuyển từ top sang bottom */
  background: white;
  border: 1px solid rgba(58, 140, 255, 0.2);
  border-radius: 16px;
  padding: 20px;
  z-index: 1000;
  display: none;
  min-width: 220px;
  box-shadow: 0 8px 30px rgba(58, 140, 255, 0.15);
  transition: transform 0.3s ease, opacity 0.3s ease;
}

/* Khi panel hiển thị */
#properties-panel.visible {
  display: block;
  transform: translateY(0);
  opacity: 1;
}

/* Nếu muốn hiệu ứng trượt mượt */
#properties-panel.hidden {
  transform: translateY(20px);
  opacity: 0;
  pointer-events: none;
}


#properties-panel.visible {
    display: block;
}

.property-group {
    margin: 12px 0;
}

.property-label {
    color: rgb(58, 140, 255);
    font-size: 12px;
    font-weight: 600;
    margin-bottom: 6px;
}

.property-input {
    width: 100%;
    padding: 10px 12px;
    background: rgba(58, 140, 255, 0.05);
    border: 1px solid rgba(58, 140, 255, 0.2);
    border-radius: 8px;
    color: #333;
    font-size: 13px;
    transition: all 0.3s ease;
}

.property-input:focus {
    outline: none;
    border-color: rgb(58, 140, 255);
    background: white;
    box-shadow: 0 0 0 3px rgba(58, 140, 255, 0.1);
}

.property-btn {
    padding: 8px 16px;
    margin: 3px;
    background: linear-gradient(135deg, rgb(58, 140, 255), rgb(48, 120, 220));
    color: white;
    border: none;
    border-radius: 8px;
    cursor: pointer;
    font-size: 12px;
    font-weight: 600;
    transition: all 0.3s ease;
    box-shadow: 0 2px 8px rgba(58, 140, 255, 0.3);
}

.property-btn:hover {
    background: linear-gradient(135deg, rgb(48, 120, 220), rgb(38, 100, 200));
    transform: translateY(-1px);
}

/* MACHINE STYLES */
.machine {
    position: absolute;
    border: 3px solid rgb(58, 140, 255);
    background: white;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    font-weight: 600;
    box-shadow: 0 4px 15px rgba(58, 140, 255, 0.2);
    transition: all 0.3s ease;
    cursor: move;
    border-radius: 8px;
    border-width: calc(3px * var(--zoomInv) * var(--browserInv));
}

.machine .icon {
  font-size: clamp(16px,
    calc(var(--scale,1) * 28px * var(--zoomInv) * var(--browserInv)),
    40px);
}

.machine .text {
    text-align: center;
    font-size: calc(var(--scale, 1) * 11px);
    color: #333;
    font-size: clamp(10px,
        calc(var(--scale,1) * 11px * var(--zoomInv) * var(--browserInv)),
        24px);
    line-height: 1.2;
}

.label {
    font-size: clamp(10px, calc(13px * var(--zoomInv) * var(--browserInv)), 22px);
    color: rgb(58, 140, 255);
}

.machine:hover {
    box-shadow: 0 8px 25px rgba(58, 140, 255, 0.3);
    z-index: 10;
    transform: translateY(-2px);
}

.machine.selected {
    border: 3px solid rgb(58, 140, 255);
    box-shadow: 0 0 0 4px rgba(58, 140, 255, 0.2), 0 8px 30px rgba(58, 140, 255, 0.4);
    z-index: 100;
}

.machine.dragging {
    opacity: 0.8;
    z-index: 1000;
}

/* RESIZE HANDLES */
.resize-handle {
    position: absolute;
    width:  calc(14px * var(--zoomInv) * var(--browserInv));
    height: calc(14px * var(--zoomInv) * var(--browserInv));
    background: white;
    border: 2px solid rgb(58, 140, 255);
    border-radius: 50%;
    display: none;
    z-index: 10;
    box-shadow: 0 2px 8px rgba(58, 140, 255, 0.3);
    transition: all 0.2s ease;
}

.resize-handle:hover {
    background: rgb(58, 140, 255);
    transform: scale(1.3);
}

.machine.selected .resize-handle {
    display: block;
}

.resize-handle.nw { top: -7px; left: -7px; cursor: nw-resize; }
.resize-handle.ne { top: -7px; right: -7px; cursor: ne-resize; }
.resize-handle.sw { bottom: -7px; left: -7px; cursor: sw-resize; }
.resize-handle.se { bottom: -7px; right: -7px; cursor: se-resize; }
.resize-handle.n { top: -7px; left: 50%; transform: translateX(-50%); cursor: n-resize; }
.resize-handle.s { bottom: -7px; left: 50%; transform: translateX(-50%); cursor: s-resize; }
.resize-handle.w { top: 50%; left: -7px; transform: translateY(-50%); cursor: w-resize; }
.resize-handle.e { top: 50%; right: -7px; transform: translateY(-50%); cursor: e-resize; }

/* ROTATE HANDLE */
.rotate-handle {
    position: absolute;
    left: 50%;
    transform: translateX(-50%);
    width:  calc(28px * var(--zoomInv) * var(--browserInv));
    height: calc(28px * var(--zoomInv) * var(--browserInv));
    top:    calc(-35px * var(--zoomInv) * var(--browserInv));
    background: linear-gradient(135deg, rgb(58, 140, 255), rgb(48, 120, 220));
    border: 2px solid white;
    border-radius: 50%;
    display: none;
    cursor: grab;
    z-index: 10;
    box-shadow: 0 4px 12px rgba(58, 140, 255, 0.4);
    transition: all 0.2s ease;
}

.rotate-handle:hover {
    transform: translateX(-50%) scale(1.2);
}

.rotate-handle:active {
    cursor: grabbing;
}

.machine.selected .rotate-handle {
    display: flex;
    align-items: center;
    justify-content: center;
    color: white;
    font-size: 16px;
}

.rotate-handle::before {
    content: '⟲';
}

/* DELETE BUTTON */
.delete-btn {
    position: absolute;
    width:  calc(28px * var(--zoomInv) * var(--browserInv));
    height: calc(28px * var(--zoomInv) * var(--browserInv));
    top:    calc(-14px * var(--zoomInv) * var(--browserInv));
    right:  calc(-14px * var(--zoomInv) * var(--browserInv));
    background: linear-gradient(135deg, #ff4757, #e84118);
    color: white;
    border: 2px solid white;
    border-radius: 50%;
    display: none;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    font-size: 16px;
    font-weight: bold;
    z-index: 10;
    box-shadow: 0 4px 12px rgba(255, 71, 87, 0.4);
    transition: all 0.2s ease;
}

.machine.selected .delete-btn {
    display: flex;
}

.delete-btn:hover {
    background: linear-gradient(135deg, #e84118, #c23616);
    transform: scale(1.2);
}

/* MACHINE TYPES */
.tank {
    border: 3px solid rgb(58, 140, 255);
    background: linear-gradient(135deg, rgba(58, 140, 255, 0.05), rgba(58, 140, 255, 0.1));
}

.equipment {
    border: 3px solid rgb(58, 140, 255);
    background: linear-gradient(135deg, rgba(58, 140, 255, 0.08), rgba(58, 140, 255, 0.15));
}

.workstation {
    border: 3px solid rgb(58, 140, 255);
    background: linear-gradient(135deg, rgba(58, 140, 255, 0.06), rgba(58, 140, 255, 0.12));
}

.at1900 {
    background: linear-gradient(135deg, rgba(58, 140, 255, 0.1), rgba(58, 140, 255, 0.18));
    border: 3px solid rgb(58, 140, 255);
}

.robot-arm {
    border: 3px solid rgb(58, 140, 255);
    background: linear-gradient(135deg, rgba(58, 140, 255, 0.07), rgba(58, 140, 255, 0.14));
}

.agv {
    border: 3px solid rgb(58, 140, 255);
    background: linear-gradient(135deg, rgba(58, 140, 255, 0.09), rgba(58, 140, 255, 0.16));
    border-radius: 12px;
}

.assembly-line {
    border: 3px solid rgb(58, 140, 255);
    background: linear-gradient(135deg, rgba(58, 140, 255, 0.05), rgba(58, 140, 255, 0.11));
}

.cnc-machine {
    border: 3px solid rgb(58, 140, 255);
    background: linear-gradient(135deg, rgba(58, 140, 255, 0.08), rgba(58, 140, 255, 0.15));
}

/* MOLD – xanh dương (đậm hơn) */
.mold {
    border: 3px solid #3A8CFF;
    background: linear-gradient(
        135deg,
        rgba(58, 140, 255, 0.18),
        rgba(58, 140, 255, 0.28)
    );
}

/* TUFTING – xanh lá (đậm hơn) */
.tufting {
    border: 3px solid #10B981;
    background: linear-gradient(
        135deg,
        rgba(16, 185, 129, 0.18),
        rgba(16, 185, 129, 0.28)
    );
}

/* BLISTER – cam (đậm hơn) */
.blister {
    border: 3px solid #F59E0B;
    background: linear-gradient(
        135deg,
        rgba(245, 158, 11, 0.18),
        rgba(245, 158, 11, 0.28)
    );
}

.conveyor {
    background: linear-gradient(180deg, rgb(58, 140, 255), rgb(48, 120, 220));
    border: 2px solid rgb(38, 100, 200);
}

.pipe {
    background: linear-gradient(180deg, rgb(58, 140, 255), rgb(48, 120, 220));
    border: 2px solid rgb(38, 100, 200);
}

.wall {
    background: #333;
    
}

.office {
    border: 3px solid rgb(58, 140, 255);
    background: linear-gradient(135deg, rgba(58, 140, 255, 0.04), rgba(58, 140, 255, 0.09));
}

.stairs {
    background: repeating-linear-gradient(45deg, rgba(58, 140, 255, 0.2), rgba(58, 140, 255, 0.2) 12px, rgba(58, 140, 255, 0.1) 12px, rgba(58, 140, 255, 0.1) 24px);
    border: 3px solid rgb(58, 140, 255);
}

.label {
    background: white;
    border: 2px solid rgb(58, 140, 255);
    color: rgb(58, 140, 255);
    padding: 8px 14px;
    border-radius: 8px;
    box-shadow: 0 4px 12px rgba(58, 140, 255, 0.2);
}

/* COORDINATES DISPLAY */
#coords-display {
    position: fixed;
    left: 320px;
    bottom: 20px;
    background: white;
    color: rgb(58, 140, 255);
    padding: 12px 24px;
    border-radius: 12px;
    font-family: 'Courier New', monospace;
    font-size: 13px;
    border: 1px solid rgba(58, 140, 255, 0.2);
    z-index: 1000;
    transition: left 0.3s;
    box-shadow: 0 4px 15px rgba(58, 140, 255, 0.15);
    font-weight: 600;
}

#toolbox.collapsed ~ #canvas-container #coords-display {
    left: 20px;
}

/* GRID */
#factory-layout.show-grid {
    background-image: 
        repeating-linear-gradient(0deg, transparent, transparent 49px, rgba(58, 140, 255, 0.1) 49px, rgba(58, 140, 255, 0.1) 50px),
        repeating-linear-gradient(90deg, transparent, transparent 49px, rgba(58, 140, 255, 0.1) 49px, rgba(58, 140, 255, 0.1) 50px);
    background-size: 50px 50px;
}

/* CONTEXT MENU */
#context-menu {
    position: fixed;
    background: white;
    border: 1px solid rgba(58, 140, 255, 0.2);
    border-radius: 12px;
    padding: 8px 0;
    display: none;
    z-index: 2000;
    box-shadow: 0 8px 30px rgba(58, 140, 255, 0.2);
}

#context-menu.visible {
    display: block;
}

.context-item {
    padding: 12px 24px;
    color: #333;
    cursor: pointer;
    font-size: 13px;
    font-weight: 500;
    transition: all 0.2s ease;
}

.context-item:hover {
    background: rgba(58, 140, 255, 0.1);
    color: rgb(58, 140, 255);
}

.context-divider {
    height: 1px;
    background: rgba(58, 140, 255, 0.1);
    margin: 6px 0;
}

/* VIEW MODE */
body.view-mode .machine {
    cursor: default;
}

body.view-mode .resize-handle,
body.view-mode .rotate-handle,
body.view-mode .delete-btn {
    display: none !important;
}

/* SELECTION BOX */
#selection-box {
    position: absolute;
    border: 2px dashed rgb(58, 140, 255);
    background: rgba(58, 140, 255, 0.1);
    pointer-events: none;
    z-index: 9999;
    display: none;
}

#selection-box.active {
    display: block;
}

/* SCROLLBAR STYLING */
::-webkit-scrollbar {
    width: 10px;
    height: 10px;
}

::-webkit-scrollbar-track {
    background: rgba(58, 140, 255, 0.05);
}

::-webkit-scrollbar-thumb {
    background: rgba(58, 140, 255, 0.3);
    border-radius: 5px;
}

::-webkit-scrollbar-thumb:hover {
    background: rgba(58, 140, 255, 0.5);
}
/* MODAL */
.modal { position: fixed; inset:0; display:none; z-index:3000; }
.modal.show { display:block; }
.modal-backdrop { position:absolute; inset:0; background:rgba(0,0,0,.25); backdrop-filter: blur(2px); }
.modal-card {
  position:absolute; top:50%; left:50%; transform:translate(-50%,-50%);
  width: 560px; max-width: calc(100vw - 40px);
  background:#fff; border-radius:16px; box-shadow:0 20px 60px rgba(58,140,255,.25);
  border:1px solid rgba(58,140,255,.2); overflow:hidden;
}
.modal-header { display:flex; justify-content:space-between; align-items:center; padding:16px 18px; background:linear-gradient(135deg,#3A8CFF,#3078DC); color:#fff; }
.modal-title { font-size:16px; font-weight:700; }
.modal-subtitle { font-size:12px; opacity:.9; margin-top:2px; }
.modal-close { border:none; background:rgba(255,255,255,.2); color:#fff; width:32px; height:32px; border-radius:8px; font-size:18px; cursor:pointer; }
.modal-body { padding:16px 18px; max-height:60vh; overflow:auto; }
.kv { display:flex; justify-content:space-between; padding:8px 0; border-bottom:1px dashed rgba(58,140,255,.15); font-size:13px; }
.kv span { color:#3A8CFF; }
.kv b { color:#333; font-weight:700; }
.badge {
  padding:2px 8px; border-radius:999px; font-weight:700; font-size:12px; border:1px solid transparent;
}
.badge.normal { background:#e9f2ff; color:#2962ff; border-color:#bcd3ff; }
.badge.breached { background:#ffecec; color:#d63031; border-color:#ffb3b3; }
.badge.disconnected { background:#f0f1f2; color:#7f8c8d; border-color:#dfe4ea; }
/* ===== STATUS COLORS (override màu mặc định) ===== */
.machine.status-normal {
  border-color: #10B981 !important; /* xanh */
  background: linear-gradient(135deg, rgba(16,185,129,.18), rgba(16,185,129,.28)) !important;
}
.machine.status-breached,
.machine.status-break {
  border-color: #EF4444 !important; /* đỏ */
  background: linear-gradient(135deg, rgba(239,68,68,.18), rgba(239,68,68,.28)) !important;
}
.machine.status-disconnected {
  border-color: #9CA3AF !important; /* xám */
  background: linear-gradient(135deg, rgba(156,163,175,.18), rgba(156,163,175,.28)) !important;
}
.machine.status-unknown {
  border-color: #A3A3A3 !important;
  background: linear-gradient(135deg, rgba(163,163,163,.12), rgba(163,163,163,.2)) !important;
}

</style>
</head>
<body>

    <!-- TOOLBOX -->
    <div id="toolbox">
    <div id="toolbox-header">🔧 DESIGN TOOLS</div>

    <div class="tool-category">
        <div class="category-title" onclick="toggleCategory(this)">🏭 Basic Machines</div>
        <div class="category-content">
        <div class="tool-item" data-type="tank" data-width="80" data-height="100">
            <div class="tool-preview tank">🛢️</div>
            Tank
        </div>
        <div class="tool-item" data-type="equipment" data-width="100" data-height="80">
            <div class="tool-preview equipment">⚙️</div>
            Equipment
        </div>
        <div class="tool-item" data-type="workstation" data-width="180" data-height="120">
            <div class="tool-preview workstation">👷</div>
            Workstation
        </div>
        <div class="tool-item" data-type="at1900" data-width="100" data-height="80">
            <div class="tool-preview at1900">🔧</div>
            AT1900
        </div>
        </div>
    </div>

    <div class="tool-category">
        <div class="category-title" onclick="toggleCategory(this)">🤖 Advanced Machines</div>
        <div class="category-content">
        <div class="tool-item" data-type="robot-arm" data-width="120" data-height="120">
            <div class="tool-preview robot-arm">🦾</div>
            Robot Arm
        </div>
        <div class="tool-item" data-type="cnc-machine" data-width="150" data-height="120">
            <div class="tool-preview cnc-machine">🔩</div>
            CNC Machine
        </div>
        <div class="tool-item" data-type="assembly-line" data-width="300" data-height="100">
            <div class="tool-preview assembly-line">⚡</div>
            Assembly Line
        </div>
        <div class="tool-item" data-type="agv" data-width="80" data-height="120">
            <div class="tool-preview agv">🚛</div>
            AGV Robot
        </div>
        <div class="tool-item" data-type="ap" data-width="120" data-height="80">
            <div class="tool-preview mold">📶</div>
            AP 
        </div>
        <div class="tool-item" data-type="mold" data-width="150" data-height="120">
            <div class="tool-preview mold">🛠️</div>
            Mold Machine
        </div>
        <div class="tool-item" data-type="tufting" data-width="150" data-height="120">
            <div class="tool-preview tufting">🧵</div>
            Tufting Machine
        </div>
        <div class="tool-item" data-type="blister" data-width="150" data-height="120">
            <div class="tool-preview blister">📦</div>
            Blister Machine
        </div>
        </div>
    </div>

    <div class="tool-category">
        <div class="category-title" onclick="toggleCategory(this)">🏢 Areas & Structures</div>
        <div class="category-content">
        <div class="tool-item" data-type="office" data-width="200" data-height="150">
            <div class="tool-preview office">🏢</div>
            Office
        </div>
        <div class="tool-item" data-type="stairs" data-width="120" data-height="150">
            <div class="tool-preview stairs">🪜</div>
            Stairs
        </div>
        </div>
    </div>

    <div class="tool-category">
        <div class="category-title" onclick="toggleCategory(this)">🔗 Connections</div>
        <div class="category-content">
        <div class="tool-item" data-type="conveyor" data-width="200" data-height="12">
            <div class="tool-preview conveyor" style="width:60px;height:10px;">→</div>
            Conveyor
        </div>
        <div class="tool-item" data-type="pipe" data-width="200" data-height="8">
            <div class="tool-preview pipe" style="width:60px;height:8px;">━</div>
            Pipe
        </div>
        <div class="tool-item" data-type="wall" data-width="200" data-height="8">
            <div class="tool-preview wall" style="width:60px;height:8px;">▬</div>
            Wall
        </div>
        </div>
    </div>

    <div class="tool-category">
        <div class="category-title" onclick="toggleCategory(this)">📝 Labels & Text</div>
        <div class="category-content">
        <div class="tool-item" data-type="label" data-width="120" data-height="40">
            <div class="tool-preview label" style="font-size:12px;">ABC</div>
            Text Label
        </div>
        </div>
    </div>
    </div>

    <button id="toolbox-toggle" onclick="toggleToolbox()">◀</button>

    <!-- CONTROL PANEL -->
    <div id="control-panel">
    <button class="control-btn" onclick="toggleMode()">Mode: <span id="mode-status">Edit</span></button>
    <button class="control-btn" onclick="toggleGrid()">📐 Grid: <span id="grid-status">ON</span></button>
    <button class="control-btn" onclick="toggleSnap()">🧲 Snap: <span id="snap-status">ON</span></button>
    <button class="control-btn" onclick="copyElement()">📋 Copy</button>
    <button class="control-btn" onclick="pasteElement()">📄 Paste</button>
    <button class="control-btn" onclick="refreshData()">🔄 Refresh</button>
    <button class="control-btn" onclick="exportLayout()">💾 Export JSON</button>
    <button class="control-btn" onclick="importLayout()">📂 Import JSON</button>
    <button class="control-btn" onclick="openLayoutPicker()">📚 Open from DB</button>
    <button class="control-btn" onclick="saveLayoutToDB()">💾 Save to DB</button>
    <button class="control-btn danger" onclick="clearAll()">🗑️ Clear all</button>
    </div>

    <!-- Nút thu gọn/hiện panel -->
    <button id="panel-toggle-btn" title="Ẩn/Hiện bảng điều khiển">⚙️</button>

    <!-- LAYOUT PICKER MODAL -->
    <div id="layout-modal" class="modal">
    <div class="modal-backdrop" onclick="closeLayoutModal()"></div>
    <div class="modal-card" style="width:720px;">
        <div class="modal-header">
        <div>
            <div class="modal-title">Layout Library</div>
            <div class="modal-subtitle">Mở layout từ cơ sở dữ liệu</div>
        </div>
        <button class="modal-close" onclick="closeLayoutModal()">×</button>
        </div>
        <div class="modal-body">
        <div style="display:flex; gap:8px; margin-bottom:10px;">
            <input id="layout-search" class="property-input" placeholder="Tìm theo tên/mô tả..." oninput="debouncedSearchLayouts()" />
            <button class="property-btn" onclick="reloadLayouts()">Tìm</button>
        </div>
        <div id="layout-list"></div>
        </div>
    </div>
    </div>

    <!-- PROPERTIES PANEL -->
    <div id="properties-panel">
    <div style="color:rgb(58, 140, 255); font-weight:bold; margin-bottom:15px; text-align:center; font-size:14px;">PROPERTIES</div>
    <div class="property-group">
        <div class="property-label">Position X:</div>
        <input type="number" id="prop-x" class="property-input" onchange="updateProperty('x')">
    </div>
    <div class="property-group">
        <div class="property-label">Position Y:</div>
        <input type="number" id="prop-y" class="property-input" onchange="updateProperty('y')">
    </div>
    <div class="property-group">
        <div class="property-label">Width:</div>
        <input type="number" id="prop-width" class="property-input" onchange="updateProperty('width')">
    </div>
    <div class="property-group">
        <div class="property-label">Height:</div>
        <input type="number" id="prop-height" class="property-input" onchange="updateProperty('height')">
    </div>
    <div class="property-group">
        <div class="property-label">Rotation (deg):</div>
        <input type="number" id="prop-rotation" class="property-input" onchange="updateProperty('rotation')" step="15">
    </div>
    <div class="property-group">
        <div class="property-label">Process:</div>
        <select id="prop-process" class="property-input" onchange="updateProperty('process')">
        <option value="mold">Mold</option>
        <option value="tuft">Tufting</option>
        <option value="blister">Blister</option>
        </select>
    </div>
    <div class="property-group">
        <div class="property-label">Device ID:</div>
        <input type="text" id="prop-device-id" class="property-input" onchange="updateProperty('device-id')">
    </div>
    <div class="property-group" style="text-align:center;">
        <button class="property-btn" onclick="flipHorizontal()">↔️ Flip horizontal</button>
        <button class="property-btn" onclick="flipVertical()">↕️ Flip vertical</button>
    </div>
    </div>

    <!-- ZOOM CONTROLS -->
    <div id="zoom-controls">
    <button class="zoom-btn" onclick="zoomIn()">+</button>
    <div id="zoom-level">100%</div>
    <button class="zoom-btn" onclick="zoomOut()">−</button>
    <button class="zoom-btn" onclick="resetZoom()" style="font-size:16px;">⊙</button>
    </div>

    <!-- COORDINATES DISPLAY -->
    <div id="coords-display">X: 0, Y: 0</div>

    <!-- CONTEXT MENU -->
    <div id="context-menu">
    <div class="context-item" onclick="copyElement()">📋 Copy</div>
    <div class="context-item" onclick="pasteElement()">📄 Paste</div>
    <div class="context-divider"></div>
    <div class="context-item" onclick="duplicateElement()">📑 Duplicate</div>
    <div class="context-item" onclick="flipHorizontal()">↔️ Flip horizontal</div>
    <div class="context-item" onclick="flipVertical()">↕️ Flip vertical</div>
    <div class="context-item" onclick="rotateElement(90)">⟲ Rotate 90°</div>
    <div class="context-divider"></div>
    <div class="context-item" onclick="deleteSelected()" style="color:#ff4757;">🗑️ Delete</div>
    </div>

    <!-- CANVAS -->
    <div id="canvas-container">
    <div id="factory-layout">
        <div id="selection-box"></div>
    </div>
    </div>

    <input type="file" id="import-file" accept=".json" style="display:none" onchange="handleImport(event)">

    <!-- DEVICE DETAIL POPUP -->
    <div id="device-modal" class="modal">
    <div class="modal-backdrop" onclick="closeDeviceModal()"></div>
    <div class="modal-card">
        <div class="modal-header">
        <div>
            <div id="dm-title" class="modal-title">Device</div>
            <div id="dm-subtitle" class="modal-subtitle"></div>
        </div>
        <button class="modal-close" onclick="closeDeviceModal()">×</button>
        </div>
        <div class="modal-body">
        <div class="kv"><span>Mold ID</span><b id="dm-mold_id">—</b></div>
        <div class="kv"><span>Family</span><b id="dm-family">—</b></div>
        <div class="kv"><span>Process</span><b id="dm-process">—</b></div>
        <div class="kv"><span>Mold cavity</span><b id="dm-mold_cavity">—</b></div>
        <div class="kv"><span>Actual cavity</span><b id="dm-actual_cavity">—</b></div>
        <div class="kv"><span>Capacity / hr</span><b id="dm-capacity_per_hr">—</b></div>
        <div class="kv"><span>Efficiency</span><b id="dm-efficiency">—</b></div>
        <div class="kv"><span>Efficiency lower limit</span><b id="dm-eff_ll">—</b></div>
        <div class="kv"><span>Current cycle</span><b id="dm-current_cycle">—</b></div>
        <div class="kv"><span>Output</span><b id="dm-output">—</b></div>
        <div class="kv"><span>Cycle count</span><b id="dm-cyclecount">—</b></div>
        <div class="kv"><span>Target</span><b id="dm-target">—</b></div>
        <div class="kv"><span>Brush / cycle</span><b id="dm-bush_per_cycle">—</b></div>
        <div class="kv"><span>Hole / brush</span><b id="dm-hole_per_brush">—</b></div>
        <div class="kv"><span>Upper limit</span><b id="dm-upper_limit">—</b></div>
        <div class="kv"><span>Lower limit</span><b id="dm-lower_limit">—</b></div>
        <div class="kv"><span>Total lost pcs</span><b id="dm-total_lost_pcs">—</b></div>
        <div class="kv"><span>Lost time</span><b id="dm-lost_time">—</b></div>
        <div class="kv"><span>Status</span><b id="dm-status" class="badge">—</b></div>
        <div class="kv"><span>Last updated</span><b id="dm-last_updated">—</b></div>
        </div>
    </div>
    </div>


<script>
    const layout = document.getElementById('factory-layout');
    const coordsDisplay = document.getElementById('coords-display');
    const propertiesPanel = document.getElementById('properties-panel');
    const contextMenu = document.getElementById('context-menu');
    const toolbox = document.getElementById('toolbox');
    const PROCESS_TYPES = new Set(['mold','tuft','tufting','blister','ap']);
    const isProcessEl = (el) => !!el && PROCESS_TYPES.has((el.dataset.process || '').toLowerCase());

    const API_URL = 'api.php';
    const LAYOUT_API = 'layout_api.php';

    let selectedElements = [];
    let isDragging = false;
    let isResizing = false;
    let isRotating = false;
    let dragOffsets = [];
    let resizeHandle = null;
    let snapToGrid = true;
    let showGrid = true;
    let elements = [];
    let elementCounter = 0;
    let clipboard = [];
    let currentZoom = 0.7;
    let rotationStart = 0;
    let elementCenter = { x: 0, y: 0 };
    let editMode = true;
    let isSelecting = false;
    let selectionBox = null;
    let selectionStart = { x: 0, y: 0 };
    const MAX_HISTORY = 50;
    let historyStack = [];
    let redoStack = [];
    let currentLayoutId = null;
    let currentLayoutName = null;
    const SNAP_THRESHOLD = 50;
    // ===== Browser zoom detection =====
    const base = {
    dpr: window.devicePixelRatio || 1,
    // đo 1 inch (96 CSS px) để fallback nếu cần – vẫn dùng DPR là chính
    pxPerInch: (() => {
        const test = document.createElement('div');
        test.style.width = '1in'; test.style.visibility = 'hidden';
        document.body.appendChild(test);
        const v = test.offsetWidth || 96;
        document.body.removeChild(test);
        return v;
    })()
    };
    // check localStorage for auth tokens
    function getAuthHeaders(base = {'Content-Type':'application/json'}) {
        const keys = ['ems_token','ems_jwt','jwt','access_token','token']; // <-- thêm ems_token
        let t = null;
        for (const k of keys) { const v = localStorage.getItem(k); if (v) { t = v; break; } }
        return t ? { ...base, 'Authorization': `Bearer ${t}` } : base;
    }
    function getLayoutData() {
        return elements.map(item => ({
            type: item.type,
            x: parseInt(item.el.style.left),
            y: parseInt(item.el.style.top),
            width: parseInt(item.el.style.width),
            height: parseInt(item.el.style.height),
            origWidth: parseFloat(item.el.dataset.origWidth),
            origHeight: parseFloat(item.el.dataset.origHeight),
            text: item.el.querySelector('.text')
            ? item.el.querySelector('.text').textContent
            : (item.type === 'label' ? item.el.textContent : ''),
            rotation: parseFloat(item.el.dataset.rotation) || 0,
            flipH: parseFloat(item.el.dataset.flipH) || 1,
            flipV: parseFloat(item.el.dataset.flipV) || 1,
            process: item.el.dataset.process || '',
            deviceId: item.el.dataset.deviceId || ''
    }));
    }

    function restoreLayout(data) {
        clearAll(true); // KHÔNG gọi saveHistory ở đây
        const PROCESS_UI = ['mold','tuft','tufting','blister'];

        (data || []).forEach(it => {
            const typeStr = String(it.type || '').toLowerCase();
            const fixedProcess =
            (it.process && PROCESS_UI.includes(String(it.process).toLowerCase()))
                ? String(it.process).toLowerCase()
                : (PROCESS_UI.includes(typeStr) ? typeStr : '');

            // wall: không cần process
            const processToUse = (typeStr === 'wall') ? '' : fixedProcess;

            const el = createElement(
            it.type, it.x, it.y, it.width, it.height,
            it.text, it.rotation, it.flipH, it.flipV,
            processToUse, it.deviceId || '',
            { snapOnPlace: false }
            );

            if (el) {
            if (!el.dataset.deviceId) {
                const m = String(it.text || '').trim().match(/#\s*([A-Za-z0-9_-]+)/);
                if (m) el.dataset.deviceId = m[1];
            }
            el.dataset.origWidth  = it.origWidth;
            el.dataset.origHeight = it.origHeight;
            updateScale(el);
            }
        });

        propertiesPanel.classList.remove('visible');
        deselectAll();
    }

    // hàm lưu trạng thái hiện tại vào history
    function saveHistory(action = 'unknown') {
        const snapshot = JSON.stringify(getLayoutData());
        historyStack.push({ action, snapshot });
        if (historyStack.length > MAX_HISTORY) historyStack.shift();
        redoStack = [];
    }
    //hàm undo
    function undo() {
    if (historyStack.length < 2) return; // phải có ít nhất 2 trạng thái
        const current = historyStack.pop();
        redoStack.push(current);
        const prev = historyStack[historyStack.length - 1];
        restoreLayout(JSON.parse(prev.snapshot));
    }

    function redo() {
        if (redoStack.length === 0) return;
        const next = redoStack.pop();
        historyStack.push(next);
        restoreLayout(JSON.parse(next.snapshot));
    }
    function setBrowserInv(v){
        // Giới hạn để tránh nhảy quá đà khi trình duyệt báo sai
        const inv = Math.max(0.5, Math.min(3, 1 / v));
        layout.style.setProperty('--browserInv', inv.toFixed(4));
    }

    function computeBrowserZoom(){
        // 1) visualViewport (nếu có): scale >=1 khi zoom-in, <1 khi zoom-out
        if (window.visualViewport && typeof window.visualViewport.scale === 'number'){
            return window.visualViewport.scale;
        }
        // 2) Fallback theo DPR (so với gốc lúc load)
        //   VD: zoom 80% => DPR giảm ~0.8 => scale≈currentDPR/baseDPR
        const dpr = window.devicePixelRatio || 1;
        return dpr / base.dpr;
    }

    // gọi khi load & khi resize/zoom
    function updateBrowserZoomVars(){
        const z = computeBrowserZoom();
        setBrowserInv(z);
    }

    // lắng nghe thay đổi zoom của trình duyệt
    window.addEventListener('resize', updateBrowserZoomVars);
    if (window.visualViewport){
        window.visualViewport.addEventListener('resize', updateBrowserZoomVars);
    }

    updateBrowserZoomVars();   // chạy lần đầu

    function snap(value) {
        if (!snapToGrid) return value;
        return Math.round(value / 50) * 50;
    }

    function toggleToolbox() {
        if (!editMode) return;
        toolbox.classList.toggle('collapsed');
        const toggle = document.getElementById('toolbox-toggle');
        toggle.textContent = toolbox.classList.contains('collapsed') ? '▶' : '◀';
    }
    const controlPanel = document.getElementById('control-panel');
    const toggleBtn = document.getElementById('panel-toggle-btn');
    let panelVisible = true;

    toggleBtn.addEventListener('click', () => {
        panelVisible = !panelVisible;
        controlPanel.classList.toggle('hidden', !panelVisible);
        toggleBtn.innerHTML = panelVisible ? '⚙️' : '🟦'; // có thể đổi icon tùy thích
    });
    function toggleCategory(titleEl) {
        const content = titleEl.nextElementSibling;
        content.classList.toggle('collapsed');
        titleEl.classList.toggle('collapsed');
    }

    function toggleGrid() {
        showGrid = !showGrid;
        layout.classList.toggle('show-grid', showGrid);
        document.getElementById('grid-status').textContent = showGrid ? 'ON' : 'OFF';
    }

    function toggleSnap() {
        snapToGrid = !snapToGrid;
        document.getElementById('snap-status').textContent = snapToGrid ? 'ON' : 'OFF';
    }

    function toggleMode() {
        editMode = !editMode;
        document.getElementById('mode-status').textContent = editMode ? 'Chỉnh sửa' : 'Xem';
        document.body.classList.toggle('view-mode', !editMode);
        if (!editMode) {
            deselectAll();
            propertiesPanel.classList.remove('visible');
            toolbox.classList.add('collapsed');
            document.getElementById('toolbox-toggle').disabled = true;
        } else {
            document.getElementById('toolbox-toggle').disabled = false;
        }
        elements.forEach(item => {
            const editable = item.el.querySelector('.text') || (item.type === 'label' ? item.el : null);
            if (editable) editable.contentEditable = editMode;
        });
    }

    function zoomIn() {
        currentZoom = Math.min(currentZoom + 0.1, 3);
        applyZoom();
    }

    function zoomOut() {
        currentZoom = Math.max(currentZoom - 0.1, 0.3);
        applyZoom();
    }

    function resetZoom() {
        currentZoom = 1;
        applyZoom();
    }


    let zoomLocked = true;   // <-- khóa zoom khi mới vào trang

    function applyZoom(){
    layout.style.transform = `scale(${currentZoom})`;
    layout.style.setProperty('--zoom', currentZoom);
    layout.style.setProperty('--zoomInv', (1/currentZoom).toFixed(4));
    document.getElementById('zoom-level').textContent = Math.round(currentZoom*100) + '%';
    }

    // gọi sau khi DOM sẵn sàng / cuối script:
    applyZoom();

    function createElement(
    type, x, y, width, height,
    text = '', rotation = 0, flipH = 1, flipV = 1,
    process = '', deviceId = '', opts = {}
    ) {
        const snapOnPlace = (opts.snapOnPlace ?? true);
        const left = (snapOnPlace && snapToGrid) ? snap(x) : Math.round(x);
        const top  = (snapOnPlace && snapToGrid) ? snap(y) : Math.round(y);
        if (!editMode) return;
        const el = document.createElement('div');
        el.className = `machine ${type}`;
        el.dataset.type = type;
        el.style.left = snap(x) + 'px';
        el.style.top = snap(y) + 'px';
        el.style.width = width + 'px';
        el.style.height = height + 'px';
        el.dataset.origWidth = width;
        el.dataset.origHeight = height;
        el.dataset.rotation = rotation;
        el.dataset.flipH = flipH;
        el.dataset.flipV = flipV;
        el.dataset.deviceId = deviceId;
        el.style.transform = `rotate(${rotation}deg) scaleX(${flipH}) scaleY(${flipV})`;
        el.style.setProperty('--scale', 1);

        if (type === 'wall') {
            el.dataset.startX = x; el.dataset.startY = y;
            el.dataset.endX = x + width; el.dataset.endY = y + height;
            el.dataset.process = '';
            el.dataset.deviceId = '';
            } else {
            const t = (type || '').toLowerCase();
            const p = (process || '').toLowerCase();
            if (PROCESS_TYPES.has(p)) {
                el.dataset.process = p;
            } else if (PROCESS_TYPES.has(t)) {
                el.dataset.process = t;
            } else {
                el.dataset.process = '';           // <- không phải nhóm process
            }
            }

            // const PROCESS_UI = ['mold','tuft','tufting','blister'];
            // const autoProcess =
            // (process && PROCESS_UI.includes(process.toLowerCase()))
            //     ? process.toLowerCase()
            //     : (PROCESS_UI.includes((type||'').toLowerCase())
            //         ? type.toLowerCase()
            //         : 'mold');
            // el.dataset.process = autoProcess;
            const icons = {
                'tank': '🛢️', 'equipment': '⚙️', 'workstation': '👷', 'at1900': '🔧',
                'robot-arm': '🦾', 'cnc-machine': '🔩', 'assembly-line': '⚡', 'agv': '🚛',
                'mold': '', 'tufting': '', 'blister': '', 'ap':'📶',
                'office': '🏢', 'stairs': '🪜', 'conveyor': '→', 'pipe': '━', 'wall': '▬', 'label': ''
            };

        if (type === 'label') {
            el.textContent = text || 'lebel';
            el.contentEditable = editMode;
        } else {
            if (icons[type]) {
                const iconDiv = document.createElement('div');
                iconDiv.className = 'icon';
                iconDiv.textContent = icons[type];
                el.appendChild(iconDiv);
            }
            const textDiv = document.createElement('div');
            textDiv.className = 'text';
            textDiv.textContent = text || `${type}#${elementCounter}`;
            textDiv.contentEditable = editMode;
            el.appendChild(textDiv);
        }
        let deviceIdAuto = deviceId;
        if (!deviceIdAuto) {
        // Lấy text hiển thị: nếu là label thì lấy el.textContent, ngược lại lấy .text
        const contentSource =
            type === 'label'
            ? (el.textContent || '')
            : ((el.querySelector('.text')?.textContent) || text || '');

        const content = String(contentSource).trim();
        const m = content.match(/#\s*([A-Za-z0-9_-]+)/); // bắt phần sau dấu #
        if (m) deviceIdAuto = m[1];
        }
        el.dataset.deviceId = deviceIdAuto || '';

        
        addControlElements(el);
        makeInteractive(el);
        layout.appendChild(el);
        elements.push({ el, type, id: elementCounter++ });
        applyStatusStyle(el, el.dataset.status || 'UNKNOWN');
        saveHistory('create element');
        return el;
    }

    function addControlElements(el) {
        const deleteBtn = document.createElement('div');
        deleteBtn.className = 'delete-btn';
        deleteBtn.innerHTML = '×';
        deleteBtn.onclick = (e) => { e.stopPropagation(); deleteElement(el); };
        el.appendChild(deleteBtn);
        
        const rotateHandle = document.createElement('div');
        rotateHandle.className = 'rotate-handle';
        el.appendChild(rotateHandle);
        
        ['nw', 'ne', 'sw', 'se', 'n', 's', 'w', 'e'].forEach(pos => {
            const handle = document.createElement('div');
            handle.className = `resize-handle ${pos}`;
            handle.dataset.position = pos;
            el.appendChild(handle);
        });
    }
    function bindDeviceIdFromLabel() {
        elements.forEach(({ el, type }) => {
        const textDiv = el.querySelector('.text');
        const content = textDiv ? textDiv.textContent.trim() : (type === 'label' ? el.textContent.trim() : '');
        if (!el.dataset.deviceId && content) {
        const m = content.match(/#\s*([A-Za-z0-9_-]+)/);
        if (m) el.dataset.deviceId = m[1];
        }
    });
        alert('Assigned deviceId from the label to elements that do not have one.');
    }
    function makeInteractive(el) {
        el.addEventListener('mousedown', (e) => {
            if (!editMode) return;
            if (e.target.classList.contains('delete-btn') || e.target.classList.contains('resize-handle') || 
                e.target.classList.contains('rotate-handle')) return;
            if (e.target.contentEditable === 'true' && (e.target.classList.contains('text') || e.target === el)) return;
            
            e.preventDefault();
            e.stopPropagation();
            
            const machine = e.target.closest('.machine');
            if (!machine) return;
            
            const canvasRect = layout.getBoundingClientRect();
            
            if (!e.ctrlKey) {
                deselectAll();
                selectedElements = [machine];
                machine.classList.add('selected');
            } else {
                if (selectedElements.includes(machine)) {
                    machine.classList.remove('selected');
                    selectedElements = selectedElements.filter(s => s !== machine);
                } else {
                    selectedElements.push(machine);
                    machine.classList.add('selected');
                }
            }
            
            if (selectedElements.length === 1) updatePropertiesPanel();
            else propertiesPanel.classList.remove('visible');
            
            if (selectedElements.length > 0) {
                dragOffsets = selectedElements.map(s => {
                    const rect = s.getBoundingClientRect();
                    return {
                        el: s,
                        offsetX: (e.clientX - canvasRect.left) / currentZoom - parseInt(s.style.left) + layout.parentElement.scrollLeft,
                        offsetY: (e.clientY - canvasRect.top) / currentZoom - parseInt(s.style.top) + layout.parentElement.scrollTop
                    };
                });
                isDragging = true;
            }
        });
        el.addEventListener('dblclick', async (e) => {
            if (!isProcessEl(el)) return;                   // <- chỉ nhóm process mới mở modal
                if (!el.dataset.deviceId) {
                    alert('There is no Device ID for this element.');
                    return;
                }
                if (!el._detail) {
                    await fetchDataForElement(el, el.dataset.deviceId, el.dataset.process || 'mold');
                }
            openDeviceModal(el);
        });

        el.querySelectorAll('.resize-handle').forEach(handle => {
            handle.addEventListener('mousedown', (e) => {
                if (!editMode || selectedElements.length > 1) return;
                e.preventDefault();
                e.stopPropagation();
                
                deselectAll();
                selectedElements = [el];
                el.classList.add('selected');
                updatePropertiesPanel();
                isResizing = true;
                resizeHandle = handle.dataset.position;
                
                dragOffsets.startX = e.clientX;
                dragOffsets.startY = e.clientY;
                dragOffsets.startWidth = parseInt(el.style.width);
                dragOffsets.startHeight = parseInt(el.style.height);
                dragOffsets.startLeft = parseInt(el.style.left);
                dragOffsets.startTop = parseInt(el.style.top);
            });
        });

        const rotateHandle = el.querySelector('.rotate-handle');
        if (rotateHandle) {
            rotateHandle.addEventListener('mousedown', (e) => {
                if (!editMode || selectedElements.length > 1) return;
                e.preventDefault();
                e.stopPropagation();
                
                deselectAll();
                selectedElements = [el];
                el.classList.add('selected');
                updatePropertiesPanel();
                isRotating = true;
                
                const rect = el.getBoundingClientRect();
                elementCenter.x = rect.left + rect.width / 2;
                elementCenter.y = rect.top + rect.height / 2;
                
                const angle = Math.atan2(e.clientY - elementCenter.y, e.clientX - elementCenter.x);
                rotationStart = angle * (180 / Math.PI) - (parseFloat(el.dataset.rotation) || 0);
            });
        }
    }

    document.addEventListener('mousemove', (e) => {
        const canvasRect = layout.getBoundingClientRect();
        const zoom = currentZoom;
        const mouseX = (e.clientX - canvasRect.left) / zoom + layout.parentElement.scrollLeft;
        const mouseY = (e.clientY - canvasRect.top) / zoom + layout.parentElement.scrollTop;
        
        coordsDisplay.textContent = `X: ${Math.round(mouseX)}, Y: ${Math.round(mouseY)} | Zoom: ${Math.round(zoom*100)}%`;

        if (isSelecting) {
            const scx = layout.parentElement.scrollLeft;   
            const scy = layout.parentElement.scrollTop; 
            const currentX = (e.clientX - canvasRect.left) / zoom + scx;
            const currentY = (e.clientY - canvasRect.top) / zoom + scy;
            
            const left = Math.min(selectionStart.x, currentX);
            const top = Math.min(selectionStart.y, currentY);
            const width = Math.abs(currentX - selectionStart.x);
            const height = Math.abs(currentY - selectionStart.y);
            
            selectionBox.style.left = left + 'px';
            selectionBox.style.top = top + 'px';
            selectionBox.style.width = width + 'px';
            selectionBox.style.height = height + 'px';
            
            const selectionRect = {
                left: left,
                top: top,
                right: left + width,
                bottom: top + height
            };
            
            elements.forEach(item => {
                const el = item.el;
                const elLeft = parseInt(el.style.left);
                const elTop = parseInt(el.style.top);
                const elRight = elLeft + parseInt(el.style.width);
                const elBottom = elTop + parseInt(el.style.height);
                
                if (elLeft < selectionRect.right && elRight > selectionRect.left &&
                    elTop < selectionRect.bottom && elBottom > selectionRect.top) {
                    if (!selectedElements.includes(el)) {
                        el.classList.add('selected');
                    }
                } else {
                    if (!e.ctrlKey && !e.shiftKey) {
                        el.classList.remove('selected');
                    }
                }
            });
            return;
        }
        
        if (isDragging) {
            dragOffsets.forEach(d => {
                const newX = snap(mouseX - d.offsetX);
                const newY = snap(mouseY - d.offsetY);

                if (d.el.classList.contains('wall')) {
                    const currentWidth = parseInt(d.el.style.width);
                    const currentHeight = parseInt(d.el.style.height);
                    const origX = parseInt(d.el.style.left);
                    const origY = parseInt(d.el.style.top);
                    const deltaX = newX - origX;
                    const deltaY = newY - origY;

                    let adjustedX = newX;
                    let adjustedY = newY;

                    elements.forEach(item => {
                        if (item.el !== d.el && item.el.classList.contains('wall')) {
                            const otherStartX = parseInt(item.el.dataset.startX);
                            const otherStartY = parseInt(item.el.dataset.startY);
                            const otherEndX = parseInt(item.el.dataset.endX);
                            const otherEndY = parseInt(item.el.dataset.endY);

                            const distStart = Math.hypot(newX - otherEndX, newY - otherEndY);
                            const distEnd = Math.hypot(newX + currentWidth - otherStartX, newY + currentHeight - otherStartY);

                            if (distStart < SNAP_THRESHOLD) {
                                adjustedX = otherEndX - deltaX;
                                adjustedY = otherEndY - deltaY;
                            } else if (distEnd < SNAP_THRESHOLD) {
                                adjustedX = otherStartX - currentWidth - deltaX;
                                adjustedY = otherStartY - currentHeight - deltaY;
                            }
                        }
                    });

                    d.el.style.left = snap(adjustedX) + 'px';
                    d.el.style.top  = snap(adjustedY) + 'px';
                    d.el.dataset.startX = snap(adjustedX);
                    d.el.dataset.startY = snap(adjustedY);
                    d.el.dataset.endX = snap(adjustedX + currentWidth);
                    d.el.dataset.endY = snap(adjustedY + currentHeight);
                } else {
                    d.el.style.left = snap(newX) + 'px';
                    d.el.style.top = snap(newY) + 'px';
                }
            });
            if (selectedElements.length === 1) updatePropertiesPanel();
        }
        
        if (isResizing && selectedElements.length === 1) {
            const el = selectedElements[0];
            const dx = (e.clientX - dragOffsets.startX) / zoom;
            const dy = (e.clientY - dragOffsets.startY) / zoom;
            const flipH = parseFloat(el.dataset.flipH) || 1;
            const flipV = parseFloat(el.dataset.flipV) || 1;
            
            let newWidth = dragOffsets.startWidth;
            let newHeight = dragOffsets.startHeight;
            let newLeft = dragOffsets.startLeft;
            let newTop = dragOffsets.startTop;
            let handlePos = resizeHandle;
            // Nếu đã flip theo trục, đảo nghĩa handle ngang/dọc


            if (flipH < 0) {
                if (handlePos.includes('e')) handlePos = handlePos.replace('e','w');
                else if (handlePos.includes('w')) handlePos = handlePos.replace('w','e');
            }if (flipV < 0) {
                if (handlePos.includes('n')) handlePos = handlePos.replace('n','s');
                else if (handlePos.includes('s')) handlePos = handlePos.replace('s','n');
            }

            switch (handlePos) {
                case 'se': newWidth += dx; newHeight += dy; break;
                case 'sw': newWidth -= dx; newLeft  += dx; newHeight += dy; break;
                case 'ne': newWidth += dx; newHeight -= dy; newTop   += dy; break;
                case 'nw': newWidth -= dx; newLeft  += dx; newHeight -= dy; newTop  += dy; break;
                case 'e':  newWidth += dx; break;
                case 'w':  newWidth -= dx; newLeft  += dx; break;
                case 's':  newHeight += dy; break;
                case 'n':  newHeight -= dy; newTop   += dy; break;
            }

            switch(resizeHandle) {
                case 'se': newWidth += dx; newHeight += dy; break;
                case 'sw': newWidth -= dx; newLeft += dx; newHeight += dy; break;
                case 'ne': newWidth += dx; newHeight -= dy; newTop += dy; break;
                case 'nw': newWidth -= dx; newLeft += dx; newHeight -= dy; newTop += dy; break;
                case 'e': newWidth += dx; break;
                case 'w': newWidth -= dx; newLeft += dx; break;
                case 's': newHeight += dy; break;
                case 'n': newHeight -= dy; newTop += dy; break;
            }
            
            el.style.width = Math.max(5, newWidth) + 'px';
            el.style.height = Math.max(5, newHeight) + 'px';
            el.style.left = newLeft + 'px';
            el.style.top = newTop + 'px';
            updateScale(el);

            if (el.classList.contains('wall')) {
                el.dataset.startX = snap(newLeft);
                el.dataset.startY = snap(newTop);
                el.dataset.endX = snap(newLeft + newWidth);
                el.dataset.endY = snap(newTop + newHeight);
            }
            updatePropertiesPanel();
        }
        
        if (isRotating && selectedElements.length === 1) {
            const el = selectedElements[0];
            const angle = Math.atan2(e.clientY - elementCenter.y, e.clientX - elementCenter.x);
            let rotation = angle * (180 / Math.PI) - rotationStart;
            if (snapToGrid) rotation = Math.round(rotation / 15) * 15;
            el.style.transform = `rotate(${rotation}deg) scaleX(${el.dataset.flipH}) scaleY(${el.dataset.flipV})`;
            el.dataset.rotation = rotation;
            updatePropertiesPanel();
        }
    });
    document.addEventListener('keydown', (e) => {
        if (e.ctrlKey && e.key.toLowerCase() === 'z') {
            e.preventDefault();
            undo();
        } else if (e.ctrlKey && (e.key.toLowerCase() === 'y' || (e.shiftKey && e.key.toLowerCase() === 'z'))) {
            e.preventDefault();
            redo();
        }
    });
    document.addEventListener('mouseup', () => {
        const changed = isDragging || isResizing || isRotating;
        if (isSelecting) {
            selectedElements = elements.filter(item => item.el.classList.contains('selected')).map(item => item.el);
            selectionBox.classList.remove('active');
            if (selectedElements.length === 1) {
                updatePropertiesPanel();
            } else if (selectedElements.length > 1) {
                propertiesPanel.classList.remove('visible');
            }
        }
        if (changed) saveHistory(isDragging ? 'move' : isResizing ? 'resize' : 'rotate');
        
        isDragging = false;
        isResizing = false;
        isRotating = false;
        isSelecting = false;
        dragOffsets = [];
    });

    function updateScale(el) {
        const scale = Math.min(parseFloat(el.style.width) / parseFloat(el.dataset.origWidth), 
                            parseFloat(el.style.height) / parseFloat(el.dataset.origHeight));
        el.style.setProperty('--scale', scale);
    }

    function deselectAll() {
        selectedElements.forEach(el => el.classList.remove('selected'));
        selectedElements = [];
    }

    function updatePropertiesPanel() {
        if (selectedElements.length !== 1) {
            propertiesPanel.classList.remove('visible');
            return;
        }

        const el = selectedElements[0];
        propertiesPanel.classList.add('visible');

        // điền các thuộc tính vị trí/kích thước/rotation
        document.getElementById('prop-x').value        = parseInt(el.style.left)   || 0;
        document.getElementById('prop-y').value        = parseInt(el.style.top)    || 0;
        document.getElementById('prop-width').value    = parseInt(el.style.width)  || 0;
        document.getElementById('prop-height').value   = parseInt(el.style.height) || 0;
        document.getElementById('prop-rotation').value = parseFloat(el.dataset.rotation) || 0;

        const processInput = document.getElementById('prop-process');
        const deviceInput  = document.getElementById('prop-device-id');

        // nếu bạn có các hàng riêng trong panel, gán data-attr để ẩn/hiện gọn
        const processRow = document.querySelector('[data-prop="process-row"]');
        const deviceRow  = document.querySelector('[data-prop="device-row"]');

        const proc = isProcessEl(el);

        // Ẩn/hiện hàng (nếu có)
        if (processRow) processRow.style.display = proc ? '' : 'none';
        if (deviceRow)  deviceRow.style.display  = proc ? '' : 'none';

        // Bật/tắt input và set value
        if (proc) {
            if (processInput) {
            processInput.disabled = false;
            processInput.value = el.dataset.process || 'mold';
            }
            if (deviceInput) {
            deviceInput.disabled = false;
            deviceInput.value = el.dataset.deviceId || '';
            }
        } else {
            // loại thường: không process, không deviceId
            if (processInput) {
            processInput.disabled = true;
            processInput.value = '';
            }
            if (deviceInput) {
            deviceInput.disabled = true;
            deviceInput.value = '';
            }
        }
    }

    function openDeviceModal(el) {
        const d = el._detail || {};
        // tiêu đề
        document.getElementById('dm-title').textContent = d.mold_id || el.dataset.deviceId || '—';
        document.getElementById('dm-subtitle').textContent = (d.family ? d.family + ' • ' : '') + (d.process || (el.dataset.process||'').toUpperCase());

        // fields
        const set = (id, v, suffix='') => document.getElementById(id).textContent = (v ?? v===0 ? (suffix && typeof v==='number' ? (v + suffix) : v) : '—');
        set('dm-mold_id', d.mold_id);
        set('dm-family', d.family);
        set('dm-process', d.process);
        set('dm-mold_cavity', d.mold_cavity);
        set('dm-actual_cavity', d.actual_cavity);
        set('dm-capacity_per_hr', d.capacity_per_hr);
        set('dm-efficiency', d.efficiency, '%');
        set('dm-eff_ll', d.efficiency_lower_limit, '%');
        set('dm-current_cycle', d.current_cycle);
        set('dm-output', d.output);
        set('dm-cyclecount', d.cyclecount);
        set('dm-target', d.target);
        set('dm-bush_per_cycle', d.bush_per_cycle);
        set('dm-hole_per_brush', d.hole_per_brush);
        set('dm-upper_limit', d.upper_limit);
        set('dm-lower_limit', d.lower_limit);
        set('dm-total_lost_pcs', d.total_lost_pcs);
        set('dm-lost_time', d.lost_time);
        set('dm-last_updated', d.last_updated);

        // badge status
        const badge = document.getElementById('dm-status');
        const s = (d.status || 'UNKNOWN').toUpperCase();
        badge.textContent = s;
        badge.classList.remove('normal','breached','disconnected');
        if (s === 'BREACHED') badge.classList.add('breached');
        else if (s === 'DISCONNECTED') badge.classList.add('disconnected');
        else badge.classList.add('normal');

        document.getElementById('device-modal').classList.add('show');
        }

        function closeDeviceModal() {
        document.getElementById('device-modal').classList.remove('show');
        }


    function updateProperty(prop) {
        if (selectedElements.length !== 1) return;
        const el = selectedElements[0];
        const value = document.getElementById(`prop-${prop}`).value;
        
        switch(prop) {
            case 'x': el.style.left = parseFloat(value) + 'px'; break;
            case 'y': el.style.top = parseFloat(value) + 'px'; break;
            case 'width': el.style.width = Math.max(5, parseFloat(value)) + 'px'; updateScale(el); break;
            case 'height': el.style.height = Math.max(5, parseFloat(value)) + 'px'; updateScale(el); break;
            case 'rotation':
                el.dataset.rotation = parseFloat(value);
                el.style.transform = `rotate(${value}deg) scaleX(${el.dataset.flipH}) scaleY(${el.dataset.flipV})`;
                break;
            case 'process': el.dataset.process = value; break;
            case 'device-id': el.dataset.deviceId = value; break;
        }
    }

    function flipHorizontal() {
        if (!editMode) return;
        selectedElements.forEach(el => {
            const flipH = parseFloat(el.dataset.flipH) * -1;
            el.dataset.flipH = flipH;
            el.style.transform = `rotate(${el.dataset.rotation}deg) scaleX(${flipH}) scaleY(${el.dataset.flipV})`;
        });
        if (selectedElements.length === 1) updatePropertiesPanel();
    }

    function flipVertical() {
        if (!editMode) return;
        selectedElements.forEach(el => {
            const flipV = parseFloat(el.dataset.flipV) * -1;
            el.dataset.flipV = flipV;
            el.style.transform = `rotate(${el.dataset.rotation}deg) scaleX(${el.dataset.flipH}) scaleY(${flipV})`;
        });
        if (selectedElements.length === 1) updatePropertiesPanel();
    }

    function rotateElement(degrees) {
        if (!editMode) return;
        selectedElements.forEach(el => {
            const rotation = (parseFloat(el.dataset.rotation) || 0) + degrees;
            el.dataset.rotation = rotation;
            el.style.transform = `rotate(${rotation}deg) scaleX(${el.dataset.flipH}) scaleY(${el.dataset.flipV})`;
        });
        if (selectedElements.length === 1) updatePropertiesPanel();
    }

    document.querySelectorAll('.tool-item').forEach(item => {
        item.addEventListener('click', () => {
            if (!editMode) return;
            const container = document.getElementById('canvas-container');
            const x = (container.scrollLeft + container.clientWidth / 2) / currentZoom - parseInt(item.dataset.width) / 2;
            const y = (container.scrollTop + container.clientHeight / 2) / currentZoom - parseInt(item.dataset.height) / 2;
            createElement(item.dataset.type, x, y, parseInt(item.dataset.width), parseInt(item.dataset.height));
        });
    });

    function deleteElement(el) {
        if (!editMode) return;
        const index = elements.findIndex(item => item.el === el);
        if (index > -1) elements.splice(index, 1);
        selectedElements = selectedElements.filter(s => s !== el);
        el.remove();
        if (selectedElements.length === 1) updatePropertiesPanel();
        else propertiesPanel.classList.remove('visible');
    }

    function deleteSelected() {
        if (!editMode) return;
        const toDelete = selectedElements.slice();
        deselectAll();
        toDelete.forEach(deleteElement);
        hideContextMenu();
        saveHistory('delete element');
    }

    function copyElement() {
        if (!editMode || selectedElements.length === 0) return;
        const minX = Math.min(...selectedElements.map(el => parseInt(el.style.left)));
        const minY = Math.min(...selectedElements.map(el => parseInt(el.style.top)));
        clipboard = selectedElements.map(el => ({
            type: el.dataset.type,
            relX: parseInt(el.style.left) - minX,
            relY: parseInt(el.style.top) - minY,
            width: parseInt(el.style.width),
            height: parseInt(el.style.height),
            origWidth: parseFloat(el.dataset.origWidth),
            origHeight: parseFloat(el.dataset.origHeight),
            text: el.querySelector('.text') ? el.querySelector('.text').textContent : (el.classList.contains('label') ? el.textContent : ''),
            rotation: parseFloat(el.dataset.rotation) || 0,
            flipH: parseFloat(el.dataset.flipH) || 1,
            flipV: parseFloat(el.dataset.flipV) || 1,
            process: el.dataset.process || '',
            deviceId: el.dataset.deviceId || ''
        }));
        hideContextMenu();
    }

    function pasteElement() {
        if (!editMode || clipboard.length === 0) return;
        const container = document.getElementById('canvas-container');
        const pasteX = (container.scrollLeft + container.clientWidth / 2) / currentZoom;
        const pasteY = (container.scrollTop + container.clientHeight / 2) / currentZoom;
        clipboard.forEach(item => {
            const el = createElement(item.type, pasteX + item.relX, pasteY + item.relY, item.width, item.height, 
                                    item.text, item.rotation, item.flipH, item.flipV, item.process, item.deviceId);
            el.dataset.origWidth = item.origWidth;
            el.dataset.origHeight = item.origHeight;
            updateScale(el);
        });
        hideContextMenu();
    }

    function duplicateElement() {
        if (!editMode) return;
        copyElement();
        pasteElement();
    }

    document.addEventListener('keydown', (e) => {
        if (!editMode || e.target.tagName === 'INPUT') return;
        
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'a') {
            e.preventDefault();
            deselectAll();
            selectedElements = elements.map(item => item.el);
            selectedElements.forEach(el => el.classList.add('selected'));
            if (selectedElements.length > 1) {
                propertiesPanel.classList.remove('visible');
            }
            return;
        }
        
        if (e.ctrlKey || e.metaKey) {
            switch(e.key.toLowerCase()) {
                case 'c': e.preventDefault(); copyElement(); break;
                case 'v': e.preventDefault(); pasteElement(); break;
                case 'd': e.preventDefault(); duplicateElement(); break;
            }
            return;
        }
        
        if (selectedElements.length === 0) return;
        const step = e.shiftKey ? 10 : 1;
        
        switch(e.key) {
            case 'ArrowLeft': e.preventDefault(); 
                selectedElements.forEach(el => el.style.left = snap(parseInt(el.style.left) - step) + 'px');
                if (selectedElements.length === 1) updatePropertiesPanel(); break;
            case 'ArrowRight': e.preventDefault(); 
                selectedElements.forEach(el => el.style.left = snap(parseInt(el.style.left) + step) + 'px');
                if (selectedElements.length === 1) updatePropertiesPanel(); break;
            case 'ArrowUp': e.preventDefault(); 
                selectedElements.forEach(el => el.style.top = snap(parseInt(el.style.top) - step) + 'px');
                if (selectedElements.length === 1) updatePropertiesPanel(); break;
            case 'ArrowDown': e.preventDefault(); 
                selectedElements.forEach(el => el.style.top = snap(parseInt(el.style.top) + step) + 'px');
                if (selectedElements.length === 1) updatePropertiesPanel(); break;
            case 'Delete': e.preventDefault(); deleteSelected(); break;
            case 'Escape': deselectAll(); propertiesPanel.classList.remove('visible'); break;
            case 'r': case 'R': e.preventDefault(); rotateElement(e.shiftKey ? -15 : 15); break;
        }
    });

    document.addEventListener('contextmenu', (e) => {
        if (!editMode) return;
        const machine = e.target.closest('.machine');
        if (machine) {
            e.preventDefault();
            if (!selectedElements.includes(machine)) {
                deselectAll();
                selectedElements = [machine];
                machine.classList.add('selected');
                if (selectedElements.length === 1) updatePropertiesPanel();
            }
            showContextMenu(e.clientX, e.clientY);
        } else {
            hideContextMenu();
        }
    });

    function showContextMenu(x, y) {
        contextMenu.style.left = x + 'px';
        contextMenu.style.top = y + 'px';
        contextMenu.classList.add('visible');
    }

    function hideContextMenu() {
        contextMenu.classList.remove('visible');
    }

    document.addEventListener('click', (e) => {
        if (!e.target.closest('#context-menu')) hideContextMenu();
    });

    function refreshData() {
        elements.forEach(item => {
            const el = item.el;
            if (item.type === 'wall') return;
            if (!isProcessEl(el)) return;                 // <- thêm dòng này
            const deviceId = el.dataset.deviceId;
            if (deviceId) fetchDataForElement(el, deviceId, el.dataset.process || 'mold');
        });
    }
    function applyStatusStyle(el, rawStatus) {
        if (!el || el.classList.contains('wall')) return;

        // Chuẩn hoá và map lỗi chính tả/đồng nghĩa
        const s0 = String(rawStatus || '').trim().toUpperCase();
        const map = {
            'NORMAL': 'NORMAL',
            'OK': 'NORMAL',

            'BREACHED': 'BREACHED',

            'DISCONNECTED': 'DISCONNECTED',
            'DISCONNECT': 'DISCONNECTED'
        };
        const status = map[s0] || 'UNKNOWN';

        // Xoá class cũ rồi gắn class mới
        el.classList.remove('status-normal','status-breached','status-break','status-disconnected','status-unknown');
        switch (status) {
            case 'NORMAL':        el.classList.add('status-normal'); break;
            case 'BREACHED':      el.classList.add('status-breached'); break;
            case 'DISCONNECTED':  el.classList.add('status-disconnected'); break;
            default:              el.classList.add('status-unknown'); break;
        }

        // Lưu lại để nơi khác có thể dùng
        el.dataset.status = status;
    }
    async function fetchAPDetail(el, apName) {
        if (!apName) return;

        const url = `/web_develop/ems/backend/ap_status_api.php?names=${encodeURIComponent(apName)}&stale_minutes=10`;
        const resp = await fetch(url, { cache: 'no-store' });
        if (!resp.ok) throw new Error(`HTTP ${resp.status} ${resp.statusText}`);
        const j = await resp.json();

        const info = (j.map && j.map[apName]) || (j.items || []).find(r => (r.name||'') === apName);
        const textDiv = el.querySelector('.text');

        if (!info) {
            applyStatusStyle(el, 'DISCONNECTED');
            if (textDiv) textDiv.innerHTML = `${apName}<br>❌ No Log`;
            el._detail = {
            mold_id: apName,
            family: 'Access Point',
            process: 'AP',
            status: 'DISCONNECTED',
            last_updated: null,
            ping_ms: null
            };
            return;
        }

        const status = String(info.status || 'UNKNOWN').toUpperCase();
        applyStatusStyle(el, status);

        const pingStr = (info.ping_ms != null) ? ` • ${Number(info.ping_ms).toFixed(1)}ms` : '';
        if (textDiv) textDiv.innerHTML = `${info.name}<br>${status}${pingStr}`;

        el._detail = {
            mold_id: info.name,          // dùng field sẵn có của modal
            family: 'Access Point',
            process: 'AP',
            status: status,
            last_updated: info.last_seen,
            ping_ms: info.ping_ms
        };
        }

    async function fetchDataForElement(el, deviceId, process) {
        if (!el || el.classList.contains('wall')) return;
        if (!PROCESS_TYPES.has(String(process||'').toLowerCase())) return;
            // map các tên process từ UI -> tên API/DB
            const mapProcess = {
                tufting: 'tuft', // UI → API
                tuft: 'tuft',
                mould: 'mold',
                mold: 'mold',
                'end-rounding': 'end-rounding',
                ap: 'ap',
        };
            const pUI = (process || 'mold').toLowerCase();
            const p   = mapProcess[pUI] || pUI;
              if (p === 'ap') {
                await fetchAPDetail(el, deviceId);
                return;
            }
            const url = `${API_URL}?action=get_machine_details&process=${encodeURIComponent(p)}&status=total`;
        try {
            console.log('[fetch]', { url, deviceId, processUI: pUI, processAPI: p });
            const resp = await fetch(url, { cache: 'no-store' });
            if (!resp.ok) throw new Error(`HTTP ${resp.status} ${resp.statusText}`);

            let dataText = await resp.text();
            dataText = dataText.trim().replace(/^\uFEFF/, '');
            let data = JSON.parse(dataText);
            if (!Array.isArray(data)) data = [];

            const candidates = ['device_id','mold_id','tufting_id','blister_id','machine_id','id','code','name'];
            const idKey = candidates.find(k => data.length && Object.hasOwn(data[0], k)) || 'device_id';

            const norm = v => String(v||'').trim().toLowerCase();
            const devNorm = norm(deviceId);

            let device = data.find(d => {
            const apiVal = norm(d[idKey]);
            if (!devNorm) return false;
            if (apiVal === devNorm) return true;
            return apiVal.endsWith('-' + devNorm) || apiVal.endsWith('#' + devNorm);
            });

            // Fallback: nếu theo process không thấy, thử gọi API không lọc process
            if (!device && data.length === 0) {
            const resp2 = await fetch(`${API_URL}?action=get_machine_details&status=total`, { cache: 'no-store' });
            if (resp2.ok) {
                let t = (await resp2.text()).trim().replace(/^\uFEFF/, '');
                let all = [];
                try { all = JSON.parse(t); } catch {}
                const idKey2 = candidates.find(k => all.length && Object.hasOwn(all[0], k)) || 'device_id';
                device = (all||[]).find(d => {
                const apiVal = norm(d[idKey2]);
                return apiVal === devNorm || apiVal.endsWith('-'+devNorm) || apiVal.endsWith('#'+devNorm);
                });
            }
        }

        const textDiv = el.querySelector('.text');
        if (!textDiv) return;

        if (device) {
            // Tính các giá trị hiển thị
            const eff = (device.efficiency ?? device.oee ?? device.utilization ?? '');
            const familyOrProc = device.family || device.process || p.toUpperCase();
            const lost = device.lost_time || device.downtime || '';
            const status = (device.status || 'NORMAL').toUpperCase();

            // Cập nhật text trên ô máy
            textDiv.innerHTML =
                // `${familyOrProc}<br>` +
                `${device.device_id || deviceId}<br>` +
                `${status}<br>` ;

            // 🔽🔽🔽 THSÊM TỪ ĐÂY 🔽🔽🔽

            // Lưu lại status để dùng lại
            el.dataset.status = status;

            // Gom đúng fields cho popup chi tiết
            el._detail = {
                mold_id: device.device_id || device[idKey] || deviceId,
                family: device.family ?? null,
                process: (device.process || p).toString().toUpperCase(),
                mold_cavity: device.mold_cavity != null ? Number(device.mold_cavity) : null,
                actual_cavity: device.actual_cavity != null
                ? Number(device.actual_cavity)
                : (device.cavities != null ? Number(device.cavities) : 0),
                capacity_per_hr: device.capacity_per_hr != null
                ? Number(device.capacity_per_hr)
                : (device.capacity != null ? Number(device.capacity) : null),
                efficiency: eff != null ? Number(eff) : 0,
                efficiency_lower_limit: device.efficiency_lower_limit != null ? Number(device.efficiency_lower_limit) : null,
                current_cycle: (device.current_cycle != null)
                ? Number(device.current_cycle)
                : (p==='mold'
                    ? Number(device.cycle_time ?? 0)
                    : (p==='tuft'
                        ? Number(device.output ?? 0)
                        : Number(device.cyclecount ?? 0))),
                output: device.output != null ? Number(device.output) : 0,
                cyclecount: device.cyclecount != null ? Number(device.cyclecount) : 0,
                target: device.target != null
                ? Number(device.target)
                : (device.target_limit != null ? Number(device.target_limit) : null),
                bush_per_cycle: device.brushes_per_cycle != null ? Number(device.brushes_per_cycle) : null,
                hole_per_brush: device.hole_per_brush != null ? Number(device.hole_per_brush) : null,
                upper_limit: device.upper_limit != null ? Number(device.upper_limit) : null,
                lower_limit: device.lower_limit != null ? Number(device.lower_limit) : null,
                total_lost_pcs: device.total_lost_pcs != null ? Number(device.total_lost_pcs) : Number(device.loss_pcs ?? 0),
                lost_time: lost != null ? Number(lost) : 0,
                status: status,
                last_updated: device.last_updated ?? device.total_count_updated_at ?? device.cavity_count_updated_at ?? ''
            };

            // Đổi màu viền theo status
            applyStatusStyle(el, status);

            // 🔼🔼🔼 ĐẾN ĐÂY 🔼🔼🔼

            } else {
            if (isProcessEl(el)) {
                textDiv.innerHTML = `ID: ${deviceId}<br>❌ No Data`;
                applyStatusStyle(el, 'UNKNOWN');
                el._detail = null;
            } else {
            // loại thường: giữ nguyên text hiện có, không hiển thị No Data
        }
    }

    } catch (err) {
        console.error('API error:', err);
        const textDiv = el.querySelector('.text');
        if (textDiv) textDiv.innerHTML = `ID: ${deviceId}<br>⚠️ Error API: ${err.message}`;
        el.style.borderColor = '#e67e22';
    }
    }


    function exportLayout() {
        if (!editMode) return;
        const data = getLayoutData();
        const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `factory_layout_${Date.now()}.json`;
        a.click();
    }
    function importLayout() {
        if (!editMode) return;
        document.getElementById('import-file').click();
    }

    function handleImport(event) {
    if (!editMode) return;
    const file = event.target.files[0];
    if (!file) return;

    const reader = new FileReader();
    reader.onload = (e) => {
        try {
        const data = JSON.parse(e.target.result);
        if (confirm('Delete the current layout and load the imported layout?')) {
            clearAll(true);

            data.forEach((item) => {
            const typeStr = String(item.type || '').toLowerCase();
            const PROCESS_UI = ['mold', 'tuft', 'tufting', 'blister'];

            // Tính process CHỈ trong phạm vi từng item
            const fixedProcess =
                (item.process && PROCESS_UI.includes(String(item.process).toLowerCase()))
                ? String(item.process).toLowerCase()
                : (PROCESS_UI.includes(typeStr) ? typeStr : '');

            // Wall: không cần process, không gọi API
            const processToUse = (typeStr === 'wall') ? '' : fixedProcess;

            const el = createElement(
                item.type,
                item.x, item.y,
                item.width, item.height,
                item.text,
                item.rotation, item.flipH, item.flipV,
                processToUse,
                item.deviceId || '',
                { snapOnPlace: false }
            );

            // Nếu chưa có deviceId, thử lấy từ nhãn "#..."
            if (el && !el.dataset.deviceId) {
                const labelText = String(item.text || '').trim();
                const m = labelText.match(/#\s*([A-Za-z0-9_-]+)/);
                if (m) el.dataset.deviceId = m[1];
            }

            if (el) {
                el.dataset.origWidth  = item.origWidth;
                el.dataset.origHeight = item.origHeight;
                updateScale(el);
            }
            });
        }
        } catch (err) {
        alert('Lỗi tải tệp: ' + err.message);
        }
    };

    reader.readAsText(file);
    event.target.value = '';
    saveHistory('import layout');
    }


    function clearAll(silent = false) {
        if (!editMode) return;
        if (!silent && !confirm('Delete all items? This action cannot be undone!')) return;
        elements.forEach(item => item.el.remove());
        elements = [];
        deselectAll();
        propertiesPanel.classList.remove('visible');
        elementCounter = 0;
    }

    layout.addEventListener('mousedown', (e) => {
        if (e.target === layout) {
            if (!e.ctrlKey && !e.shiftKey) {
                deselectAll();
                propertiesPanel.classList.remove('visible');
            }
            
            if (editMode) {
                isSelecting = true;
                const canvasRect = layout.getBoundingClientRect();
                const zoom = currentZoom;
                const scx = layout.parentElement.scrollLeft;
                const scy = layout.parentElement.scrollTop; 
                selectionStart.x = (e.clientX - canvasRect.left) / zoom + scx;
                selectionStart.y = (e.clientY - canvasRect.top) / zoom + scy;
                
                if (!selectionBox) {
                    selectionBox = document.getElementById('selection-box');
                }
                selectionBox.style.left = selectionStart.x + 'px';
                selectionBox.style.top = selectionStart.y + 'px';
                selectionBox.style.width = '0px';
                selectionBox.style.height = '0px';
                selectionBox.classList.add('active');
            }
        }
    });
    async function saveLayoutToDB() {
  try {
    const name = prompt('Đặt tên layout:', currentLayoutName || `Layout ${new Date().toLocaleString()}`);
    if (!name) return;

    const description = prompt('Mô tả ngắn (tuỳ chọn):', '');
    const data = getLayoutData();

    // Lấy kích thước canvas (nếu bạn muốn lưu)
    const w = parseInt((layout.style.width || '').replace('px','')) || 10000;
    const h = parseInt((layout.style.height || '').replace('px','')) || 10000;

    let overwrite = false;
    if (currentLayoutId) overwrite = confirm(`Bạn đang mở "${currentLayoutName}". Ghi đè layout này?`);

    const body = {
      id: overwrite ? currentLayoutId : undefined,
      name, description,
      layout_json: data,
      canvas_w: w, canvas_h: h
    };

    const resp = await fetch(`${LAYOUT_API}?action=save_layout`, {
      method: 'POST',
      headers: getAuthHeaders(),
      body: JSON.stringify(body),
      credentials: 'include'
    });
    const j = await resp.json();
    if (!resp.ok || j.error) throw new Error(j.error || `HTTP ${resp.status}`);

    currentLayoutId = j.id;
    currentLayoutName = name;
    alert(`Đã lưu ${j.mode === 'update' ? 'cập nhật' : 'mới'}: #${j.id} - ${name}`);
  } catch (e) {
    alert('Save thất bại: ' + e.message);
  }
}

/* ====== Modal mở layout từ DB ====== */
let __layoutSearchTimer = null;

function openLayoutPicker() {
  document.getElementById('layout-modal').classList.add('show');
  document.getElementById('layout-search').value = '';
  reloadLayouts();
}
function closeLayoutModal() {
  document.getElementById('layout-modal').classList.remove('show');
}
function debouncedSearchLayouts() {
  clearTimeout(__layoutSearchTimer);
  __layoutSearchTimer = setTimeout(reloadLayouts, 300);
}

async function reloadLayouts() {
  const q = document.getElementById('layout-search').value.trim();
  const url = `${LAYOUT_API}?action=list_layouts` + (q ? `&q=${encodeURIComponent(q)}` : '');
  try {
    const resp = await fetch(url, { cache: 'no-store' });
    const j = await resp.json();
    if (!resp.ok || j.error) throw new Error(j.error || `HTTP ${resp.status}`);
    renderLayoutList(j.items || []);
  } catch (e) {
    document.getElementById('layout-list').innerHTML = `<div style="color:#e11;">Lỗi tải danh sách: ${e.message}</div>`;
  }
}

function renderLayoutList(items) {
  const box = document.getElementById('layout-list');
  if (!items.length) { box.innerHTML = '<div>Không có layout nào.</div>'; return; }

  const rows = items.map(it => {
    const title = it.name.replace(/</g,'&lt;');
    const desc  = (it.description || '').replace(/</g,'&lt;');
    const meta  = `${it.canvas_w||'-'}×${it.canvas_h||'-'} • ${it.created_by||'-'} • ${new Date(it.updated_at).toLocaleString()}`;
    return `
      <div style="border:1px solid rgba(58,140,255,.2); border-radius:12px; padding:12px; margin:8px 0; display:flex; justify-content:space-between; gap:10px;">
        <div>
          <div style="font-weight:700; color:#3A8CFF;">${title}</div>
          <div style="font-size:12px; color:#555;">${desc || '<i>No description</i>'}</div>
          <div style="font-size:12px; color:#888; margin-top:4px;">${meta}</div>
        </div>
        <div style="display:flex; align-items:center; gap:6px; white-space:nowrap;">
          <button class="property-btn" onclick="loadLayoutById(${it.id}, '${title.replace(/'/g,"&#39;")}')">Open</button>
          <button class="property-btn" onclick="overwriteWithCurrent(${it.id}, '${title.replace(/'/g,"&#39;")}')" title="Ghi đè bằng layout đang mở">Overwrite</button>
          <button class="property-btn" style="background:linear-gradient(135deg,#ff4757,#e84118);" onclick="deleteLayout(${it.id}, '${title.replace(/'/g,"&#39;")}')">Delete</button>
        </div>
      </div>
    `;
  }).join('');
  box.innerHTML = rows;
}

    async function loadLayoutById(id, name) {
        try {
            const resp = await fetch(`${LAYOUT_API}?action=get_layout&id=${id}`, { cache: 'no-store' });
            const j = await resp.json();
            if (!resp.ok || j.error) throw new Error(j.error || `HTTP ${resp.status}`);

            // Khôi phục kích thước canvas nếu muốn
            if (j.canvas_w && j.canvas_h) {
            layout.style.width = j.canvas_w + 'px';
            layout.style.height = j.canvas_h + 'px';
            }

            restoreLayout(j.layout_json || []);   // <— sử dụng hàm bạn đã có
            saveHistory('load layout DB');

            currentLayoutId = j.id;
            currentLayoutName = j.name;

            closeLayoutModal();
            alert(`Đã mở layout: #${j.id} - ${j.name}`);
        } catch (e) {
            alert('Load thất bại: ' + e.message);
        }
    }

    async function overwriteWithCurrent(id, name) {
        if (!confirm(`Ghi đè layout #${id} - "${name}" bằng layout hiện tại?`)) return;
        try {
            const data = getLayoutData();
            const w = parseInt((layout.style.width || '').replace('px','')) || 10000;
            const h = parseInt((layout.style.height || '').replace('px','')) || 10000;
            const body = { id, name, description: '', layout_json: data, canvas_w: w, canvas_h: h };

            const resp = await fetch(`${LAYOUT_API}?action=save_layout`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify(body)
            });
            const j = await resp.json();
            if (!resp.ok || j.error) throw new Error(j.error || `HTTP ${resp.status}`);

            currentLayoutId = id;
            currentLayoutName = name;
            alert(`Đã ghi đè: #${id} - ${name}`);
            reloadLayouts();
        } catch (e) {
            alert('Overwrite thất bại: ' + e.message);
        }
    }

    async function deleteLayout(id, name) {
        if (!confirm(`Delete Layout #${id} - "${name}"?`)) return;
        try {
            const resp = await fetch(`${LAYOUT_API}?action=delete_layout`, {
            method: 'POST',
            headers: getAuthHeaders(),
            body: JSON.stringify({ id })
            });
            const j = await resp.json();
            if (!resp.ok || j.error) throw new Error(j.error || `HTTP ${resp.status}`);
            alert(`Đã xoá #${id}`);
            reloadLayouts();
        } catch (e) {
            alert('Delete thất bại: ' + e.message);
        }
    }

    function initializeLayout() {
        layout.classList.add('show-grid');
    }

    initializeLayout();
    setInterval(refreshData, 30000);
</script>

</body>
</html>