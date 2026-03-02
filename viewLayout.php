<?php
// require_once __DIR__ . '/helper/jwt.php';
// require_once __DIR__ . '/helper/auth_helper.php';

// $token = $_COOKIE['ems_token'] ?? null;
// if (!$token) {
//   $redirect = $_SERVER['REQUEST_URI'];
//   header('Location: backend/login.php?redirect=' . urlencode($redirect), true, 302);
//   exit;
// }
// function _jwt_payload_noverify($jwt) {
//   $parts = explode('.', $jwt);
//   if (count($parts) < 2) return null;
//   $b = strtr($parts[1], '-_', '+/');
//   $b .= str_repeat('=', (4 - strlen($b) % 4) % 4);
//   $obj = json_decode(base64_decode($b), true);
//   return is_array($obj) ? $obj : null;
// }
// $pl = _jwt_payload_noverify($token);
// if ($pl && isset($pl['exp']) && time() >= (int)$pl['exp']) {
//   setcookie('ems_token','',[
//     'expires'=>time()-3600,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']),
//     'httponly'=>true,'samesite'=>'Lax'
//   ]);
//   header('Location: backend/login.php?redirect=' . urlencode($_SERVER['REQUEST_URI']), true, 302);
//   exit;
// }
// setcookie('ems_token', $token, [
//   'expires'  => $pl['exp'] ?? (time()+3600),
//   'path'     => '/',
//   'secure'   => !empty($_SERVER['HTTPS']),
//   'httponly' => true,
//   'samesite' => 'Lax',
// ]);
?>
<!DOCTYPE html>
<html lang="vi">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<link rel="icon" type="image/png" href="image/icon2.png">
<title>Factory Layout Viewer</title>
<style>
*{margin:0;padding:0;box-sizing:border-box}
body{background:linear-gradient(135deg,#f5f7fa 0%,#c3cfe2 100%);overflow:hidden;font-family:'Segoe UI',Tahoma,Geneva,Verdana,sans-serif}

/* CANVAS */
#canvas-container{position:fixed;left:0;top:0;right:0;height:100vh;overflow:auto;background:linear-gradient(135deg,#f5f7fa 0%,#e8ecf1 100%)}
#factory-layout{width:20000px;height:10000px;background:#fff;position:relative;box-shadow:0 0 50px rgba(58,140,255,.1);transform-origin:0 0;--zoom:1;--zoomInv:1;--browserInv:1}
#factory-layout.show-grid{
  background-image:
    repeating-linear-gradient(0deg,transparent,transparent 49px,hsla(215, 100%, 61%, 0.10) 49px,rgba(58,140,255,.1) 50px),
    repeating-linear-gradient(90deg,transparent,transparent 49px,rgba(58,140,255,.1) 49px,rgba(58,140,255,.1) 50px);
  background-size:50px 50px;
}

/* CONTROL PANEL (chỉ vài nút xem) */
#control-panel{
  position:fixed;right:20px;top:20px;background:#fff;border:1px solid rgba(58,140,255,.2);border-radius:16px;
  padding:16px;z-index:1000;box-shadow:0 8px 30px rgba(58,140,255,.15);min-width:220px;
   display: inline-block;          /* quan trọng để width bám nội dung */
  width: 280px;                   /* đặt chiều rộng khi mở (tuỳ chỉnh) */
  min-width: 0;                   /* bỏ ép min-width cũ 220px */
  transition: width .25s ease, padding .2s ease;
}

/* Header: tiêu đề co/ẩn mượt khi thu */
.panel-title{
  white-space: nowrap;
  overflow: hidden;
  display: inline-block;
  max-width: 140px;               /* khi mở */
  transition: max-width .2s ease, opacity .2s ease, margin .2s ease;
}

/* Body thu ngang như bạn đã làm (scaleX) */
#control-panel .panel-body{
  transform-origin: right center;
  transform: scaleX(1);
  transition: transform .25s ease, opacity .2s ease;
  opacity: 1;
}

/* Trạng thái collapsed: container THU GỌN THỰC SỰ */
#control-panel.collapsed{
  width: auto !important;
  min-width: 0 !important;
  padding: 0 !important;
  background: transparent !important;
  border: 0 !important;
  box-shadow: none !important;
}
#control-panel.collapsed .panel-header{
  margin: 0 !important;
  padding: 0 !important;
  display: flex !important;
  align-items: center !important;
  justify-content: flex-end !important;
}

/* Ẩn tiêu đề khi collapsed để đỡ chiếm ngang */
#control-panel.collapsed .panel-title{
  display: none !important;
  max-width: 0 !important;
  opacity: 0 !important;
  margin: 0 !important;
}

/* Ẩn phần body như cũ */
#control-panel.collapsed .panel-body{
  /* display: none !important;          đơn giản, gọn */

     transform: scaleX(0); opacity:0; max-height:0; margin:0; padding:0;
}
#control-panel.collapsed .panel-toggle{
  width: 36px !important;
  height: 36px !important;
  border-radius: 10px !important;
  background: #eef5ff !important;
  color: #3A8CFF !important;
  position: relative !important;
  box-shadow: 0 1px 4px rgba(58,140,255,.15) !important;
}
#control-panel .panel-toggle{ position: relative; }
#control-panel .panel-toggle .icon{
  position: absolute; top: 50%; left: 50%;
  transform: translate(-50%, -50%);
  transition: opacity .15s ease;
}
/* Nút icon định vị đẹp khi container thu */
.panel-toggle{ position: relative; }
.panel-toggle .icon{
  position: absolute; top:50%; left:50%; transform: translate(-50%,-50%);
  transition: opacity .15s ease;
}
#control-panel.collapsed .panel-toggle .icon-right{ opacity:0; }
#control-panel.collapsed .panel-toggle .icon-left { opacity:1; }
.control-btn {
  display:flex;
  align-items:center;
  justify-content:center;
  gap: 8px;
  text-align: center;
  text-decoration: none ;
  width:100%;
  padding:12px 16px;
  margin:6px 0;
  background:linear-gradient(135deg,#3A8CFF,#3078DC);
  color:#fff;
  border:none;
  border-radius:10px;
  cursor:pointer;
  font-weight:600;
  font-size:13px;
  box-shadow:0 4px 12px rgba(58,140,255,.3)};
.control-btn.secondary{background:#fff;color:#3A8CFF;border:1px solid rgba(58,140,255,.35)}
.control-btn:disabled{opacity:.6;cursor:not-allowed}

/* ZOOM (đặt trong control-panel) */
.zoom-group{margin-top:10px;padding-top:10px;border-top:1px dashed rgba(58,140,255,.2)}
.zoom-title{font-weight:700;color:#3A8CFF;font-size:12px;margin-bottom:8px}
.zoom-bar{margin-left: 25px; display:flex;align-items:center;gap:8px}
.zoom-btn{width:45px;height:45px;background:linear-gradient(135deg,#3A8CFF,#3078DC);color:#fff;border:none;border-radius:10px;cursor:pointer;font-size:20px;font-weight:bold}
#zoom-level{text-align:center;color:#3A8CFF;font-size:13px;font-weight:700;margin:0 4px}
/* Header + nút toggle */
/* Header + nút toggle */
#control-panel.has-toggle { padding-top: 10px; }
#control-panel .panel-header{
  display:flex; align-items:center; justify-content:space-between;
  margin-bottom:8px;
}
.panel-title{ font-weight:800; color:#3A8CFF; font-size:12px; letter-spacing:.3px; }

/* Nút icon chevron */
.panel-toggle{
  width:28px; height:28px; border:none; border-radius:8px; cursor:pointer;
  background:#eef5ff; color:#3A8CFF; font-weight:900; line-height:1;
  display:flex; align-items:center; justify-content:center;
  box-shadow:0 1px 4px rgba(58,140,255,.15);
}
.panel-toggle .icon{ position:absolute; opacity:1; transition:opacity .15s ease; }
.panel-toggle .icon-left{ opacity:0; }         /* mặc định ẩn icon mở rộng */

/* Body thu/phóng theo CHIỀU NGANG */
#control-panel .panel-body{
  transform-origin: right center;     /* thu từ trái → phải (neo bên phải) */
  transform: scaleX(1);
  transition: transform .25s ease, opacity .2s ease;
  opacity: 1;
  will-change: transform, opacity;
}

/* Khi collapsed: co ngang lại về bên phải */
#control-panel.collapsed .panel-body{
  transform: scaleX(0);
  opacity: 0;
  pointer-events: none;
}

/* Đổi icon theo trạng thái:
   - Expanded: hiện chevron-right (▶) = Thu gọn
   - Collapsed: hiện chevron-left (◀)  = Mở rộng */
#control-panel.collapsed .panel-toggle .icon-right{ opacity:0; }
#control-panel.collapsed .panel-toggle .icon-left{ opacity:1; }





/* ZOOM */
/* #zoom-controls{position:fixed;right:20px;bottom:80px;background:#fff;border:1px solid rgba(58,140,255,.2);border-radius:16px;padding:12px;z-index:1000;display:flex;flex-direction:column;gap:8px;box-shadow:0 8px 30px rgba(58,140,255,.15)}
.zoom-btn{width:45px;height:45px;background:linear-gradient(135deg,#3A8CFF,#3078DC);color:#fff;border:none;border-radius:10px;cursor:pointer;font-size:20px;font-weight:bold}
#zoom-level{text-align:center;color:#3A8CFF;font-size:13px;font-weight:700;margin:5px 0} */

/* COORD */
#coords-display{position:fixed;left:20px;bottom:20px;background:#fff;color:#3A8CFF;padding:10px 16px;border-radius:12px;font-family:'Courier New',monospace;font-size:13px;border:1px solid rgba(58,140,255,.2);z-index:1000;font-weight:600}

/* MACHINE (không handle/không delete ở viewer) */
.machine{position:absolute;border:3px solid #3A8CFF;background:#fff;display:flex;flex-direction:column;align-items:center;justify-content:center;font-weight:600;border-radius:8px;border-width:calc(3px * var(--zoomInv) * var(--browserInv));transition:box-shadow .2s}
.machine .icon{font-size:clamp(16px,calc(var(--scale,1)*28px*var(--zoomInv)*var(--browserInv)),40px)}
.machine .text{text-align:center;color:#333;line-height:1.2;font-size:clamp(10px,calc(var(--scale,1)*11px*var(--zoomInv)*var(--browserInv)),24px)}
.label{font-size:clamp(10px,calc(13px*var(--zoomInv)*var(--browserInv)),22px);color:#3A8CFF;background:#fff;border:2px solid #3A8CFF;border-radius:8px;padding:6px 10px}

/* TYPES */
.mold{border-color:#3A8CFF;background:linear-gradient(135deg,rgba(58,140,255,.18),rgba(58,140,255,.28))}
.tufting{border-color:#10B981;background:linear-gradient(135deg,rgba(16,185,129,.18),rgba(16,185,129,.28))}
.blister{border-color:#F59E0B;background:linear-gradient(135deg,rgba(245,158,11,.18),rgba(245,158,11,.28))}
.ap{border-color:#6366F1;background:linear-gradient(135deg,rgba(99,102,241,.18),rgba(99,102,241,.28))}
.wall{background:#333}

/* STATUS OVERRIDE */
.machine.status-normal{border-color:#10B981!important;background:linear-gradient(135deg,rgba(16,185,129,.18),rgba(16,185,129,.28))!important}
.machine.status-breached,.machine.status-break{border-color:#EF4444!important;background:linear-gradient(135deg,rgba(239,68,68,.18),rgba(239,68,68,.28))!important}
.machine.status-disconnected{border-color:#9CA3AF!important;background:linear-gradient(135deg,rgba(156,163,175,.18),rgba(156,163,175,.28))!important}
.machine.status-unknown{border-color:#A3A3A3!important;background:linear-gradient(135deg,rgba(163,163,163,.12),rgba(163,163,163,.2))!important}

/* MODAL */
.modal{position:fixed;inset:0;display:none;z-index:3000}
.modal.show{display:block}
.modal-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.25);backdrop-filter:blur(2px)}
.modal-card{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);width:560px;max-width:calc(100vw - 40px);background:#fff;border-radius:16px;box-shadow:0 20px 60px rgba(58,140,255,.25);border:1px solid rgba(58,140,255,.2);overflow:hidden}
.modal-header{display:flex;justify-content:space-between;align-items:center;padding:16px 18px;background:linear-gradient(135deg,#3A8CFF,#3078DC);color:#fff}
.modal-title{font-size:16px;font-weight:700}
.modal-subtitle{font-size:12px;opacity:.9;margin-top:2px}
.modal-close{border:none;background:rgba(255,255,255,.2);color:#fff;width:32px;height:32px;border-radius:8px;font-size:18px;cursor:pointer}
.modal-body{padding:16px 18px;max-height:60vh;overflow:auto}
.kv{display:flex;justify-content:space-between;padding:8px 0;border-bottom:1px dashed rgba(58,140,255,.15);font-size:13px}
.kv span{color:#3A8CFF}
.kv b{color:#333;font-weight:700}
.badge{padding:2px 8px;border-radius:999px;font-weight:700;font-size:12px;border:1px solid transparent}
.badge.normal{background:#e9f2ff;color:#2962ff;border-color:#bcd3ff}
.badge.breached{background:#ffecec;color:#d63031;border-color:#ffb3b3}
.badge.disconnected{background:#f0f1f2;color:#7f8c8d;border-color:#dfe4ea}

/* Layout picker modal (viewer: chỉ Open) */
.property-input{width:100%;padding:10px 12px;background:rgba(58,140,255,.05);border:1px solid rgba(58,140,255,.2);border-radius:8px;font-size:13px}
.property-btn{padding:8px 14px;background:linear-gradient(135deg,#3A8CFF,#3078DC);color:#fff;border:none;border-radius:8px;font-weight:700;cursor:pointer;font-size:12px}
.control-btn a {
  display: block;              /* cho full-area clickable */
  color: inherit;              /* lấy màu của .control-btn */
  text-decoration: none;       /* bỏ gạch chân */
}
.control-btn a:visited {
  color: inherit;              /* tránh màu tím */
}

</style>
<style>
.fs-tip {
  position: fixed; right: 20px; bottom: 20px;
  background: #3A8CFF; color: #fff; padding: 10px 14px;
  border-radius: 12px; box-shadow: 0 8px 20px rgba(58,140,255,.25);
  font: 600 13px/1 'Segoe UI', Tahoma, sans-serif; z-index: 1200;
  cursor: pointer; user-select: none;
}
.fs-tip.hide { display: none; }
</style>

<div id="fs-tip" class="fs-tip">Tap to open full screen ⤢</div>

<script>
const fsTip = document.getElementById('fs-tip');
function hideFsTip(){ if (fsTip) fsTip.classList.add('hide'); }
if (fsTip) {
  fsTip.addEventListener('click', async () => {
    try { await enterFullscreen(); hideFsTip(); } catch {}
  });
  document.addEventListener('fullscreenchange', () => {
    if (isFullscreen()) hideFsTip();
  });
}
</script>
</head>
<body>

  <div id="control-panel" class="has-toggle collapsed">
    <div class="panel-header">
      <span class="panel-title">Controls</span>

      <!-- Nút toggle icon SVG -->
      <button id="panel-toggle" class="panel-toggle" aria-label="Collapse/Expand" aria-expanded="true">
        <!-- Chevron Right (thu gọn) -->
        <svg class="icon icon-right" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
          <path d="M9 6l6 6-6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <!-- Chevron Left (mở rộng) -->
        <svg class="icon icon-left" viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
          <path d="M15 6l-6 6 6 6" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
      </button>
    </div>

    <div class="panel-body">
      <button class="control-btn secondary" id="btn-grid">
        📐 Grid: <span id="grid-status">ON</span>
      </button>

      <a href="index.html" class="control-btn control-link-btn">
        EMS Monitoring
      </a>

      <button class="control-btn" onclick="refreshData()">🔄 Refresh data</button>
      <button class="control-btn" id="btn-fullscreen">⤢ Fullscreen</button>
      <div class="zoom-group">
        <div class="zoom-title">Zoom</div>
        <div class="zoom-bar">
          <button class="zoom-btn" onclick="zoomIn()">+</button>
          <div id="zoom-level">100%</div>
          <button class="zoom-btn" onclick="zoomOut()">−</button>
          <button class="zoom-btn" onclick="resetZoom()" style="font-size:16px">⊙</button>
        </div>
      </div>
    </div>
  </div>




<!-- COORDS (chỉ hiển thị) -->
<div id="coords-display">X: 0, Y: 0 | Zoom: 100%</div>

<!-- LAYOUT PICKER MODAL (viewer: không overwrite/delete) -->
<div id="layout-modal" class="modal">
  <div class="modal-backdrop" onclick="closeLayoutModal()"></div>
  <div class="modal-card" style="width:720px">
    <div class="modal-header">
      <div>
        <div class="modal-title">Layout Library</div>
        <div class="modal-subtitle">Chọn layout từ cơ sở dữ liệu</div>
      </div>
      <button class="modal-close" onclick="closeLayoutModal()">×</button>
    </div>
    <div class="modal-body">
      <div style="display:flex;gap:8px;margin-bottom:10px">
        <input id="layout-search" class="property-input" placeholder="Tìm theo tên/mô tả..." oninput="debouncedSearchLayouts()" />
        <button class="property-btn" onclick="reloadLayouts()">Tìm</button>
      </div>
      <div id="layout-list"></div>
    </div>
  </div>
</div>

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
      <div class="kv"><span>Ping (ms)</span><b id="dm-ping_ms">—</b></div>
      <div class="kv"><span>Last updated</span><b id="dm-last_updated">—</b></div>
    </div>
  </div>
</div>

<!-- CANVAS -->
<div id="canvas-container">
  <div id="factory-layout"></div>
</div>

<script>
/* ====== CONSTS ====== */
const layout = document.getElementById('factory-layout');
const coordsDisplay = document.getElementById('coords-display');
const PROCESS_TYPES = new Set(['mold','tuft','tufting','blister','ap']);
const isProcessEl = (el) => !!el && PROCESS_TYPES.has((el.dataset.process || '').toLowerCase());
const API_URL = 'api.php';
const LAYOUT_API = 'layout_api.php';
const AP_STATUS_API = '/web_develop/ems/backend/ap_status_api.php';

/* ====== STATE (viewer) ====== */
let elements = [];
let elementCounter = 0;
let currentZoom = 0.6;
const SNAP = 50;

/* ====== Grid toggle ====== */
document.getElementById('btn-grid').onclick = () => {
  const on = layout.classList.toggle('show-grid');
  document.getElementById('grid-status').textContent = on ? 'ON' : 'OFF';
};
(function(){
  const PANEL_KEY = 'factory_layout_panel_collapsed_h';
  const panel = document.getElementById('control-panel');
  const btn   = document.getElementById('panel-toggle');

  // Khôi phục: nếu chưa có key -> mặc định collapsed=true
  let pref = null;
  try { pref = localStorage.getItem(PANEL_KEY); } catch {}
  const collapsed = (pref === null) ? true : (pref === '1');

  panel.classList.toggle('collapsed', collapsed);
  btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');

  // Click toggle
  btn.addEventListener('click', () => {
    const willCollapse = !panel.classList.contains('collapsed');
    panel.classList.toggle('collapsed', willCollapse);
    btn.setAttribute('aria-expanded', willCollapse ? 'false' : 'true');
    try { localStorage.setItem(PANEL_KEY, willCollapse ? '1' : '0'); } catch {}
  });
})();
/* ====== Zoom ====== */
function applyZoom(){
  layout.style.transform = `scale(${currentZoom})`;
  layout.style.setProperty('--zoom', currentZoom);
  layout.style.setProperty('--zoomInv', (1/currentZoom).toFixed(4));
  document.getElementById('zoom-level').textContent = Math.round(currentZoom*100) + '%';
}
function zoomIn(){ currentZoom = Math.min(currentZoom + 0.1, 3); applyZoom(); }
function zoomOut(){ currentZoom = Math.max(currentZoom - 0.1, 0.3); applyZoom(); }
function resetZoom(){ currentZoom = 1; applyZoom(); }
applyZoom();
function enterFullscreen() {
  const el = document.documentElement; // toàn trang
  if (el.requestFullscreen) return el.requestFullscreen();
  if (el.webkitRequestFullscreen) return el.webkitRequestFullscreen();
  if (el.msRequestFullscreen) return el.msRequestFullscreen();
}

function exitFullscreen() {
  if (document.exitFullscreen) return document.exitFullscreen();
  if (document.webkitExitFullscreen) return document.webkitExitFullscreen();
  if (document.msExitFullscreen) return document.msExitFullscreen();
}

function isFullscreen() {
  return !!(document.fullscreenElement || document.webkitFullscreenElement || document.msFullscreenElement);
}

// Nút trong control panel
const btnFS = document.getElementById('btn-fullscreen');
if (btnFS) {
  btnFS.addEventListener('click', async () => {
    try {
      if (!isFullscreen()) { await enterFullscreen(); }
      else { await exitFullscreen(); }
    } catch (e) {
      console.warn('Fullscreen error:', e);
      alert('Trình duyệt chặn auto fullscreen. Hãy thử nhấn phím F11 hoặc bật quyền fullscreen.');
    }
  });
}

// ===== Vào fullscreen ngay ở LẦN TƯƠNG TÁC ĐẦU TIÊN =====
// (click/ chạm / phím bất kỳ)


// function requestFSOnce() {
//   document.removeEventListener('pointerdown', requestFSOnce);
//   document.removeEventListener('keydown', requestFSOnce);
//   if (!isFullscreen()) enterFullscreen().catch(()=>{ /* bị chặn thì thôi */ });
// }
// // Đăng ký lắng nghe một lần
// document.addEventListener('pointerdown', requestFSOnce, { once: true, passive: true });
// document.addEventListener('keydown', requestFSOnce, { once: true });



// (Tuỳ chọn) nếu quay lại tab (visibilitychange) mà thoát fullscreen, có thể nhắc lại
document.addEventListener('fullscreenchange', () => {
  // Cập nhật label nút (nếu có)
  if (btnFS) btnFS.textContent = isFullscreen() ? '⤢ Exit fullscreen' : '⤢ Fullscreen';
});
/* ====== Coords HUD ====== */
document.addEventListener('mousemove', (e) => {
  const rect = layout.getBoundingClientRect();
  const x = (e.clientX - rect.left) / currentZoom + layout.parentElement.scrollLeft;
  const y = (e.clientY - rect.top) / currentZoom + layout.parentElement.scrollTop;
  coordsDisplay.textContent = `X: ${Math.round(x)}, Y: ${Math.round(y)} | Zoom: ${Math.round(currentZoom*100)}%`;
});

/* ====== Helpers ====== */
function updateScale(el){
  const scale = Math.min(parseFloat(el.style.width)/parseFloat(el.dataset.origWidth),
                         parseFloat(el.style.height)/parseFloat(el.dataset.origHeight));
  el.style.setProperty('--scale', scale);
}
function applyStatusStyle(el, rawStatus){
  if (!el || el.classList.contains('wall')) return;
  const s0 = String(rawStatus || '').trim().toUpperCase();
    const map = { 
    NORMAL:'NORMAL', OK:'NORMAL', ONLINE:'NORMAL',
    BREACHED:'BREACHED',
    DISCONNECTED:'DISCONNECTED', DISCONNECT:'DISCONNECTED', OFFLINE:'DISCONNECTED'
  };
  const status = map[s0] || 'UNKNOWN';
  el.classList.remove('status-normal','status-breached','status-break','status-disconnected','status-unknown');
  switch(status){
    case 'NORMAL': el.classList.add('status-normal'); break;
    case 'BREACHED': el.classList.add('status-breached'); break;
    case 'DISCONNECTED': el.classList.add('status-disconnected'); break;
    default: el.classList.add('status-unknown'); break;
  }
  el.dataset.status = status;
}

/* ====== Create element (viewer-safe, không thêm handle/drag) ====== */
function createElement(type, x, y, width, height, text='', rotation=0, flipH=1, flipV=1, process='', deviceId=''){
  const el = document.createElement('div');
  el.className = `machine ${type}`;
  el.dataset.type = type;
  el.style.left = Math.round(x) + 'px';
  el.style.top  = Math.round(y) + 'px';
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

  // process xác định phần tử nào gọi API
  if (type === 'wall'){
    el.dataset.process = '';
    el.dataset.deviceId = '';
  } else {
    const t = (type || '').toLowerCase();
    const p = (process || '').toLowerCase();
    if (PROCESS_TYPES.has(p)) el.dataset.process = p;
    else if (PROCESS_TYPES.has(t)) el.dataset.process = t;
    else el.dataset.process = '';
  }

  const icons = {
    'tank':'🛢️','equipment':'⚙️','workstation':'👷','at1900':'🔧',
    'robot-arm':'🦾','cnc-machine':'🔩','assembly-line':'⚡','agv':'🚛',
    'mold':'','tufting':'','blister':'','ap':'📶',
    'office':'🏢','stairs':'🪜','conveyor':'→','pipe':'━','wall':'▬','label':''
  };

  if (type === 'label'){
    el.textContent = text || 'label';
  } else {
    if (icons[type]){
      const iconDiv = document.createElement('div');
      iconDiv.className = 'icon';
      iconDiv.textContent = icons[type];
      el.appendChild(iconDiv);
    }
    const textDiv = document.createElement('div');
    textDiv.className = 'text';
    textDiv.textContent = text || `${type}#${elementCounter}`;
    el.appendChild(textDiv);
  }

  // auto deviceId từ #ID trong text
  let deviceIdAuto = deviceId;
  if (!deviceIdAuto){
    const contentSource = type === 'label' ? (el.textContent || '') : ((el.querySelector('.text')?.textContent) || text || '');
    const m = String(contentSource).trim().match(/#\s*([A-Za-z0-9_-]+)/);
    if (m) deviceIdAuto = m[1];
  }
  el.dataset.deviceId = deviceIdAuto || '';

  // dblclick mở modal nếu có process & deviceId
  el.addEventListener('dblclick', async () => {
    if (!isProcessEl(el)) return;
    if (!el.dataset.deviceId) { alert('There is no Device ID for this element.'); return; }
    if (!el._detail) { await fetchDataForElement(el, el.dataset.deviceId, el.dataset.process || 'mold'); }
    openDeviceModal(el);
  });

  layout.appendChild(el);
  elements.push({ el, type, id: elementCounter++ });
  applyStatusStyle(el, el.dataset.status || 'UNKNOWN');
  updateScale(el);
  return el;
}

/* ====== Restore layout từ DB JSON ====== */
function restoreLayout(data){
  // clear
  elements.forEach(it => it.el.remove());
  elements = [];
  elementCounter = 0;

  const PROCESS_UI = ['mold','tuft','tufting','blister','ap'];

  (data || []).forEach(it=>{
    const typeStr = String(it.type || '').toLowerCase();
    const fixedProcess =
      (it.process && PROCESS_UI.includes(String(it.process).toLowerCase()))
        ? String(it.process).toLowerCase()
        : (PROCESS_UI.includes(typeStr) ? typeStr : '');

    const processToUse = (typeStr === 'wall') ? '' : fixedProcess;

    const el = createElement(
      it.type, it.x, it.y, it.width, it.height,
      it.text, it.rotation, it.flipH, it.flipV,
      processToUse, it.deviceId || ''
    );

    if (el){
      el.dataset.origWidth = it.origWidth;
      el.dataset.origHeight = it.origHeight;
      updateScale(el);
    }
  });
}

/* ====== Modal device ====== */
function openDeviceModal(el){
  const d = el._detail || {};
  document.getElementById('dm-title').textContent = d.mold_id || el.dataset.deviceId || '—';
  document.getElementById('dm-subtitle').textContent = (d.family ? d.family + ' • ' : '') + (d.process || (el.dataset.process||'').toUpperCase());
  const set = (id, v, suffix='') => document.getElementById(id).textContent = (v ?? v===0 ? (suffix && typeof v==='number' ? (v+suffix) : v) : '—');
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
  set('dm-ping_ms', d.ping_ms != null ? Number(d.ping_ms).toFixed(1) : null, ' ms');

  const badge = document.getElementById('dm-status');
  const s = (d.status || 'UNKNOWN').toUpperCase();
  badge.textContent = s;
  badge.classList.remove('normal','breached','disconnected');
  if (s==='BREACHED') badge.classList.add('breached');
  else if (s==='DISCONNECTED') badge.classList.add('disconnected');
  else badge.classList.add('normal');

  document.getElementById('device-modal').classList.add('show');
}
function closeDeviceModal(){ document.getElementById('device-modal').classList.remove('show'); }

/* ====== Fetch data ====== */
async function fetchDataForElement(el, deviceId, process){
  if (!el || el.classList.contains('wall')) return;
  if (!PROCESS_TYPES.has(String(process||'').toLowerCase())) return;

  const mapProcess = { tufting:'tuft', tuft:'tuft', mould:'mold', mold:'mold', 'end-rounding':'end-rounding', ap:'ap' };
  const pUI = (process || 'mold').toLowerCase();
  const p = mapProcess[pUI] || pUI;
    if (p === 'ap'){
    await fetchAPDetail(el, deviceId);   // <= gọi API AP
    return;                              // <= và dừng ở đây
  }

  const url = `${API_URL}?action=get_machine_details&process=${encodeURIComponent(p)}&status=total`;
  try{
    const resp = await fetch(url, { cache: 'no-store' });
    if (!resp.ok) throw new Error(`HTTP ${resp.status} ${resp.statusText}`);

    let dataText = (await resp.text()).trim().replace(/^\uFEFF/, '');
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
      return apiVal.endsWith('-'+devNorm) || apiVal.endsWith('#'+devNorm);
    });

    if (!device && data.length === 0){
      const resp2 = await fetch(`${API_URL}?action=get_machine_details&status=total`, { cache: 'no-store' });
      if (resp2.ok){
        let t = (await resp2.text()).trim().replace(/^\uFEFF/, '');
        let all = [];
        try{ all = JSON.parse(t); }catch{}
        const idKey2 = candidates.find(k => all.length && Object.hasOwn(all[0], k)) || 'device_id';
        device = (all||[]).find(d => {
          const apiVal = norm(d[idKey2]);
          return apiVal === devNorm || apiVal.endsWith('-'+devNorm) || apiVal.endsWith('#'+devNorm);
        });
      }
    }

    const textDiv = el.querySelector('.text');
    if (!textDiv) return;

    if (device){
      const eff = (device.efficiency ?? device.oee ?? device.utilization ?? '');
      const lost = device.lost_time || device.downtime || '';
      const status = (device.status || 'NORMAL').toUpperCase();

      textDiv.innerHTML = `${device.device_id || deviceId}<br>${status}<br>`;
      el.dataset.status = status;

      el._detail = {
        mold_id: device.device_id || device[idKey] || deviceId,
        family: device.family ?? null,
        process: (device.process || p).toString().toUpperCase(),
        mold_cavity: device.mold_cavity != null ? Number(device.mold_cavity) : null,
        actual_cavity: device.actual_cavity != null ? Number(device.actual_cavity) : (device.cavities != null ? Number(device.cavities) : 0),
        capacity_per_hr: device.capacity_per_hr != null ? Number(device.capacity_per_hr) : (device.capacity != null ? Number(device.capacity) : null),
        efficiency: eff != null ? Number(eff) : 0,
        efficiency_lower_limit: device.efficiency_lower_limit != null ? Number(device.efficiency_lower_limit) : null,
        current_cycle: (device.current_cycle != null) ? Number(device.current_cycle)
                        : (p==='mold' ? Number(device.cycle_time ?? 0)
                        : (p==='tuft' ? Number(device.output ?? 0)
                        : Number(device.cyclecount ?? 0))),
        output: device.output != null ? Number(device.output) : 0,
        cyclecount: device.cyclecount != null ? Number(device.cyclecount) : 0,
        target: device.target != null ? Number(device.target) : (device.target_limit != null ? Number(device.target_limit) : null),
        bush_per_cycle: device.brushes_per_cycle != null ? Number(device.brushes_per_cycle) : null,
        hole_per_brush: device.hole_per_brush != null ? Number(device.hole_per_brush) : null,
        upper_limit: device.upper_limit != null ? Number(device.upper_limit) : null,
        lower_limit: device.lower_limit != null ? Number(device.lower_limit) : null,
        total_lost_pcs: device.total_lost_pcs != null ? Number(device.total_lost_pcs) : Number(device.loss_pcs ?? 0),
        lost_time: lost != null ? Number(lost) : 0,
        status: status,
        last_updated: device.last_updated ?? device.total_count_updated_at ?? device.cavity_count_updated_at ?? ''
      };

      applyStatusStyle(el, status);
    } else {
      if (isProcessEl(el)){
        textDiv.innerHTML = `ID: ${deviceId}<br>❌ No Data`;
        applyStatusStyle(el, 'UNKNOWN');
        el._detail = null;
      }
    }
  }catch(err){
    console.error('API error:', err);
    const textDiv = el.querySelector('.text');
    if (textDiv) textDiv.innerHTML = `ID: ${deviceId}<br>⚠️ Error API: ${err.message}`;
    el.style.borderColor = '#e67e22';
  }
}
  async function fetchAPDetail(el, apName, staleMinutes = 10){
    if (!apName) return;

    const url = `${AP_STATUS_API}?names=${encodeURIComponent(apName)}&stale_minutes=${staleMinutes}`;
    const resp = await fetch(url, { cache:'no-store' });
    if (!resp.ok) throw new Error(`HTTP ${resp.status} ${resp.statusText}`);
    const j = await resp.json();

    const info = (j.map && j.map[apName]) || (j.items || []).find(r => (r.name||'') === apName);
    const textDiv = el.querySelector('.text');

    if (!info){
      applyStatusStyle(el, 'DISCONNECTED');
      if (textDiv) textDiv.innerHTML = `${apName}<br>DISCONNECTED`;
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
      mold_id: info.name,
      family: 'Access Point',
      process: 'AP',
      status,
      last_updated: info.last_seen,
      ping_ms: info.ping_ms
    };
  }


/* ====== Refresh all process elements ====== */
function refreshData(){
  elements.forEach(item=>{
    const el = item.el;
    if (item.type === 'wall') return;
    if (!isProcessEl(el)) return;
    const deviceId = el.dataset.deviceId;
    if (deviceId) fetchDataForElement(el, deviceId, el.dataset.process || 'mold');
  });
}

/* ====== Layout picker (viewer) ====== */
let __layoutSearchTimer = null;
// function openLayoutPicker(){
//   document.getElementById('layout-modal').classList.add('show');
//   document.getElementById('layout-search').value = '';
//   reloadLayouts();
// }
function closeLayoutModal(){ document.getElementById('layout-modal').classList.remove('show'); }
function debouncedSearchLayouts(){ clearTimeout(__layoutSearchTimer); __layoutSearchTimer = setTimeout(reloadLayouts, 300); }
async function reloadLayouts(){
  const q = document.getElementById('layout-search').value.trim();
  const url = `${LAYOUT_API}?action=list_layouts` + (q ? `&q=${encodeURIComponent(q)}` : '');
  try{
    const resp = await fetch(url,{cache:'no-store'});
    const j = await resp.json();
    if (!resp.ok || j.error) throw new Error(j.error || `HTTP ${resp.status}`);
    renderLayoutList(j.items || []);
  }catch(e){
    document.getElementById('layout-list').innerHTML = `<div style="color:#e11;">Lỗi tải danh sách: ${e.message}</div>`;
  }
}
function renderLayoutList(items){
  const box = document.getElementById('layout-list');
  if (!items.length){ box.innerHTML = '<div>Không có layout nào.</div>'; return; }
  const rows = items.map(it=>{
    const title = it.name.replace(/</g,'&lt;');
    const desc  = (it.description || '').replace(/</g,'&lt;');
    const meta  = `${it.canvas_w||'-'}×${it.canvas_h||'-'} • ${it.created_by||'-'} • ${new Date(it.updated_at).toLocaleString()}`;
    return `
      <div style="border:1px solid rgba(58,140,255,.2);border-radius:12px;padding:12px;margin:8px 0;display:flex;justify-content:space-between;gap:10px;">
        <div>
          <div style="font-weight:700;color:#3A8CFF;">${title}</div>
          <div style="font-size:12px;color:#555;">${desc || '<i>No description</i>'}</div>
          <div style="font-size:12px;color:#888;margin-top:4px;">${meta}</div>
        </div>
        <div style="display:flex;align-items:center;gap:6px;white-space:nowrap;">
          <button class="property-btn" onclick="loadLayoutById(${it.id}, '${title.replace(/'/g,'&#39;')}')">Open</button>
        </div>
      </div>`;
  }).join('');
  box.innerHTML = rows;
}
async function loadLayoutById(id, name){
  try{
    const resp = await fetch(`${LAYOUT_API}?action=get_layout&id=${id}`,{cache:'no-store'});
    const j = await resp.json();
    if (!resp.ok || j.error) throw new Error(j.error || `HTTP ${resp.status}`);

    if (j.canvas_w && j.canvas_h){
      layout.style.width = j.canvas_w + 'px';
      layout.style.height = j.canvas_h + 'px';
    }
    restoreLayout(j.layout_json || []);
    closeLayoutModal();
    // gọi API lần đầu ngay sau khi load
    refreshData();
    alert(`Đã mở layout: #${j.id} - ${j.name}`);
  }catch(e){
    alert('Load thất bại: ' + e.message);
  }
}

/* ====== Auto-refresh mỗi 30s ====== */
setInterval(refreshData, 30000);

/* ====== Init ====== */
layout.classList.add('show-grid');

// (tuỳ chọn) auto open if ?id=... trên URL
(function tryAutoOpen(){
  const m = location.search.match(/[?&]id=(\d+)/);
  if (m){ loadLayoutById(m[1], ''); }
})();

  const __SEED_LAYOUT__ = [
{
    "type": "wall",
    "x": 383,
    "y": 140,
    "width": 1783,
    "height": 8,
    "origWidth": 200,
    "origHeight": 8,
    "text": "wall#0",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "0"
  },
  {
    "type": "wall",
    "x": 384,
    "y": 686,
    "width": 1783,
    "height": 8,
    "origWidth": 200,
    "origHeight": 8,
    "text": "wall#0",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "0"
  },
  {
    "type": "wall",
    "x": 82,
    "y": 790,
    "width": 2239,
    "height": 8,
    "origWidth": 200,
    "origHeight": 8,
    "text": "wall#2",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "0"
  },
  {
    "type": "wall",
    "x": 86,
    "y": 1290,
    "width": 2234,
    "height": 8,
    "origWidth": 200,
    "origHeight": 8,
    "text": "wall#3",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "0"
  },
  {
    "type": "wall",
    "x": 111,
    "y": 415,
    "width": 556,
    "height": 8,
    "origWidth": 200,
    "origHeight": 8,
    "text": "wall#4",
    "rotation": 270,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "4"
  },
  {
    "type": "wall",
    "x": 1884,
    "y": 418,
    "width": 557,
    "height": 8,
    "origWidth": 200,
    "origHeight": 8,
    "text": "wall#4",
    "rotation": 270,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "4"
  },
  {
    "type": "wall",
    "x": -162,
    "y": 1038,
    "width": 502,
    "height": 8,
    "origWidth": 200,
    "origHeight": 8,
    "text": "wall#4",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "4"
  },
  {
    "type": "wall",
    "x": 2064,
    "y": 1042,
    "width": 508,
    "height": 8,
    "origWidth": 200,
    "origHeight": 8,
    "text": "wall#4",
    "rotation": 270,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "4"
  },
  {
    "type": "blister",
    "x": 1933,
    "y": 440,
    "width": 44,
    "height": 120,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP04025BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP04025"
  },
  {
    "type": "blister",
    "x": 1883,
    "y": 440,
    "width": 44,
    "height": 171,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP04002BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP04002"
  },
  {
    "type": "blister",
    "x": 1783,
    "y": 440,
    "width": 44,
    "height": 120,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP04009BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP04009"
  },
  {
    "type": "blister",
    "x": 1733,
    "y": 440,
    "width": 44,
    "height": 120,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP04008DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP04008"
  },
  {
    "type": "blister",
    "x": 1633,
    "y": 440,
    "width": 44,
    "height": 120,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP04003DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP04003"
  },
  {
    "type": "blister",
    "x": 1583,
    "y": 440,
    "width": 44,
    "height": 120,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP04011BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP04011"
  },
  {
    "type": "blister",
    "x": 1533,
    "y": 440,
    "width": 44,
    "height": 120,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP04013BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP04013"
  },
  {
    "type": "blister",
    "x": 1433,
    "y": 440,
    "width": 44,
    "height": 120,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP04020BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP04020"
  },
  {
    "type": "tufting",
    "x": 1234,
    "y": 465,
    "width": 133,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02054BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02054"
  },
  {
    "type": "tufting",
    "x": 1234,
    "y": 515,
    "width": 148,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02058DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02058"
  },
  {
    "type": "tufting",
    "x": 1034,
    "y": 515,
    "width": 142,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02059BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02059"
  },
  {
    "type": "tufting",
    "x": 1034,
    "y": 465,
    "width": 137,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02056BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02056"
  },
  {
    "type": "tufting",
    "x": 483,
    "y": 390,
    "width": 40,
    "height": 171,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02053DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02053"
  },
  {
    "type": "tufting",
    "x": 833,
    "y": 465,
    "width": 145,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02048BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02048"
  },
  {
    "type": "tufting",
    "x": 683,
    "y": 515,
    "width": 137,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02007NORMAL",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02007"
  },
  {
    "type": "tufting",
    "x": 683,
    "y": 465,
    "width": 137,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02008BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02008"
  },
  {
    "type": "tufting",
    "x": 833,
    "y": 515,
    "width": 151,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02049DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02049"
  },
  {
    "type": "tufting",
    "x": 533,
    "y": 390,
    "width": 40,
    "height": 174,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02062DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02062"
  },
  {
    "type": "tufting",
    "x": 583,
    "y": 390,
    "width": 40,
    "height": 174,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02061DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02061"
  },
  {
    "type": "tufting",
    "x": 533,
    "y": 590,
    "width": 40,
    "height": 48,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02038DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02038"
  },
  {
    "type": "tufting",
    "x": 583,
    "y": 590,
    "width": 40,
    "height": 48,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02037DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02037"
  },
  {
    "type": "tufting",
    "x": 683,
    "y": 565,
    "width": 137,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02043DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02043"
  },
  {
    "type": "tufting",
    "x": 833,
    "y": 565,
    "width": 137,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02044DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02044"
  },
  {
    "type": "tufting",
    "x": 1034,
    "y": 565,
    "width": 137,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02051DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02051"
  },
  {
    "type": "tufting",
    "x": 1234,
    "y": 565,
    "width": 137,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02055BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02055"
  },
  {
    "type": "tufting",
    "x": 834,
    "y": 615,
    "width": 137,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02050DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02050"
  },
  {
    "type": "tufting",
    "x": 1034,
    "y": 615,
    "width": 137,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02052BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02052"
  },
  {
    "type": "tufting",
    "x": 1234,
    "y": 615,
    "width": 137,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02057BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02057"
  },
  {
    "type": "mold",
    "x": 533,
    "y": 190,
    "width": 40,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 47❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "47"
  },
  {
    "type": "mold",
    "x": 583,
    "y": 190,
    "width": 40,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC161101DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC161101"
  },
  {
    "type": "mold",
    "x": 683,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 47❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "47"
  },
  {
    "type": "mold",
    "x": 783,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 47❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "47"
  },
  {
    "type": "mold",
    "x": 833,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 47❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "47"
  },
  {
    "type": "mold",
    "x": 933,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 47❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "47"
  },
  {
    "type": "mold",
    "x": 1033,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC150906DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC150906"
  },
  {
    "type": "mold",
    "x": 1133,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC141201DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC141201"
  },
  {
    "type": "mold",
    "x": 1233,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC151012DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC151012"
  },
  {
    "type": "mold",
    "x": 1283,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC161109DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC161109"
  },
  {
    "type": "mold",
    "x": 1383,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC180701DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC180701"
  },
  {
    "type": "mold",
    "x": 1433,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC161110BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC161110"
  },
  {
    "type": "mold",
    "x": 1533,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC151010DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC151010"
  },
  {
    "type": "mold",
    "x": 1583,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC151017DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC151017"
  },
  {
    "type": "mold",
    "x": 1683,
    "y": 190,
    "width": 45,
    "height": 174,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: AC151006❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC151006"
  },
  {
    "type": "mold",
    "x": 1733,
    "y": 190,
    "width": 45,
    "height": 179,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC100902DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC100902"
  },
  {
    "type": "mold",
    "x": 1883,
    "y": 190,
    "width": 45,
    "height": 194,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC120913DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC120913"
  },
  {
    "type": "blister",
    "x": 124,
    "y": 804,
    "width": 138,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP24004DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP24004"
  },
  {
    "type": "blister",
    "x": 336,
    "y": 803,
    "width": 141,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP24003DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP24003"
  },
  {
    "type": "blister",
    "x": 548,
    "y": 802,
    "width": 141,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP24001BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP24001"
  },
  {
    "type": "blister",
    "x": 156,
    "y": 893,
    "width": 44,
    "height": 87,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: VE33001❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VE33001"
  },
  {
    "type": "tufting",
    "x": 104,
    "y": 891,
    "width": 41,
    "height": 137,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT32001BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT32001"
  },
  {
    "type": "tufting",
    "x": 218,
    "y": 890,
    "width": 41,
    "height": 48,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02045DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02045"
  },
  {
    "type": "tufting",
    "x": 214,
    "y": 943,
    "width": 41,
    "height": 42,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT32002DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT32002"
  },
  {
    "type": "blister",
    "x": 273,
    "y": 891,
    "width": 44,
    "height": 87,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT22002BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT22002"
  },
  {
    "type": "blister",
    "x": 323,
    "y": 890,
    "width": 44,
    "height": 73,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 72❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "72"
  },
  {
    "type": "tufting",
    "x": 333,
    "y": 990,
    "width": 41,
    "height": 51,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 73❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "73"
  },
  {
    "type": "tufting",
    "x": 379,
    "y": 884,
    "width": 41,
    "height": 102,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02042BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02042"
  },
  {
    "type": "tufting",
    "x": 449,
    "y": 890,
    "width": 41,
    "height": 93,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: VE13003❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VE13003"
  },
  {
    "type": "tufting",
    "x": 475,
    "y": 991,
    "width": 41,
    "height": 48,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02040DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tufting",
    "deviceId": "VT02040"
  },
  {
    "type": "blister",
    "x": 516,
    "y": 882,
    "width": 44,
    "height": 109,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02060DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT02060"
  },
  {
    "type": "tufting",
    "x": 547,
    "y": 994,
    "width": 41,
    "height": 42,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02039BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT02039"
  },
  {
    "type": "tufting",
    "x": 611,
    "y": 995,
    "width": 41,
    "height": 42,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02011BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT02011"
  },
  {
    "type": "blister",
    "x": 587,
    "y": 877,
    "width": 44,
    "height": 109,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02047BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT02047"
  },
  {
    "type": "blister",
    "x": 664,
    "y": 888,
    "width": 44,
    "height": 109,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02046DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT02046"
  },
  {
    "type": "blister",
    "x": 804,
    "y": 1023,
    "width": 44,
    "height": 109,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT02046DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT02046"
  },
  {
    "type": "blister",
    "x": 871,
    "y": 1035,
    "width": 44,
    "height": 109,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 72❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "72"
  },
  {
    "type": "mold",
    "x": 126,
    "y": 1103,
    "width": 46,
    "height": 159,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 90❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "90"
  },
  {
    "type": "mold",
    "x": 202,
    "y": 1111,
    "width": 46,
    "height": 172,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 90❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "90"
  },
  {
    "type": "mold",
    "x": 265,
    "y": 1109,
    "width": 46,
    "height": 159,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 90❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "90"
  },
  {
    "type": "mold",
    "x": 338,
    "y": 1111,
    "width": 46,
    "height": 159,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 90❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "90"
  },
  {
    "type": "mold",
    "x": 405,
    "y": 1112,
    "width": 46,
    "height": 159,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 90❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "90"
  },
  {
    "type": "mold",
    "x": 481,
    "y": 1108,
    "width": 46,
    "height": 159,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC231005DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC231005"
  },
  {
    "type": "mold",
    "x": 557,
    "y": 1114,
    "width": 46,
    "height": 159,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC231006DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC231006"
  },
  {
    "type": "mold",
    "x": 633,
    "y": 1115,
    "width": 46,
    "height": 159,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 90❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "90"
  },
  {
    "type": "mold",
    "x": 833,
    "y": 1140,
    "width": 46,
    "height": 144,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC110904DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC110904"
  },
  {
    "type": "blister",
    "x": 828,
    "y": 875,
    "width": 90,
    "height": 42,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 106❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "106"
  },
  {
    "type": "mold",
    "x": 933,
    "y": 1040,
    "width": 47,
    "height": 74,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12008DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12008"
  },
  {
    "type": "wall",
    "x": 683,
    "y": 1040,
    "width": 491,
    "height": 5,
    "origWidth": 200,
    "origHeight": 8,
    "text": "wall#105",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "108"
  },
  {
    "type": "mold",
    "x": 933,
    "y": 1140,
    "width": 47,
    "height": 74,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12007DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12007"
  },
  {
    "type": "mold",
    "x": 1031,
    "y": 1122,
    "width": 47,
    "height": 162,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC170802DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC170802"
  },
  {
    "type": "mold",
    "x": 1092,
    "y": 1122,
    "width": 47,
    "height": 162,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: VI11010❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "VI11010"
  },
  {
    "type": "mold",
    "x": 1033,
    "y": 1040,
    "width": 47,
    "height": 74,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 107❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "107"
  },
  {
    "type": "mold",
    "x": 1083,
    "y": 1004,
    "width": 47,
    "height": 74,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 107❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "107"
  },
  {
    "type": "mold",
    "x": 1183,
    "y": 1090,
    "width": 47,
    "height": 162,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC170402DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC170402"
  },
  {
    "type": "mold",
    "x": 1177,
    "y": 913,
    "width": 47,
    "height": 162,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12010DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12010"
  },
  {
    "type": "mold",
    "x": 1244,
    "y": 1101,
    "width": 47,
    "height": 162,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC170401DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC170401"
  },
  {
    "type": "mold",
    "x": 1234,
    "y": 900,
    "width": 47,
    "height": 162,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12009BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12009"
  },
  {
    "type": "mold",
    "x": 1333,
    "y": 1090,
    "width": 47,
    "height": 162,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: VI11006❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "VI11006"
  },
  {
    "type": "mold",
    "x": 1295,
    "y": 901,
    "width": 47,
    "height": 119,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12006DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12006"
  },
  {
    "type": "mold",
    "x": 1383,
    "y": 1090,
    "width": 47,
    "height": 147,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 107❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "107"
  },
  {
    "type": "mold",
    "x": 1440,
    "y": 1115,
    "width": 47,
    "height": 159,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 107❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "107"
  },
  {
    "type": "mold",
    "x": 1533,
    "y": 1090,
    "width": 47,
    "height": 182,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 107❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "107"
  },
  {
    "type": "mold",
    "x": 1583,
    "y": 1090,
    "width": 69,
    "height": 169,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 107❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "107"
  },
  {
    "type": "mold",
    "x": 1683,
    "y": 1090,
    "width": 47,
    "height": 156,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC220905DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC220905"
  },
  {
    "type": "mold",
    "x": 1748,
    "y": 1100,
    "width": 47,
    "height": 156,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC220904DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC220904"
  },
  {
    "type": "mold",
    "x": 1833,
    "y": 1090,
    "width": 47,
    "height": 162,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC220903DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC220903"
  },
  {
    "type": "mold",
    "x": 1893,
    "y": 1100,
    "width": 47,
    "height": 162,
    "origWidth": 150,
    "origHeight": 120,
    "text": "AC220902DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "AC220902"
  },
  {
    "type": "mold",
    "x": 1981,
    "y": 900,
    "width": 47,
    "height": 233,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: VP24005❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VP24005"
  },
  {
    "type": "mold",
    "x": 2033,
    "y": 1140,
    "width": 181,
    "height": 37,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP24001BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP24001"
  },
  {
    "type": "mold",
    "x": 2033,
    "y": 1090,
    "width": 149,
    "height": 35,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VP14001DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "VP14001"
  },
  {
    "type": "mold",
    "x": 1378,
    "y": 912,
    "width": 47,
    "height": 164,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12004DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12004"
  },
  {
    "type": "mold",
    "x": 1439,
    "y": 943,
    "width": 47,
    "height": 124,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12003DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12003"
  },
  {
    "type": "mold",
    "x": 1528,
    "y": 901,
    "width": 47,
    "height": 68,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: VT12012❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12012"
  },
  {
    "type": "mold",
    "x": 1533,
    "y": 990,
    "width": 47,
    "height": 74,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12005DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12005"
  },
  {
    "type": "mold",
    "x": 1733,
    "y": 924,
    "width": 47,
    "height": 68,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 107❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "mold",
    "deviceId": "107"
  },
  {
    "type": "mold",
    "x": 1699,
    "y": 1010,
    "width": 47,
    "height": 74,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: VT12012❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12012"
  },
  {
    "type": "mold",
    "x": 1908,
    "y": 927,
    "width": 47,
    "height": 124,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12001DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12001"
  },
  {
    "type": "mold",
    "x": 1846,
    "y": 893,
    "width": 47,
    "height": 164,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12002DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12002"
  },
  {
    "type": "blister",
    "x": 1890,
    "y": 799,
    "width": 154,
    "height": 41,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 19❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "19"
  },
  {
    "type": "blister",
    "x": 1681,
    "y": 795,
    "width": 174,
    "height": 44,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 19❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "19"
  },
  {
    "type": "blister",
    "x": 1481,
    "y": 800,
    "width": 165,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 19❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "19"
  },
  {
    "type": "blister",
    "x": 1280,
    "y": 801,
    "width": 154,
    "height": 38,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 19❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "19"
  },
  {
    "type": "blister",
    "x": 1033,
    "y": 800,
    "width": 154,
    "height": 36,
    "origWidth": 150,
    "origHeight": 120,
    "text": "ID: 19❌ No Data",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "blister",
    "deviceId": "19"
  },
  {
    "type": "wall",
    "x": 683,
    "y": 740,
    "width": 97,
    "height": 8,
    "origWidth": 200,
    "origHeight": 8,
    "text": "wall#152",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "152"
  },
  {
    "type": "wall",
    "x": 783,
    "y": 740,
    "width": 97,
    "height": 8,
    "origWidth": 200,
    "origHeight": 8,
    "text": "wall#152",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "152"
  },
  {
    "type": "mold",
    "x": 1583,
    "y": 898,
    "width": 47,
    "height": 100,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12001DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12001"
  },
  {
    "type": "mold",
    "x": 1647,
    "y": 987,
    "width": 47,
    "height": 92,
    "origWidth": 150,
    "origHeight": 120,
    "text": "VT12011BREACHED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "tuft",
    "deviceId": "VT12011"
  },
  {
    "type": "conveyor",
    "x": 431,
    "y": 167,
    "width": 1576,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#153",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "153"
  },
  {
    "type": "conveyor",
    "x": 193,
    "y": 411,
    "width": 486,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#154",
    "rotation": 270,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "154"
  },
  {
    "type": "conveyor",
    "x": 430,
    "y": 656,
    "width": 1582,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#153",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "153"
  },
  {
    "type": "conveyor",
    "x": 1883,
    "y": 540,
    "width": 239,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#154",
    "rotation": 270,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "154"
  },
  {
    "type": "conveyor",
    "x": 1895,
    "y": 275,
    "width": 217,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#154",
    "rotation": 270,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "154"
  },
  {
    "type": "conveyor",
    "x": 733,
    "y": 390,
    "width": 1276,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#154",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "154"
  },
  {
    "type": "conveyor",
    "x": 730,
    "y": 418,
    "width": 1276,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#154",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "154"
  },
  {
    "type": "conveyor",
    "x": 718,
    "y": 404,
    "width": 30,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#160",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "160"
  },
  {
    "type": "conveyor",
    "x": 694,
    "y": 818,
    "width": 62,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#161",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "161"
  },
  {
    "type": "conveyor",
    "x": 85,
    "y": 844,
    "width": 638,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#147",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "162"
  },
  {
    "type": "conveyor",
    "x": 783,
    "y": 990,
    "width": 310,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#162",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "162"
  },
  {
    "type": "conveyor",
    "x": 83,
    "y": 872,
    "width": 648,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#149",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "162"
  },
  {
    "type": "conveyor",
    "x": 636,
    "y": 955,
    "width": 181,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#161",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "161"
  },
  {
    "type": "conveyor",
    "x": 85,
    "y": 1038,
    "width": 641,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#149",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "162"
  },
  {
    "type": "conveyor",
    "x": 633,
    "y": 1190,
    "width": 200,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#161",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "161"
  },
  {
    "type": "conveyor",
    "x": 86,
    "y": 1090,
    "width": 654,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#149",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "162"
  },
  {
    "type": "conveyor",
    "x": 756,
    "y": 896,
    "width": 95,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#161",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "161"
  },
  {
    "type": "conveyor",
    "x": 794,
    "y": 934,
    "width": 135,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#161",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "161"
  },
  {
    "type": "conveyor",
    "x": 633,
    "y": 1140,
    "width": 298,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#154",
    "rotation": 270,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "154"
  },
  {
    "type": "conveyor",
    "x": 931,
    "y": 841,
    "width": 1125,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#153",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "153"
  },
  {
    "type": "conveyor",
    "x": 2036,
    "y": 818,
    "width": 54,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#161",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "161"
  },
  {
    "type": "conveyor",
    "x": 1033,
    "y": 940,
    "width": 109,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#161",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "161"
  },
  {
    "type": "conveyor",
    "x": 1083,
    "y": 890,
    "width": 1159,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#149",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "162"
  },
  {
    "type": "conveyor",
    "x": 2083,
    "y": 1040,
    "width": 303,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#161",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "161"
  },
  {
    "type": "conveyor",
    "x": 1983,
    "y": 1190,
    "width": 258,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#162",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "162"
  },
  {
    "type": "conveyor",
    "x": 1933,
    "y": 1240,
    "width": 95,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#161",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "161"
  },
  {
    "type": "ap",
    "x": 1533,
    "y": 390,
    "width": 94,
    "height": 51,
    "origWidth": 120,
    "origHeight": 80,
    "text": "AP26NORMAL",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "ap",
    "deviceId": "AP26"
  },
  {
    "type": "ap",
    "x": 883,
    "y": 390,
    "width": 94,
    "height": 51,
    "origWidth": 120,
    "origHeight": 80,
    "text": "AP28NORMAL",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "ap",
    "deviceId": "AP28"
  },
  {
    "type": "conveyor",
    "x": 796,
    "y": 850,
    "width": 75,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#161",
    "rotation": 180,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "161"
  },
  {
    "type": "conveyor",
    "x": 837,
    "y": 828,
    "width": 61,
    "height": 5,
    "origWidth": 200,
    "origHeight": 12,
    "text": "conveyor#161",
    "rotation": 90,
    "flipH": 1,
    "flipV": 1,
    "process": "",
    "deviceId": "161"
  },
    {
    "type": "ap",
    "x": 1752,
    "y": 1034,
    "width": 94,
    "height": 51,
    "origWidth": 120,
    "origHeight": 80,
    "text": "AP27DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "ap",
    "deviceId": "AP27"
  },
  {
    "type": "ap",
    "x": 1287,
    "y": 1030,
    "width": 94,
    "height": 51,
    "origWidth": 120,
    "origHeight": 80,
    "text": "AP04DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "ap",
    "deviceId": "AP04"
  },

  {
    "type": "ap",
    "x": 373,
    "y": 988,
    "width": 94,
    "height": 51,
    "origWidth": 120,
    "origHeight": 80,
    "text": "AP02DISCONNECTED",
    "rotation": 0,
    "flipH": 1,
    "flipV": 1,
    "process": "ap",
    "deviceId": "AP02"
  }
];

  // 2) Khi trang sẵn sàng thì dựng layout + gọi API lần đầu
  window.addEventListener('load', () => {
    if (__SEED_LAYOUT__ && __SEED_LAYOUT__.length) {
      restoreLayout(__SEED_LAYOUT__);   // nhớ: restoreLayout gọi createElement(..., {snapOnPlace:false})
      refreshData();                    // load dữ liệu lần đầu
    }
  });
</script>
</body>
</html>
