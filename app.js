let isViewTransitioning = false;
let lastUpdateTimestamp = null;
let currentView = 'mold';
let currentPage = 1;
let currentClientFilter = 'all';
const PAGE_SIZE = 35;
let deviceDataMap = new Map();
let chartInstance = null;
let currentModalDeviceId = null;
let currentModalDeviceType = null;
let currentSearchResults = [];
let __actionsFetchVer = 0;
let currentSortState = {
    column: 'datetime',
    direction: 'desc'
};
let viewSwitchTimer = null;
const autoSwitchInterval = 10000;
const viewsToCycle = ['mold', 'tuft', 'blister'];
// --- Tab DOM cache ---
const TabCache = {
  dashboard: { frag: null, ts: 0 },
  mold:      { frag: null, ts: 0 },
  tuft:      { frag: null, ts: 0 },
  blister:   { frag: null, ts: 0 }
};

window.API = window.API || window.API_BASE || 'api.php';

window.API_BASE = window.API;

// === JWT helpers ===
const JWT_STORAGE_KEY = 'ems_token';

function isLikelyJWT(str) {
  if (typeof str !== 'string') return false;
  // Dạng aaaa.bbbb.cccc + chỉ gồm base64url chars
  const parts = str.split('.');
  if (parts.length !== 3) return false;
  if (!/^[A-Za-z0-9\-_]+$/.test(parts[0])) return false;
  if (!/^[A-Za-z0-9\-_]+$/.test(parts[1])) return false;
  if (!/^[A-Za-z0-9\-_]+$/.test(parts[2])) return false;
  // Độ dài tối thiểu để tránh rác
  if (str.length < 40) return false;
  return true;
}

function getToken()    { return localStorage.getItem(JWT_STORAGE_KEY) || ''; }
function setToken(tok) {
  if (isLikelyJWT(tok)) {
    localStorage.setItem(JWT_STORAGE_KEY, tok);
  } else {
    // (tuỳ chọn) ghi log để bắt thủ phạm trả "token" rác
    // console.warn('[auth] Ignored non-JWT token candidate:', tok);
  }
}
function clearToken()  { localStorage.removeItem(JWT_STORAGE_KEY); }




// --- Grab JWT from URL once & clean it ---
(function captureTokenFromURL() {
  try {
    const u = new URL(location.href);
    const tok = u.searchParams.get('token') || (u.hash.startsWith('#token=') ? u.hash.slice(7) : '');
    if (tok) {
      setToken(tok);
      u.searchParams.delete('token');
      if (u.hash.startsWith('#token=')) u.hash = '';
      history.replaceState(null, '', u.pathname + (u.search ? '?' + u.searchParams.toString() : '') + u.hash);
    }
  } catch {}
})();




(function () {
  const ORIG = window.fetch.bind(window);

  function toUrl(input) {
    try { return new URL(typeof input === 'string' ? input : input.url, location.href); }
    catch { return null; }
  }
  function isApi(url) {
    if (!url) return false;
    if (url.origin !== location.origin) return false;           
    return /(^|\/)(api\.php|backend\/api\.php)$/.test(url.pathname);
  }

  window.fetch = async function(input, init = {}) {
    const url = toUrl(input);
    const headers = new Headers(init.headers || {});
    const token = getToken();


    if (token && isApi(url)) {
      headers.set('Authorization', `Bearer ${token}`);

    }

    const res = await ORIG(input, { ...init, headers });

// --- Learn token from responses ---
try {
  if (isApi(url)) {
    // Header-based (ưu tiên) — chỉ nhận nếu giống JWT
    const hAuth = res.headers.get('Authorization')
              || res.headers.get('X-Auth-Token')
              || res.headers.get('X-Token');
    if (hAuth && /^Bearer\s+(.+)/i.test(hAuth)) {
      const cand = hAuth.replace(/^Bearer\s+/i, '').trim();
      if (isLikelyJWT(cand)) setToken(cand);
    }

    // JSON-based — chỉ nhận nếu giống JWT
    const ct = (res.headers.get('content-type') || '').toLowerCase();
    if (ct.includes('application/json')) {
      res.clone().json().then(j => {
        const raw = j?.token || j?.jwt || j?.access_token;
        if (isLikelyJWT(raw)) setToken(raw);
      }).catch(()=>{});
    }
  }
} catch {}




    if (isApi(url) && res.status === 401) {
      // clearToken();


    }
    return res;
  };
})();



// ===== Apply permissions to DOM elements =====
(function(){
  // tiện: promise khi quyền sẵn sàng
  function whenPermissionsReady(){
    if (window.EMSPermission && EMSPermission._list && EMSPermission._list().length) {
      return Promise.resolve();
    }
    return new Promise(res=>{
      document.addEventListener('permissions:ready', ()=>res(), { once:true });
    });
  }

  whenPermissionsReady().then(()=>{
    // 1) áp cho toàn trang lần đầu
    EMSPermission.applyVisibility(document);

    // 2) theo dõi mọi DOM chèn mới và chỉ áp trong vùng có liên quan
    const obs = new MutationObserver(mutations=>{
      for (const m of mutations) {
        m.addedNodes.forEach(node=>{
          if (node.nodeType !== 1) return; // Element
          // nếu chính node có data-perm hoặc có con có data-perm
          if (node.matches?.('[data-perm],[data-perm-any]') ||
              node.querySelector?.('[data-perm],[data-perm-any]')) {
            EMSPermission.applyVisibility(node);
          }
        });
      }
    });

    obs.observe(document.body, { childList:true, subtree:true });
    // có thể lưu lại obs nếu cần disconnect sau này: window.__permObs = obs;

    // 3) cũng nên chạy lại sau khi dịch/ngôn ngữ load xong (nếu UI thay đổi)
    document.addEventListener('translations:ready', ()=>{
      EMSPermission.applyVisibility(document);
    });
  });
})();


// ===== TotalCount cache helpers (LS + RAM) =====
window.__totalCountCache = window.__totalCountCache || new Map();
window.__totalCountUnit  = window.__totalCountUnit  || new Map();
const LS_TC_DATA_KEY = 'tc:data:v1';   // lưu cả count & unit

function tc_safeParse(json, fb) { try { return JSON.parse(json); } catch { return fb; } }

/** Nạp cache từ localStorage vào RAM (cả count và unit) */
function tc_loadFromLS() {
  const raw = localStorage.getItem(LS_TC_DATA_KEY);
  const obj = tc_safeParse(raw, null) || {};
  const byDevice = (obj && typeof obj.byDevice === 'object') ? obj.byDevice : {};
  const byUnit   = (obj && typeof obj.byUnit   === 'object') ? obj.byUnit   : {};

  window.__totalCountCache = new Map(
    Object.entries(byDevice).map(([k, v]) => [String(k), Number(v)])
  );
  window.__totalCountUnit = new Map(
    Object.entries(byUnit).map(([k, v]) => [String(k), String(v)])
  );
  return window.__totalCountCache;
}

/** Lưu RAM cache hiện tại xuống localStorage (cả count và unit) */
function tc_saveToLS(mapCounts = window.__totalCountCache) {
  const byDevice = Object.fromEntries((mapCounts || new Map()).entries());
  const byUnit   = Object.fromEntries((window.__totalCountUnit || new Map()).entries());
  localStorage.setItem(LS_TC_DATA_KEY, JSON.stringify({ byDevice, byUnit }));
}

// ===== DB-driven TTL (6h theo giờ SERVER) =====
const SIX_HOURS_MS   = 6 * 60 * 60 * 1000; // 6 giờ
// const SIX_HOURS_MS   =10000; // 10s (cho test)
const META_TTL_MS    = 60 * 1000;          // cache meta 60s để tránh spam
const LS_TC_META_KEY = 'tc:meta:v1';       // { db_updated_at, server_now, fetched_at }

function tc_saveMetaToLS(meta) {
  try {
    localStorage.setItem(LS_TC_META_KEY, JSON.stringify({
      db_updated_at: meta?.db_updated_at || null,
      server_now:    meta?.server_now    || null,
      fetched_at:    Date.now()
    }));
  } catch {}
}

function tc_loadMetaFromLS() {
  try { return JSON.parse(localStorage.getItem(LS_TC_META_KEY) || ''); }
  catch { return null; }
}

function parseDbTime(s) {
  if (!s) return NaN;
  // giả định server trả UTC "YYYY-MM-DD HH:mm:ss"
  const norm = s.replace(' ', 'T') + 'Z';
  const t = Date.parse(norm);
  return Number.isFinite(t) ? t : NaN;
}

async function tc_getServerMeta() {
  const cached = tc_loadMetaFromLS();
  if (cached && (Date.now() - (cached.fetched_at || 0) < META_TTL_MS)) return cached;

  const API = window.API || 'api.php';
  const r = await fetch(`${API}?action=tc_meta`, { credentials:'include', cache:'no-store' });
  const j = await r.json();
  tc_saveMetaToLS(j);
  return tc_loadMetaFromLS();
}

async function tc_shouldRebuildByDb() {
  try {
    const meta = await tc_getServerMeta();
    const tServerNow = parseDbTime(meta?.server_now);
    const tDbUpdated = parseDbTime(meta?.db_updated_at);
    if (!Number.isFinite(tServerNow) || !Number.isFinite(tDbUpdated)) return true; 
    return (tServerNow - tDbUpdated) >= SIX_HOURS_MS;
  } catch {
    return true; 
  }
}
// Cache để tránh gọi API lặp
window.__totalCountCache = window.__totalCountCache || new Map();
window.__totalCountUnit  = window.__totalCountUnit  || new Map();

async function tc_refreshAllIfExpired() {
  // Seed RAM-cache từ LS khi mới mở trang
  if (window.__totalCountCache.size === 0) tc_loadFromLS();


  const needRebuild = await tc_shouldRebuildByDb();
  if (!needRebuild) return; 

  try {
    const API = window.API_BASE || 'api.php';
    // lấy toàn bộ & recalc
    // const res = await fetch(`${API}?action=get_total_count&recalc=1`,{
    const res = await fetch(`${API}?action=get_total_count`, {
      credentials: 'include',
      cache: 'no-store'
    });
    const j = await res.json();
    const list = Array.isArray(j?.devices) ? j.devices : [];
    // cập nhật RAM-cache
    for (const row of list) {
      const id  = String(row.device_id || '');
      const val = Number(row.total_count || 0);
      if (id) window.__totalCountCache.set(id, val);
      if (row.unit !== undefined) window.__totalCountUnit.set(id, String(row.unit || ''));
    }
    // lưu LS + timestamp
    tc_saveToLS(window.__totalCountCache);


  } catch (e) {
    console.warn('get_total_count (recalc) failed:', e);
  }
}


async function refreshAllTotalCounts() {
  try {
    const API = window.API_BASE || 'api.php';
    const r = await fetch(`${API}?action=get_total_count`, { credentials:'include', cache:'no-store' });
    const j = await r.json();

    // Kỳ vọng j.devices = [{device_id, total_count, unit, ...}]
    if (Array.isArray(j?.devices)) {
      for (const row of j.devices) {
        const id   = String(row.device_id);
        const t    = Number(row.total_count ?? 0);
        const u    = String(row.unit ?? '');
        window.__totalCountCache.set(id, t);
        window.__totalCountUnit.set(id, u);
      }
    }

    // Nếu panel đang mở 1 máy thì cập nhật ngay
    const curId = window.__infoPanelCache?.device_id;
    if (curId) {
      const el = document.getElementById('info-total-count');
      if (el) {
        const t = window.__totalCountCache.get(curId) ?? 0;
        const u = window.__totalCountUnit.get(curId) ?? '';
        el.textContent = formatNumber(t) + (u ? ` ${u}` : '');
      }
    }
  } catch (e) {
    console.warn('refreshAllTotalCounts failed', e);
  }
}

async function loadTotalCount(equipmentId, targetEl) {
  try {
    if (!equipmentId || !targetEl) {
      if (targetEl) targetEl.textContent = '--';
      return;
    }

    // Seed RAM-cache từ localStorage nếu trống
    if (window.__totalCountCache.size === 0) tc_loadFromLS();

    if (window.__totalCountCache.has(equipmentId)) {
      const t = window.__totalCountCache.get(equipmentId);
      const u = window.__totalCountUnit.get(equipmentId) ?? '';
      targetEl.textContent = formatNumber(t) + (u ? ` ${u}` : '');
    } else {
      targetEl.textContent = '--';
    }

    await tc_refreshAllIfExpired();

    if (window.__totalCountCache.has(equipmentId)) {
      const t = window.__totalCountCache.get(equipmentId);
      const u = window.__totalCountUnit.get(equipmentId) ?? '';

      if (!window.__infoPanelCache || window.__infoPanelCache.device_id === equipmentId) {
        targetEl.textContent = formatNumber(t) + (u ? ` ${u}` : '');
      }
      return; 
    }

    // 3) Fallback nhẹ: lấy riêng 1 thiết bị từ số đang lưu DB (không recalc)
    const API = window.API_BASE || 'api.php';
    const url = `${API}?action=get_total_count&device_id=${encodeURIComponent(equipmentId)}`;
    const res = await fetch(url, { credentials: 'include', cache: 'no-store' });
    const data = await res.json(); // kỳ vọng: { device_id, total_count, unit, ... }

    const total = Number(data?.total_count ?? 0);
    const unit  = String(data?.unit ?? '');

    // Cập nhật RAM-cache (và LS cho lần sau)
    window.__totalCountCache.set(equipmentId, total);
    window.__totalCountUnit.set(equipmentId, unit);
    tc_saveToLS(window.__totalCountCache);

    // Cập nhật UI nếu vẫn đang xem đúng thiết bị
    if (!window.__infoPanelCache || window.__infoPanelCache.device_id === equipmentId) {
      targetEl.textContent = formatNumber(total) + (unit ? ` ${unit}` : '');
    }
  } catch (e) {
    console.error('loadTotalCount error:', e);
    targetEl.textContent = '--';
  }
}
async function fillDerivedForPanel(item) {
  const API = window.API || 'api.php';
  try {
    const id = String(item.device_id || '');
    if (!id) return;

    // Đảm bảo total_count đã có trong cache & panel (hàm này sẽ cập nhật cache nếu thiếu)
    const elTotal = document.getElementById('info-total-count');
    await loadTotalCount(id, elTotal);

    const total = Number(window.__totalCountCache.get(id) || 0);
    const type  = String(item.display_type || '');

    if (type === 'mold') {
      const cav = Number(item.cavities || 0);
      const val = total * cav;
      const el  = document.getElementById('info-cavity-count');
      if (el) el.textContent = isFinite(val) ? `${formatNumber(val)} pcs` : '--';
    } else if (type === 'tuft') {
      // YÊU CẦU: API ?view=tuft trả thêm item.hole_per_brush
      const hpb = Number(item.hole_per_brush || item.holes_per_brush || 0);
      const val = total * hpb;
      const el  = document.getElementById('info-total-rpm');
      if (el) el.textContent = isFinite(val) ? `${formatNumber(val)} cycle` : '--';
    } else if (type === 'blister') {
      const r = await fetch(`${API}?action=get_total_count&device_id=${encodeURIComponent(id)}`, {
        credentials: 'include',
        cache: 'no-store'
      });
      const j = await r.json();
      const totalCycle = Number(j?.total_cycle || 0);

      const elCycle = document.getElementById('info-total-cycle');
      if (elCycle) {
        elCycle.textContent = Number.isFinite(totalCycle)
          ? `${formatNumber(totalCycle)} cycle`
          : '--';
      }
    }
  } catch (e) {
    console.warn('fillDerivedForPanel error:', e);
    // set fallback
    const elA = document.getElementById('info-cavity-count'); if (elA) elA.textContent = '--';
    const elB = document.getElementById('info-total-rpm');    if (elB) elB.textContent = '--';
    const elC = document.getElementById('info-total-cycle');  if (elC) elC.textContent = '--';
  }
}



// cất DOM của tab hiện tại vào cache
function detachGridToCache(tab){
  const grid = document.getElementById('dashboard-grid');
  const frag = document.createDocumentFragment();
  while (grid.firstChild) frag.appendChild(grid.firstChild);
  TabCache[tab].frag = frag;
  TabCache[tab].ts = lastUpdateTimestamp || 0;
}

// gắn lại DOM đã cache, trả true nếu có
function attachGridFromCache(tab){
  const grid = document.getElementById('dashboard-grid');
  const frag = TabCache[tab].frag;
  if (!frag) return false;
  grid.appendChild(frag);
  lastUpdateTimestamp = TabCache[tab].ts || null; // để lần fetch sau chỉ lấy delta
  TabCache[tab].frag = null;
  return true;
}

let autoSwitchToggle = null;
// Auto-switch toggle
function startAutoSwitching() {

    if (!autoSwitchToggle || !autoSwitchToggle.checked) return;

    stopAutoSwitching();

    viewSwitchTimer = setInterval(() => {
        const currentIndex = viewsToCycle.indexOf(currentView);
        const nextIndex = (currentIndex + 1) % viewsToCycle.length;
        const nextView = viewsToCycle[nextIndex];
        setView(nextView);
    }, autoSwitchInterval);
}
// Sync CSS animation offset for multiple elements
function syncAnimationOffset(el, cycleMs = 2000) {
  const t = performance.now() % cycleMs;
  el.style.setProperty('--anim-offset', `-${t}ms`);
}


document.querySelectorAll('.info-card.status-breached').forEach(el => {
  syncAnimationOffset(el);
});
// Auto-switch toggle checkbox
function stopAutoSwitching() {
    if (viewSwitchTimer) {
        clearInterval(viewSwitchTimer);
    }
}


/** 10s self-throttled poller (no overlap) **/
let refreshTimer = null;
let refreshAbort = null;
const REFRESH_MS = 10000;

function getVueApp() {
  return document.getElementById('app').__vue__;
}

async function tick(extSignal) {

let hiddenAt = null;
const MAX_HIDDEN_TIME = 60 * 1000; // 1 phút (bạn đổi tuỳ ý)

      // Nếu không quá lâu -> có thể chỉ fetch lại data
      const app = getVueApp();
      if (!app) return;
      try {
        await app.fetchData();
        await app.loadExtraCountersByTab(currentView);
      } catch (e) {
        if (e?.name !== 'AbortError') console.error('tick failed:', e);
      }

// document.addEventListener("visibilitychange", async () => {
//   if (document.hidden) {
//     // Tab bị ẩn -> lưu thời điểm
//     hiddenAt = Date.now();
//   } else {
//     // Tab quay lại -> check thời gian ẩn
//     if (hiddenAt && Date.now() - hiddenAt > MAX_HIDDEN_TIME) {
//       // Nếu ẩn quá lâu -> reload full page
//       location.reload();
//     } else {

//     }
//     hiddenAt = null; // reset
//   }
// });


}


// đặt lên đầu file (gần nơi bạn đang khai báo interval hiện tại)
let POLL = {
  isRunning: false,
  intervalId: null,
  ctrl: null
};

function startPolling() {
  if (POLL.isRunning) return;                         
  POLL.isRunning = true;
  POLL.ctrl = new AbortController();

  const run = async () => {
    try {
      await tick(POLL.ctrl.signal);
    } catch (err) {
      // Bỏ qua AbortError do mình chủ động stop
      if (err?.name !== 'AbortError') {
        if (err?.name !== 'AbortError') {
        console.error('Error fetching data:', err);
}
      }
    }
  };

  run();                                              
  POLL.intervalId = setInterval(run, 30_000);         
}

function stopPolling(reason = 'polling-stopped') {
  if (!POLL.isRunning) return;
  POLL.isRunning = false;

  if (POLL.intervalId) {
    clearInterval(POLL.intervalId);
    POLL.intervalId = null;
  }
  if (POLL.ctrl) {
    try { POLL.ctrl.abort(reason); } catch {}
    POLL.ctrl = null;
  }
}


    const actionsModalClose = document.getElementById('actions-modal-close');
if (actionsModalClose) {
  actionsModalClose.addEventListener('click', () => {
    document.getElementById('actions-modal').classList.add('hidden');
  });
}
const gridElement = document.getElementById('dashboard-grid');

function createInfoCard(item) {
    const itemDiv = document.createElement('div');
    const statusLc = String(item.status || '').toLowerCase();
    itemDiv.className = `info-card status-${statusLc}`;
    itemDiv.dataset.deviceId = item.device_id;

    const flexIndicatorHtml = item.flex == 1 ? '<div class="flex-indicator">F</div>' : '';
    const actionIndicatorHtml = (item.action_count_open > 0)
  ? `<div class="action-indicator ${item.action_urgent_overdue ? 'urgent' : ''}"
       title="${item.action_count_open} active action(s)">A</div>`
  : '';
    const {
        device_id,
        product,
        status,
        live_data,
        lower_limit,
        target_limit,
        upper_limit,
        timestamp,
        breach_type
    } = item;
    let headerBgColor = '';
    if (status === 'DISCONNECTED') headerBgColor = '#999';
    else if (status === 'Normal') headerBgColor = '#2ECC71';
    else if (status === 'Breached') headerBgColor = '#E74C3C';

    let cardBodyHtml = '';

    if (!live_data) {
        cardBodyHtml = `<div class="card-quantity" style="color: #b3b3b3; font-size: 3em;" data-translate="NO DATA" >NO DATA</div>`;
    } else {
        const redStyle = 'color: #E74C3C; font-weight: bold;';

        switch (currentView) {
            case 'mold': {
              const { cycle_time, efficiency, cavities: actualCavitiesValue } = live_data;
              let cycleStyle = '', efficiencyStyle = '', actualCavityStyle = '', moldCavityStyle = '', llStyle = '', ulStyle = '';

              const cycleTimeFloat = parseFloat(cycle_time);
              const lowerLimitFloat = parseFloat(lower_limit);
              const upperLimitFloat = parseFloat(upper_limit);
              if (!isNaN(cycleTimeFloat) && !isNaN(lowerLimitFloat) && cycleTimeFloat < lowerLimitFloat) {
                  cycleStyle = redStyle; llStyle = redStyle;
              }
              if (!isNaN(cycleTimeFloat) && !isNaN(upperLimitFloat) && cycleTimeFloat > upperLimitFloat) {
                  cycleStyle = redStyle; ulStyle = redStyle;
              }

              const efficiencyFloat = parseFloat(efficiency);
              const effLowerLimitFloat = parseFloat(item.efficiency_lower_limit);
              // (đoạn này đã comment check efficiency)
              if (!isNaN(efficiencyFloat) && !isNaN(effLowerLimitFloat) && efficiencyFloat < effLowerLimitFloat) {
                efficiencyStyle = 'color: #E74C3C; font-weight: bold;';
              }
              const actualCavitiesInt = parseInt(actualCavitiesValue, 10);
              const moldCavitiesInt = parseInt(item.cavities, 10);
              if (!isNaN(actualCavitiesInt) && !isNaN(moldCavitiesInt) && actualCavitiesInt < moldCavitiesInt) {
                  actualCavityStyle = redStyle; moldCavityStyle = redStyle;
              }
                // --- QUYẾT ĐỊNH MÀU HEADER CHO MOLD ---
              const cycleOut =
                (!isNaN(cycleTimeFloat) && !isNaN(lowerLimitFloat) && cycleTimeFloat < lowerLimitFloat) ||
                (!isNaN(cycleTimeFloat) && !isNaN(upperLimitFloat) && cycleTimeFloat > upperLimitFloat);

              const cavityMismatch =
                (!isNaN(actualCavitiesInt) && !isNaN(moldCavitiesInt) && actualCavitiesInt < moldCavitiesInt);

              const isBreach = cycleOut || cavityMismatch;

              if (status === 'DISCONNECTED')      headerBgColor = '#999';
              else if (isBreach)                  headerBgColor = '#E74C3C';
              else                                headerBgColor = '#2ECC71';

              if (status !== 'DISCONNECTED' && isBreach) {
                itemDiv.classList.add('status-breached');
              } else {
                itemDiv.classList.remove('status-breached');
              }
                    cardBodyHtml = `
                        <div class="flex flex-col flex-grow">
                            <div class="flex w-full" style="flex-grow: 1.5; border-bottom: 1px solid #eeeeee;">
                                <div class="w-1/2 flex flex-col items-center justify-center">
                                    <span class="font-bold text-lg" style="${cycleStyle}">${cycle_time ?? 'N/A'}s</span>
                                    <span class="font-size:0.2em text-gray-500 " data-translate="Current Cycle">Current Cycle</span>
                                </div>
                                <div class="w-1/2 flex flex-col items-center justify-center border-l border-gray-200">
                                    <span class="font-bold text-lg" style="${efficiencyStyle}">${efficiency ?? 'N/A'}%</span>
                                    <span class="font-size:0.2em text-gray-500 " data-translate="Efficiency">Efficiency</span>
                                </div>
                            </div>
                            <div class="card-specs bg-white">
                                <div class="card-spec-col"><span class="value" style="${ulStyle}">${upper_limit ?? 'N/A'}</span><br><span class="label" >UL</span></div>
                                <div class="card-spec-col"><span class="value">${target_limit ?? 'N/A'}</span><br><span class="label" data-translate="Target">Target</span></div>
                                <div class="card-spec-col"><span class="value" style="${llStyle}">${lower_limit ?? 'N/A'}</span><br><span class="label">LL</span></div>
                            </div>
                            <div class="card-details">
                                <div class="card-spec-col"><span class="value">${item.process ?? 'N/A'}</span><br><span class="label" data-translate="Process">Process</span></div>
                                <div class="card-spec-col"><span class="value" style="${actualCavityStyle}">${actualCavitiesValue ?? 'N/A'}</span><br><span class="label" data-translate="Actual Cavity">Actual Cavity</span></div>
                                <div class="card-spec-col"><span class="value" style="${moldCavityStyle}">${item.cavities ?? 'N/A'}</span><br><span class="label" data-translate="Mold Cavity">Mold Cavity</span></div>
                            </div>
                        </div>`;
                    break;
                }
            case 'tuft': {
            const { output, rpm } = live_data;
            let outputStyle = '', llStyle = '', ulStyle = '';
            let isBreach = false;
            let effStyle = '';
            const effLower = parseFloat(item.efficiency_lower_limit);
            const effVal   = parseFloat(live_data.efficiency);
            if (!isNaN(effVal) && !isNaN(effLower) && effVal < effLower) {
              effStyle = 'color: #E74C3C; font-weight: bold;';
            }

            const outputFloat = parseFloat(output);
            const lower = parseFloat(lower_limit);
            const upper = parseFloat(upper_limit);

            if (!isNaN(outputFloat) && !isNaN(lower) && outputFloat < lower) {
                outputStyle = redStyle; llStyle = redStyle; isBreach = true;
            }
            if (!isNaN(outputFloat) && !isNaN(upper) && outputFloat > upper) {
                outputStyle = redStyle; ulStyle = redStyle; isBreach = true;
            }

            
            if (status === 'DISCONNECTED')      headerBgColor = '#999';
            else if (isBreach)                  headerBgColor = '#E74C3C';
            else                                headerBgColor = '#2ECC71';
            if (isBreach) itemDiv.classList.add('status-breached');
             if (status !== 'DISCONNECTED' && isBreach) {
            itemDiv.classList.add('status-breached');
            } else {
            itemDiv.classList.remove('status-breached'); 
            }
                    cardBodyHtml = `
                        <div class="flex flex-col flex-grow">
                            <div class="flex w-full" style="flex-grow: 1.5; border-bottom: 1px solid #eeeeee;">
                                <div class="w-1/2 flex flex-col items-center justify-center">
                                    <span class="font-bold text-lg" style="${outputStyle}">${output ?? 'N/A'}pcs</span>
                                    <span class="font-size:0.2em text-gray-500 " data-translate="Current Cycle">Current Cycle</span>
                                </div>
                                <div class="w-1/2 flex flex-col items-center justify-center border-l border-gray-200">
                                    <span class="font-bold text-lg" style="${effStyle}">${live_data.efficiency ?? 'N/A'}%</span>
                                    <span class="font-size:0.2em text-gray-500 "  data-translate="Efficiency"> Efficiency</span>
                                </div>
                            </div>
                            <div class="card-specs bg-white">
                                 <div class="card-spec-col"><span class="value" style="${ulStyle}">${upper_limit ?? 'N/A'}</span><br><span class="label">UL</span></div>
                                <div class="card-spec-col"><span class="value">${target_limit ?? 'N/A'}</span><br><span class="label" data-translate="Target">Target</span></div>
                                 <div class="card-spec-col"><span class="value" style="${llStyle}">${lower_limit ?? 'N/A'}</span><br><span class="label">LL</span></div>
                            </div>
                            <div class="card-details">
                                <div class="card-spec-col"><span class="value">${item.process ?? 'N/A'}</span><br><span class="label" data-translate="Process">Process</span></div>
                                <div class="card-spec-col"><span class="value">${rpm ?? 'N/A'}</span><br><span class="label" data-translate="rpm">RPM</span></div>
                            </div>
                        </div>`;
                    break;
                }

            case 'blister':
                {
                    const {
                        cyclecount,
                        BrushesperCycle,
                        output: blisterOutput
                    } = live_data;
                    let cycleCountStyle = '',
                        llStyle = '',
                        ulStyle = '';
                    let effStyle = '';
                      const effLower = parseFloat(item.efficiency_lower_limit);
                      const effVal   = parseFloat(live_data.efficiency);
                      if (!isNaN(effVal) && !isNaN(effLower) && effVal < effLower) {
                        effStyle = 'color: #E74C3C; font-weight: bold;';
                      }

                    const cycleCountFloat = parseFloat(cyclecount);
                    const lowerLimitFloat = parseFloat(lower_limit);
                    const upperLimitFloat = parseFloat(upper_limit);

                    if (!isNaN(cycleCountFloat) && !isNaN(lowerLimitFloat) && cycleCountFloat < lowerLimitFloat) {
                        cycleCountStyle = redStyle;
                        llStyle = redStyle;
                    }
                    if (!isNaN(cycleCountFloat) && !isNaN(upperLimitFloat) && cycleCountFloat > upperLimitFloat) {
                        cycleCountStyle = redStyle;
                        ulStyle = redStyle;
                    }

                    cardBodyHtml = `
                        <div class="flex flex-col flex-grow">
                            <div class="flex w-full" style="flex-grow: 1.5; border-bottom: 1px solid #eeeeee;">
                                <div class="w-1/2 flex flex-col items-center justify-center">
                                    <span class="font-bold text-lg" style="${cycleCountStyle}">${cyclecount ?? 'N/A'}cycles</span>
                                    <span class="font-size:0.2em text-gray-500 " data-translate="Current Cycle">Current Cycle</span>
                                </div>
                                <div class="w-1/2 flex flex-col items-center justify-center border-l border-gray-200">
                                    <span class="font-bold text-lg" style="${effStyle}">${live_data.efficiency ?? 'N/A'}%</span>
                                    <span class="font-size:0.2em text-gray-500 " data-translate="Efficiency"> Efficiency</span>
                                </div>
                            </div>
                            <div class="card-specs bg-white">
                                <div class="card-spec-col"><span class="value" style="${ulStyle}">${upper_limit ?? 'N/A'}</span><br><span class="label">UL</span></div>
                                <div class="card-spec-col"><span class="value">${target_limit ?? 'N/A'}</span><br><span class="label" data-translate="Target">Target</span></div>
                                 <div class="card-spec-col"><span class="value" style="${llStyle}">${lower_limit ?? 'N/A'}</span><br><span class="label">LL</span></div>
                            </div>
                            <div class="card-details">
                                <div class="card-spec-col"><span class="value">${BrushesperCycle ?? 'N/A'}</span><br><span class="label" data-translate="brushes_per_cycle">Brushes/cycle</span></div>
                                <div class="card-spec-col"><span class="value">${blisterOutput ?? 'N/A'}</span><br><span class="label" data-translate="pcs_per_minute">Pcs/minute</span></div>
                            </div>
                        </div>`;
                    break;
                }
        }
    }

    itemDiv.innerHTML = `
            ${flexIndicatorHtml} 
            ${actionIndicatorHtml}
            <div class="card-header" style="background-color: ${headerBgColor};">
                <div class="vt-code">${device_id}</div><div class="product-name">${product}</div>
            </div>
            ${cardBodyHtml}
            <div class="card-footer">${formatDbDate(timestamp)}</div>`;
    return itemDiv;
}

function renderGridSkeleton(count = 12){
  const grid = document.getElementById('dashboard-grid');
  if (!grid) return;
  grid.innerHTML = '';
  const tpl = document.getElementById('tpl-skel-card');
  const frag = document.createDocumentFragment();
  for (let i=0;i<count;i++){
    frag.appendChild(tpl.content.firstElementChild.cloneNode(true));
  }
  grid.appendChild(frag);
}

function showTableSkeleton(){
  const tbody = document.getElementById('search-tbody');
  const thead = document.getElementById('search-table-header');
  if (!tbody) return;
  // giữ header, chỉ thay body bằng skeleton
  const holder = document.getElementById('tpl-skel-table');
  tbody.innerHTML = '';
  // cho skeleton chiếm full hàng:
  const tr = document.createElement('tr');
  const td = document.createElement('td');
  td.colSpan = (thead?.cells.length || 4);
  td.appendChild(holder.content.cloneNode(true));
  tr.appendChild(td);
  tbody.appendChild(tr);
}
function hideTableSkeleton(){ /* no-op: body sẽ được populate thật */ }


function populateInfoPanel(item) {
    const redColor = '#E74C3C';
    const defaultColor = '';
    const live_data = item.live_data;
    const displayType = item.display_type || '';

    const moldInfoFields = document.querySelectorAll('#chart-info-panel [data-info-field="mold"]');
    const tuftInfoFields = document.querySelectorAll('#chart-info-panel [data-info-field="tuft"]');
    tuftInfoFields.forEach(field => {
      field.style.display = (displayType === 'tuft') ? '' : 'none';
    });

    const blisterInfoFields = document.querySelectorAll('#chart-info-panel [data-info-field="blister"]');
    blisterInfoFields.forEach(field => {
      field.style.display = (displayType === 'blister') ? '' : 'none';
    });
    moldInfoFields.forEach(field => {
        field.style.display = (displayType === 'mold') ? '' : 'none';
    });

    const elements = {
        idLabel: document.getElementById('info-device-id-label'),
        deviceId: document.getElementById('info-device-id'),
        process: document.getElementById('info-process'),
        moldCavities: document.getElementById('info-mold-cavities'),
        actualCavities: document.getElementById('info-actual-cavities'),
        capacity: document.getElementById('info-capacity'),
        efficiency: document.getElementById('info-efficiency'),
        effLl: document.getElementById('info-eff-ll'),
        currentCycle: document.getElementById('info-current-cycle'),
        tg: document.getElementById('info-tg'),
        ul: document.getElementById('info-ul'),
        ll: document.getElementById('info-ll'),
        lostPcs: document.getElementById('info-lost-pcs'),
        lostTime: document.getElementById('info-lost-time'),

        chartModalTitle: document.getElementById('chart-modal-title'),
        totalCount: document.getElementById('info-total-count')
    };

    for (const key in elements) {
        if (elements[key] && elements[key].style) {
            elements[key].style.color = defaultColor;
        }
    }

    if (displayType === 'mold') elements.idLabel.textContent = 'Mold ID:';
    else if (displayType === 'tuft') elements.idLabel.textContent = 'Machine ID:';
    else if (displayType === 'blister') elements.idLabel.textContent = 'Machine ID:';
    else elements.idLabel.textContent = 'ID:';

    elements.deviceId.textContent = item.device_id ?? 'N/A';
    elements.process.textContent = item.process ?? 'N/A';
    elements.moldCavities.textContent = formatNumber(item.cavities);
    elements.actualCavities.textContent = formatNumber(live_data?.cavities);
    elements.capacity.textContent = formatNumber(item.capacity) + ' pcs/h';
    elements.efficiency.textContent = formatNumber(live_data?.efficiency) + '%';
    elements.effLl.textContent = formatNumber(item.efficiency_lower_limit) + '%';
    switch (displayType) {
        case 'tuft':
            elements.currentCycle.textContent = formatNumber(live_data?.output) + ' pcs';
            break;
        case 'blister':
            elements.currentCycle.textContent = formatNumber(live_data?.cyclecount) + ' cycles';
            break;
        case 'mold':
        default:
            elements.currentCycle.textContent = formatNumber(live_data?.cycle_time) + 's';
            break;
    }
    ['mold', 'tuft', 'blister'].forEach(t => {
  document.querySelectorAll(`#chart-info-panel [data-info-field="${t}"]`)
    .forEach(el => el.style.display = (displayType === t) ? '' : 'none');
});

// Đổi nhãn cho dòng tổng: Mold = Shot Count, còn lại = Total Count
const lblTotal = document.getElementById('label-total-count');
if (lblTotal) {
  lblTotal.textContent = (displayType === 'mold') ? 'Shot Count' : 'Total Count';
}
    elements.tg.textContent = formatNumber(item.target_limit);
    elements.ul.textContent = formatNumber(item.upper_limit);
    elements.ll.textContent = formatNumber(item.lower_limit);
    elements.lostPcs.textContent = formatNumber(live_data?.loss_pcs);
    elements.lostTime.textContent = formatNumber(live_data?.idle_breakdown) + ' min';
    elements.chartModalTitle.textContent = `${item.device_id} ${item.product}`;

    if (live_data) {
        const cycleTimeFloat = parseFloat(live_data.cycle_time);
        const lowerLimitFloat = parseFloat(item.lower_limit);
        const upperLimitFloat = parseFloat(item.upper_limit);
        if (!isNaN(cycleTimeFloat)) {
            if (!isNaN(lowerLimitFloat) && cycleTimeFloat < lowerLimitFloat) {
                elements.currentCycle.style.color = redColor;
                elements.ll.style.color = redColor;
            }
            if (!isNaN(upperLimitFloat) && cycleTimeFloat > upperLimitFloat) {
                elements.currentCycle.style.color = redColor;
                elements.ul.style.color = redColor;
            }
        }

        const efficiencyFloat = parseFloat(live_data.efficiency);
        const effLowerLimitFloat = parseFloat(item.efficiency_lower_limit);
        // if (!isNaN(efficiencyFloat) && !isNaN(effLowerLimitFloat) && efficiencyFloat < effLowerLimitFloat) {
        //     elements.efficiency.style.color = redColor;
        //     elements.effLl.style.color = redColor;
        // }

        if (displayType === 'mold') {
            const actualCavitiesInt = parseInt(live_data.cavities, 10);
            const moldCavitiesInt = parseInt(item.cavities, 10);
            if (!isNaN(actualCavitiesInt) && !isNaN(moldCavitiesInt) && actualCavitiesInt < moldCavitiesInt) {
                elements.actualCavities.style.color = redColor;
                elements.moldCavities.style.color = redColor;
            }
        }
    }
    // Lưu raw dữ liệu của panel để chỗ khác tái dùng (không phải đọc ngược từ DOM đã format)
    window.__infoPanelCache = {
      device_id: item.device_id,
      target_limit: item.target_limit,
      upper_limit: item.upper_limit,
      lower_limit: item.lower_limit,
      efficiency_lower_limit: item.efficiency_lower_limit,
      cavities: item.cavities,                    // Mold Cavity
      actual_efficiency: item.live_data?.efficiency ?? null,
      actual_cycle: item.live_data?.cycle_time ?? null,
      actual_cavities: item.live_data?.cavities ?? null
    };
    // --- TOTAL COUNT ---
if (elements.totalCount) {
  elements.totalCount.textContent = '--'; // reset hiển thị
  loadTotalCount(item.device_id, elements.totalCount);
}
 if (elements.totalCount) {
   // Ưu tiên lấy từ cache đã seed khi vào web
   const tCache = window.__totalCountCache.get(item.device_id);
   const uCache = window.__totalCountUnit.get(item.device_id);
   // fallback: nếu backend đã trả kèm trong payload devices (nếu có)
   const total = (tCache !== undefined) ? tCache : Number(item.total_count ?? 0);
   const unit  = (uCache !== undefined) ? uCache : String(item.unit ?? '');
   elements.totalCount.textContent = formatNumber(total) + (unit ? ` ${unit}` : '');
 }
 fillDerivedForPanel(item);
const url = `./api.php?action=get_total_count&device_id=${item.device_id}`;
}

function populateLossReport(lostPcsData, idleData) {
    const reportTableBody = document.querySelector('#loss-report-table tbody');
    if (!reportTableBody) return;

    if (reportTableBody.rows.length < 2) {
        reportTableBody.innerHTML = `
                <tr><td class="p-1 border border-gray-300 font-medium text-gray-600" data-translate="lost_pcs">Lost pcs(pcs)</td>${Array(25).fill('<td class="p-1 border border-gray-300 text-center">-</td>').join('')}</tr>
                <tr><td class="p-1 border border-gray-300 font-medium text-gray-600" data-translate="idle_breakdown">Idle/Breakdown(s)</td>${Array(25).fill('<td class="p-1 border border-gray-300 text-center">-</td>').join('')}</tr>
            `;
    }
    const lostPcsRow = reportTableBody.rows[0];
    const idleRow = reportTableBody.rows[1];
    let totalLost = 0;
    let totalIdle = 0;

    lostPcsData.forEach((value, index) => {
        lostPcsRow.cells[index + 1].textContent = formatNumber(value);
        totalLost += (value || 0);
    });
    lostPcsRow.cells[lostPcsRow.cells.length - 1].textContent = formatNumber(totalLost);

    idleData.forEach((value, index) => {
        idleRow.cells[index + 1].textContent = formatNumber(value);
        totalIdle += (value || 0);
    });
    idleRow.cells[idleRow.cells.length - 1].textContent = formatNumber(totalIdle);

    document.getElementById('info-lost-pcs').textContent = formatNumber(totalLost);
    document.getElementById('info-lost-time').textContent = formatNumber(totalIdle) + ' min';
}
    async function refreshLossReport(deviceId, reportDate) {
      const url = `./api.php?action=get_hourly_report`
                + `&device_id=${encodeURIComponent(deviceId)}`
                + `&report_date=${encodeURIComponent(reportDate)}`;

      const tbody = document.querySelector('#loss-report-table tbody');
      if (tbody) {
        tbody.innerHTML = `
          <tr><td class="p-1 border border-gray-300 font-medium text-gray-600" data-translate="lost_pcs">Lost pcs (pcs)</td>${Array(25).fill('<td class="p-1 border border-gray-300 text-center">…</td>').join('')}</tr>
          <tr><td class="p-1 border border-gray-300 font-medium text-gray-600" data-translate="idle_breakdown">Idle/Breakdown (min)</td>${Array(25).fill('<td class="p-1 border border-gray-300 text-center">…</td>').join('')}</tr>
        `;
      }

      try {
        const resp = await fetch(url, { cache: 'no-store' });
        if (!resp.ok) throw new Error(`HTTP ${resp.status}`);
        const j = await resp.json();
        if (j.status && j.status !== 'success') throw new Error(j.message || 'API error');

        const lost = Array.isArray(j.lost_pcs_24) ? j.lost_pcs_24.slice(0,24) : [];
        const idle = Array.isArray(j.idle_24_min) ? j.idle_24_min.slice(0,24) : [];
        while (lost.length < 24) lost.push(0);
        while (idle.length < 24) idle.push(0);

        // Đồng bộ label phút
        const idleHeader = document.querySelector('[data-translate="idle_breakdown"]');
        if (idleHeader) idleHeader.textContent = 'Idle/Breakdown (min)';

        populateLossReport(lost, idle);
      } catch (err) {
        console.error('[LossReport]', err);
        if (tbody) tbody.innerHTML = `<tr><td colspan="26" class="p-2 text-red-600 border">Lỗi tải Loss Report: ${err.message}</td></tr>`;
        const elLost = document.getElementById('info-lost-pcs');
        const elIdle = document.getElementById('info-lost-time');
        if (elLost) elLost.textContent = '0';
        if (elIdle) elIdle.textContent = '0 min';
      }
    }

    // Gọi thử:
    // refreshLossReport('AC151006', '2025-10-16');


function fetchAndRenderHourlyReport(deviceId, reportDate) {
    const ctx = document.getElementById('history-chart').getContext('2d');
    if (chartInstance) {
        chartInstance.destroy();
    }

    ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
    ctx.font = "16px Roboto";
    ctx.fillStyle = "#666";
    ctx.textAlign = "center";
    ctx.fillText("Loading data...", ctx.canvas.width / 2, ctx.canvas.height / 2);

    const url = `./api.php?action=get_hourly_report&device_id=${deviceId}&report_date=${reportDate}`;

    fetch(url)
        .then(response => response.json())
        .then(data => {
            if (data.error) throw new Error(data.error);

            const labels = [];
            for (let i = 7; i < 24; i++) {
                labels.push(i.toString().padStart(2, '0') + ':00');
            }
            for (let i = 0; i < 7; i++) {
                labels.push(i.toString().padStart(2, '0') + ':00');
            }
            const dataMap = new Map(data.hourly_data.map(h => [h.hour, h]));
            const hoursSequence = [...Array(24).keys()].map(h => (h + 7) % 24);

            const outputData = [],
                efficiencyData = [],
                lostPcsData = [],
                idleData = [];
            for (const hour of hoursSequence) {
                const hourData = dataMap.get(hour);
                if (hourData) {
                    outputData.push(hourData.total_output);
                    efficiencyData.push(hourData.avg_efficiency);
                    lostPcsData.push(hourData.total_loss_pcs);
                    idleData.push(hourData.total_idle_breakdown);
                } else {
                    outputData.push(0);
                    efficiencyData.push(0);
                    lostPcsData.push(0);
                    idleData.push(0);
                }
            }
            const effLowerLimitData = Array(24).fill(data.eff_lower_limit);

            renderChart(labels, outputData, efficiencyData, effLowerLimitData);
            populateLossReport(lostPcsData, idleData);
        })
        .catch(error => {
            console.error('Failed to fetch hourly report:', error);
            ctx.clearRect(0, 0, ctx.canvas.width, ctx.canvas.height);
            ctx.fillStyle = "#E74C3C";
            ctx.fillText(`Error: ${error.message}`, ctx.canvas.width / 2, ctx.canvas.height / 2);
        });
}

function renderChart(labels, outputData, efficiencyData, effLowerLimitData) {
    if (chartInstance) {
        chartInstance.destroy();
    }
    const ctx = document.getElementById('history-chart').getContext('2d');

    chartInstance = new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                type: 'line',
                label: window.translationManager.translate("Efficiency"),
                data: efficiencyData,
                borderColor: 'rgb(255, 152, 152)',
                backgroundColor: 'rgba(255, 136, 0, 0.1)',
                yAxisID: 'y1',
                tension: 0.4,
                fill: false,
                datalabels: {
                    align: 'start',
                    anchor: 'start',
                    borderRadius: 4,
                    padding: 4,
                    color: '#9e0202ff',
                    font: {
                        weight: 'bold',
                        size: 10
                    },
                    formatter: (value) => value > 0 ? value.toFixed(0) + '%' : null
                }
            }, {
                type: 'bar',
                label: window.translationManager.translate("Output"),
                data: outputData,
                backgroundColor: 'rgba(54, 162, 235, 0.7)',
                borderColor: 'rgb(15, 76, 117)',
                yAxisID: 'y',
                datalabels: {
                    anchor: 'center',
                    align: 'center',
                    offset: -5,
                    color: '#202428ff',
                    font: {
                        weight: 'bold',
                        size: 10
                    },
                    formatter: (value) => value > 0 ? Math.round(value) : null
                }
            }, {
                type: 'line',
                label: window.translationManager.translate("Eff.requirement"),
                data: effLowerLimitData,
                borderColor: 'rgb(225, 68, 52)',
                borderDash: [5, 5],
                yAxisID: 'y1',
                pointRadius: 0,
                fill: false,
                datalabels: {
                    display: false
                }
            }, ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            interaction: {
                mode: 'index',
                intersect: false
            },
            scales: {
                y: {
                    type: 'linear',
                    position: 'left',
                    ticks: {
                        callback: value => value.toLocaleString()
                    }
                },
                y1: {
                    type: 'linear',
                    position: 'right',
                    min: 0,
                    max: 110,
                    grid: {
                        drawOnChartArea: false
                    },
                    ticks: {
                        callback: value => value + '%'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            },
            plugins: {
                legend: {
                    position: 'bottom'
                }
            }
        }
    });
}
function openChartModal(deviceId) {
    const item = deviceDataMap.get(deviceId);
    if (!item) return;

    currentModalDeviceId = deviceId;
    currentModalDeviceType = item.display_type;

    const chartModal = document.getElementById('chart-modal');
    chartModal.classList.remove('hidden');

    const toDateInstance = document.querySelector("#to-date")._flatpickr;
    if (toDateInstance) {
        toDateInstance.setDate(new Date());
    }

    populateInfoPanel(item);
    updateSearchTableHeader(currentModalDeviceType);
    updateSearchStats([]);

    document.querySelectorAll('.date-shift-btn').forEach(btn => btn.classList.remove('active'));
    const todayButton = document.querySelector('.date-shift-btn[data-shift="0"]');
    if (todayButton) todayButton.classList.add('active');

    const today = new Date().toISOString().slice(0, 10);

    fetchAndRenderHourlyReport(deviceId, today);
}
function openActionsModal(deviceId) {
  window.currentModalDeviceId = deviceId;

  const modal = document.getElementById('actions-modal');
  const title = document.getElementById('actions-modal-title');
  const body  = document.getElementById('actions-list');

  title.textContent = window.translationManager.translate("Issue") + ` – ${deviceId}`;
  body.innerHTML = `
    <div class="flex items-center justify-center p-8">
      <div class="flex items-center space-x-3">
        <svg class="animate-spin h-6 w-6 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
        <span class="text-gray-600 font-medium">Loading...</span>
      </div>
    </div>`;
  modal.classList.remove('hidden');

   fetch(`api.php?action=list_device_actions_v2&device_id=${encodeURIComponent(deviceId)}`, {
     credentials: 'include',
     cache: 'no-store',
     headers: { 'Accept': 'application/json' }
   })
  .then(r => { if (!r.ok) throw new Error(`HTTP ${r.status}`); return r.json(); })
   .then(j => {
   if (j && j.status && j.status !== 'success') throw new Error(j.message || 'API error');
   const list = Array.isArray(j)               ? j
              : Array.isArray(j.items)         ? j.items
              : Array.isArray(j.data?.items)   ? j.data.items
              : Array.isArray(j.data)          ? j.data
              : Array.isArray(j.rows)          ? j.rows
              : Array.isArray(j.list)          ? j.list
              : [];
    if (!Array.isArray(list) || list.length === 0) {
      body.innerHTML = `
        <div class="flex flex-col items-center justify-center p-12">
          <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mb-4">
            <i class="fas fa-inbox text-3xl text-gray-400"></i>
          </div>
          <p class="text-gray-500 font-medium">No actions found</p>
          <p class="text-gray-400 text-sm mt-1">There are no issues for this device</p>
        </div>`;
      return;
    }

    // Sort: incomplete first, nearest deadline first
    list.sort((a,b)=>{
      const w = (a.status_auto==='done') - (b.status_auto==='done'); if (w) return w;
      const da = a.due_date ? Date.parse(a.due_date) : Infinity;
      const db = b.due_date ? Date.parse(b.due_date) : Infinity;
      if (da!==db) return da-db;
      return (b.id||0)-(a.id||0);
    });

    body.innerHTML = `
      <div class="overflow-auto max-h-[75vh]">
        <table class="min-w-full text-sm bg-white">
          <thead class="sticky top-0 z-20 bg-gradient-to-r from-gray-50 to-gray-100 border-b-2 border-gray-200">
            <tr class="text-xs uppercase tracking-wider text-gray-700 font-semibold">
              <th class="px-4 py-4 text-left whitespace-nowrap" data-translate="Issue ID">Issue ID</th>
              <th class="px-4 py-4 text-left min-w-[200px]" data-translate="Issue Description">Issue Description</th>
              <th class="px-4 py-4 text-center whitespace-nowrap" data-translate="Issue Type">Type</th>
              <th class="px-4 py-4 text-center whitespace-nowrap" data-translate="Planned completion date">Planned Date</th>
              <th class="px-4 py-4 text-center whitespace-nowrap" data-translate="Created Date">Created</th>
              <th class="px-4 py-4 text-center whitespace-nowrap" data-translate="Creator">Creator</th>
              <th class="px-4 py-4 text-center whitespace-nowrap" data-translate="Status">Status</th>
              <th class="px-4 py-4 text-center whitespace-nowrap" data-translate="Action Plans">Plans</th>
              <th class="px-4 py-4 text-center whitespace-nowrap" data-translate="Approval">Approval</th>
            </tr>
          </thead>
          <tbody id="issues-tbody"></tbody>
        </table>
      </div>`;

    const tbody = document.getElementById('issues-tbody');

    list.forEach((row, idx) => {
      const ISS        = 'ISS' + String(row.id).padStart(5,'0');
      const created    = row.created_at ? formatDbDate(row.created_at) : '-';
      const issueType  = window.translationManager.translate(row.issue_type || '-');
      const plannedEnd = row.planned_completion_date ? formatDbDateOnly(row.planned_completion_date) : '-';
      const creator    = row.created_by_name || '-';
      const desc       = row.desc || row.title || '-';

      const done  = Number(row.plans_done||0);
      const total = Number(row.plans_total||0);
      const inProgress = total>0 && done>0 && done<total;
      const complete   = total>0 && done===total;

      const pill = complete
        ? `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
             <i class="fas fa-check-circle mr-1.5"></i>Complete
           </span>`
        : inProgress
        ? `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-amber-50 text-amber-700 ring-1 ring-amber-200">
             <i class="fas fa-clock mr-1.5"></i>In Progress
           </span>`
        : `<span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold bg-blue-50 text-blue-700 ring-1 ring-blue-200">
             <i class="fas fa-circle-notch mr-1.5"></i>Open
           </span>`;

      const appr = window.translationManager.translate(
        complete && row.approval_status!=='approved'
          ? 'awaiting approval'
          : (row.approval_status || '-')
      );

      if (idx > 0) {
        tbody.insertAdjacentHTML('beforeend', `<tr><td colspan="9" class="h-3"></td></tr>`);
      }

      tbody.insertAdjacentHTML('beforeend', `
        <tr id="issue-${row.id}"
            class="group hover:bg-blue-50/30 transition-all duration-200
                   border-y border-gray-200 bg-white">
          <td class="px-4 py-4">
            <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-blue-100 text-blue-800 font-bold text-xs tracking-wide">
              ${ISS}
            </span>
          </td>
          <td class="px-4 py-4">
            <div class="text-gray-900 font-medium leading-snug">${escapeHtml(desc)}</div>
          </td>
          <td class="px-4 py-4 text-center" data-issue-id="${row.id}">
            <span class="inline-flex items-center px-2.5 py-1 rounded-md bg-gray-100 text-gray-700 text-xs font-medium">
              ${escapeHtml(issueType)}
            </span>
          </td>
          <td class="px-4 py-4 text-center issue-type-label" data-col="planned-end">
            <span class="text-gray-700 font-medium">${plannedEnd}</span>
          </td>
          <td class="px-4 py-4 text-center text-gray-600 text-xs">${created}</td>
          <td class="px-4 py-4 text-center">
            <span class="text-gray-700 font-medium">${escapeHtml(creator)}</span>
          </td>
          <td class="px-4 py-4 text-center">${pill}</td>
          <td class="px-4 py-4 text-center">
            <div class="flex items-center justify-center space-x-2">
              <button class="icon-btn eye-btn inline-flex items-center justify-center w-9 h-9 rounded-lg 
                             bg-gray-100 hover:bg-blue-600 hover:text-white text-gray-700 
                             transition-all duration-200 shadow-sm hover:shadow-md group/btn"
                      title="Show all action plans" data-issue-id="${row.id}">
                <i class="fas fa-eye text-sm"></i>
              </button>
              <div class="inline-flex items-center px-3 py-1.5 rounded-lg bg-gradient-to-r from-gray-50 to-gray-100 border border-gray-200">
                <span class="text-xs font-bold text-gray-900">${done}</span>
                <span class="mx-1 text-xs text-gray-400">/</span>
                <span class="text-xs font-medium text-gray-600">${total}</span>
              </div>
            </div>
          </td>
          <td class="px-4 py-4 text-center">
            <span class="text-gray-700 font-medium text-xs">${appr}</span>
          </td>
        </tr>
      `);
    });
function ensureReasonOverlay() {
  let overlay = document.getElementById('reason-modal');
  if (!overlay) {
    // Nếu bạn đã render sẵn reason-modal trong HTML, đoạn này không chạy.
    overlay = document.createElement('div');
    overlay.id = 'reason-modal';
    overlay.className = 'rm-overlay';
    overlay.hidden = true;
    overlay.innerHTML = `
      <div class="rm">
        <div class="rm-header">Provide a reason</div>
        <form id="reason-form">
          <label class="rm-label">Reason <span id="rm-count">0/300</span></label>
          <textarea id="rm-input" maxlength="300" required
            placeholder="Ví dụ: nhập sai ngày / không còn áp dụng..."></textarea>
          <div class="rm-actions">
            <button type="button" id="rm-cancel">Cancel</button>
            <button type="submit" id="rm-ok">Confirm</button>
          </div>
        </form>
      </div>`;
  }
  // 🔴 Quan trọng: portal ra body để thoát stacking context của popup cha
  if (overlay.parentElement !== document.body) {
    document.body.appendChild(overlay);
  }
  // Đảm bảo nằm trên cùng
  overlay.style.zIndex = '10000';
  return overlay;
}

function lockBodyScroll(lock) {
  document.documentElement.style.overflow = lock ? 'hidden' : '';
  document.body.style.overflow = lock ? 'hidden' : '';
}

async function askReason({ title='Provide a reason', placeholder='Describe reason…',
                           required=true, min=0, max=300 } = {}) {
  return new Promise(resolve => {
    const overlay = ensureReasonOverlay();          // ⟵ portal here
    const header  = overlay.querySelector('.rm-header');
    const form    = overlay.querySelector('#reason-form');
    const input   = overlay.querySelector('#rm-input');
    const cancel  = overlay.querySelector('#rm-cancel');
    const okBtn   = overlay.querySelector('#rm-ok');
    const counter = overlay.querySelector('#rm-count');

    header.textContent = title;
    input.placeholder  = placeholder;
    input.value = '';
    input.setAttribute('maxlength', max);
    overlay.hidden = false;
    lockBodyScroll(true);                           // chặn scroll nền
    input.focus();

    const update = () => {
      counter.textContent = `${input.value.length}/${max}`;
      okBtn.disabled = !!(required && input.value.trim().length < Math.max(1, min));
    };
    update();

    const close = (val) => {
      overlay.hidden = true;
      lockBodyScroll(false);
      form.removeEventListener('submit', onSubmit);
      cancel.removeEventListener('click', onCancel);
      document.removeEventListener('keydown', onEsc);
      input.removeEventListener('input', update);
      resolve(val);
    };
    const onSubmit = (e) => {
      e.preventDefault();
      if (required && input.value.trim().length < Math.max(1, min)) return;
      close(input.value.trim());
    };
    const onCancel = () => close(null);
    const onEsc = (e) => { if (e.key === 'Escape') close(null); };

    form.addEventListener('submit', onSubmit);
    cancel.addEventListener('click', onCancel);
    document.addEventListener('keydown', onEsc);
    input.addEventListener('input', update);
  });
}


    // Event delegation
    tbody.addEventListener('click', async (ev) => {
      // 1) Bulk Complete ALL plans
      const bulkBtn = ev.target.closest('[data-complete-all]');
      if (bulkBtn) {
        const issueId = bulkBtn.dataset.issueId;
        if (!issueId) return;
        if (!confirm('Mark ALL action plans as Complete?')) return;

        try {
          const form = new URLSearchParams();
          form.set('action', 'bulk_update_action_plan_status');
          form.set('action_id', issueId);
          form.set('status', 'done');

          const r = await fetch('./api.php', {
            method: 'POST',
            body: form,
            credentials: 'include',
            cache: 'no-store'
          });
          const j = await r.json();
          if (!r.ok || j.error) throw new Error(j.error || ('HTTP ' + r.status));

          openActionsModal(deviceId);
          vm?.loadExtraCountersByTab?.(currentView);
          enrichDevicesWithActionsAndRender?.();
        } catch (e) {
          alert(e.message || 'Bulk update failed');
        }
        return;
      }

      // 2) Individual plan tools
      const toolBtn = ev.target.closest('.act-plan-complete, .act-plan-reopen, .act-plan-delete');
      if (toolBtn) {
        const planId  = toolBtn.dataset.planId;
        const issueId = toolBtn.dataset.issueId;
try {
  let reason = null;

  if (toolBtn.classList.contains('act-plan-delete')) {
    reason = await askReason({
      title: 'Remove this action plan',
      required: true, min: 3, max: 300
    });
    if (reason === null) return; // người dùng bấm Cancel/Esc
    await postForm('api.php', { action: 'delete_action_plan', plan_id: planId, reason });

  } else if (toolBtn.classList.contains('act-plan-complete')) {
    reason = await askReason({
      title: 'Mark plan as done',
      
      required: false, max: 300
    });
    await postForm('api.php', {
      action: 'update_action_plan_status', plan_id: planId, status: 'done', reason: reason || ''
    });

  } else if (toolBtn.classList.contains('act-plan-reopen')) {
    reason = await askReason({
      title: 'Reopen plan',
      
      required: true, min: 3, max: 300
    });
    if (reason === null) return;
    await postForm('api.php', {
      action: 'update_action_plan_status', plan_id: planId, status: 'open', reason
    });
  }

  openActionsModal(deviceId);
  vm?.loadExtraCountersByTab?.(currentView);
  enrichDevicesWithActionsAndRender?.();

} catch (e) {
  alert(e.message || 'Update failed');


        }
        return;
      }

      // 3) Eye button - toggle action plans
      const eye = ev.target.closest('.eye-btn');
      if (!eye) return;

      const issueId = eye.dataset.issueId;
      const parent  = document.getElementById(`issue-${issueId}`);
      if (!parent) return;

      // If already open, close it
      const next = parent.nextElementSibling;
      if (next && next.classList.contains('extra-row-mini')) {
        next.remove();
        return;
      }

      // Fetch action plans
      let plans = [];
      let rows  = '';
      try {
        plans = await fetchActionPlans(issueId);
        rows = (plans || []).map((p) => {
          const AP    = p.plan_code || ('AP' + String(p.id).padStart(5,'0'));
          const est   = p.est_date ? formatDbDateOnly(p.est_date) : '-';
          const owner = p.owner_name || p.owner_user_id || '-';
          const done  = String(p.status||'').toLowerCase()==='done';
          
          const pill  = done
            ? `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 ring-1 ring-emerald-200">
                 <i class="fas fa-check-circle mr-1"></i>Complete
               </span>`
            : `<span class="inline-flex items-center px-2.5 py-1 rounded-full text-xs font-bold bg-gray-100 text-gray-700 ring-1 ring-gray-200">
                 <i class="fas fa-circle mr-1"></i>Open
               </span>`;
          
          const tools = !done
            ? `<button class="icon-btn act-plan-complete inline-flex items-center justify-center w-8 h-8 rounded-lg 
                       bg-emerald-50 hover:bg-emerald-600 text-emerald-600 hover:text-white 
                       transition-all duration-200 shadow-sm hover:shadow"
                       data-perm= "update.status.action.plan" 
                       title="Complete" data-plan-id="${p.id}" data-issue-id="${issueId}">
                 <i class="fas fa-check text-sm"></i>
               </button>`
            : `<button class="icon-btn act-plan-reopen inline-flex items-center justify-center w-8 h-8 rounded-lg 
                       bg-gray-50 hover:bg-gray-600 text-gray-600 hover:text-white 
                       transition-all duration-200 shadow-sm hover:shadow"
                       
                       title="Mark Incomplete" data-plan-id="${p.id}" data-issue-id="${issueId}">
                 <i class="fas fa-undo text-sm"></i>
               </button>`;
          
          return `
            <tr class="border-t border-gray-100 hover:bg-gray-50 transition-colors duration-150">
              <td class="px-4 py-3">
                <span class="inline-flex items-center px-2 py-1 rounded-md bg-indigo-100 text-indigo-800 font-bold text-xs">
                  ${AP}
                </span>
              </td>
              <td class="px-4 py-3 text-left">
                <span class="text-gray-900 font-medium">${escapeHtml(p.plan_text||'-')}</span>
              </td>
              <td class="px-4 py-3 text-center text-gray-700 font-medium">${est}</td>
              <td class="px-4 py-3 text-center text-gray-700">${escapeHtml(owner)}</td>
              <td class="px-4 py-3 text-center">${pill}</td>
              <td class="px-4 py-3 text-center">
                <div class="flex items-center justify-center space-x-2">
                  ${tools}
                  <button class="icon-btn act-plan-delete inline-flex items-center justify-center w-8 h-8 rounded-lg 
                                 bg-red-50 hover:bg-red-600 text-red-600 hover:text-white 
                                 transition-all duration-200 shadow-sm hover:shadow" 
                                 data-perm= "delete.action.plan" 
                          title="Remove" data-plan-id="${p.id}" data-issue-id="${issueId}">
                    <i class="fas fa-trash text-sm"></i>
                  </button>
                </div>
              </td>
            </tr>`;
        }).join('');
      } catch (e) {
        rows = `<tr><td colspan="6" class="px-4 py-4 text-center text-red-600 font-medium">${e.message || 'Load plans failed'}</td></tr>`;
      }

      parent.insertAdjacentHTML('afterend', `
        <tr class="extra-row-mini bg-gradient-to-b from-gray-50/50 to-transparent">
          <td colspan="9" class="py-3 px-4">
            <div class="flex">
              <!-- Vertical indicator line -->
              <div class="w-8 flex items-start pt-2">
                <div class="ml-4 w-0.5 h-full bg-gradient-to-b from-blue-400 to-transparent rounded-full"></div>
              </div>

              <!-- Content card -->
              <div class="flex-1 pl-2">
                <div class="rounded-xl border border-gray-200 bg-white shadow-lg overflow-hidden">
                  <!-- Header -->
                  <div class="px-5 py-4 bg-gradient-to-r from-blue-50 via-indigo-50 to-blue-50 border-b border-gray-200">
                    <div class="flex items-center justify-between">
                      <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-lg bg-blue-600 flex items-center justify-center shadow-md">
                          <i class="fas fa-list-check text-white"></i>
                        </div>
                        <div>
                          <h3 class="text-sm font-bold text-gray-900">Action Plans</h3>
                          <p class="text-xs text-gray-600 mt-0.5">${deviceId} – ISS${String(issueId).padStart(5,'0')}</p>
                        </div>
                      </div>
                      <button class="inline-flex items-center justify-center h-10 px-5 rounded-lg 
                                     bg-gradient-to-r from-emerald-600 to-emerald-500 text-white 
                                     hover:from-emerald-700 hover:to-emerald-600 
                                     font-semibold text-sm shadow-md hover:shadow-lg 
                                     transition-all duration-200 transform hover:scale-105"
                              data-perm = "update.status.action.plan"
                              data-complete-all data-issue-id="${issueId}">
                        <i class="fas fa-check-double mr-2"></i>
                        Complete All
                      </button>
                    </div>
                  </div>

                  <!-- Table -->
                  <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                      <thead class="bg-gray-50 border-b border-gray-200">
                        <tr class="text-xs uppercase tracking-wider text-gray-700 font-semibold">
                          <th class="px-4 py-3 text-left whitespace-nowrap" data-translate="Action ID">Action ID</th>
                          <th class="px-4 py-3 text-left min-w-[200px]" data-translate="Action Plans">Action Plan</th>
                          <th class="px-4 py-3 text-center whitespace-nowrap" data-translate="Planned completion date">Planned Date</th>
                          <th class="px-4 py-3 text-center whitespace-nowrap" data-translate="Owner">Owner</th>
                          <th class="px-4 py-3 text-center whitespace-nowrap" data-translate="Status">Status</th>
                          <th class="px-4 py-3 text-center whitespace-nowrap" data-translate="Tools">Tools</th>
                        </tr>
                      </thead>
                      <tbody class="divide-y divide-gray-100">
                        ${rows || `
                          <tr>
                            <td colspan="6" class="px-4 py-8 text-center">
                              <div class="flex flex-col items-center">
                                <div class="w-12 h-12 bg-gray-100 rounded-full flex items-center justify-center mb-3">
                                  <i class="fas fa-inbox text-2xl text-gray-400"></i>
                                </div>
                                <p class="text-gray-500 font-medium">No action plans</p>
                              </div>
                            </td>
                          </tr>
                        `}
                      </tbody>
                    </table>
                  </div>
                </div>
              </div>
            </div>
          </td>
        </tr>
      `);
    });
  })
  .catch(err => {
    body.innerHTML = `
      <div class="flex flex-col items-center justify-center p-12">
        <div class="w-16 h-16 bg-red-100 rounded-full flex items-center justify-center mb-4">
          <i class="fas fa-exclamation-triangle text-3xl text-red-600"></i>
        </div>
        <p class="text-red-600 font-bold text-lg">Error Loading Data</p>
        <p class="text-gray-600 text-sm mt-2">${err.message}</p>
      </div>`;
    console.error(err);
  });
}


// Helpers
function escapeHtml(s){return String(s??'').replace(/[&<>"']/g,m=>({ '&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;' }[m]))}
async function fetchActionPlans(issueId){
  const r = await fetch(`api.php?action=list_action_plans&action_id=${issueId}`,{credentials:'include',cache:'no-store'});
  if(!r.ok) throw new Error('Load plans failed');
  return r.json();
}
async function postForm(url, obj){
  const fd = new URLSearchParams(); Object.entries(obj).forEach(([k,v])=>fd.set(k,v));
  const r = await fetch(url,{method:'POST',body:fd,credentials:'include',cache:'no-store'});
  const j = await r.json(); if(!(j && (j.success || j.status==='success'))) throw new Error(j?.message||j?.error||'Request failed');
  return j;
}





function updateSearchTableHeader(deviceType) {
    const thead = document.getElementById('search-table-header');
    let headerHtml = '';

    switch (deviceType) {
        case 'mold':
            headerHtml = `
                    <th class="p-1 text-left cursor-pointer" data-sort="datetime"><span class="flex items-center" >window.translationManager.translate('DateTime')<i class="fas fa-sort ml-2"></i></span></th>
                    <th class="p-1 text-left cursor-pointer" data-sort="cavities"><span class="flex items-center" >window.translationManager.translate('Cavities')<i class="fas fa-sort ml-2"></i></span></th>
                    <th class="p-1 text-left cursor-pointer" data-sort="cycle_time"><span class="flex items-center" >window.translationManager.translate('Cycle')<i class="fas fa-sort ml-2"></i></span></th>
                    <th class="p-1 text-left cursor-pointer" data-sort="output"><span class="flex items-center" >window.translationManager.translate('Output')<i class="fas fa-sort ml-2"></i></span></th>
                `;
            break;
        case 'tuft':
            headerHtml = `
                    <th class="p-1 text-left cursor-pointer" data-sort="datetime"><span class="flex items-center" >window.translationManager.translate('DateTime')<i class="fas fa-sort ml-2"></i></span></th>
                    <th class="p-1 text-left cursor-pointer" data-sort="output"><span class="flex items-center" >window.translationManager.translate('Output')<i class="fas fa-sort ml-2"></i></span></th>
                `;
            break;
        case 'blister':
            headerHtml = `
                    <th class="p-1 text-left cursor-pointer" data-sort="datetime"><span class="flex items-center" >window.translationManager.translate('DateTime')<i class="fas fa-sort ml-2"></i></span></th>
                    <th class="p-1 text-left cursor-pointer" data-sort="BrushesperCycle"><span class="flex items-center" >window.translationManager.translate('Pcs')<i class="fas fa-sort ml-2"></i></span></th>
                    <th class="p-1 text-left cursor-pointer" data-sort="cyclecount"><span class="flex items-center" >window.translationManager.translate('Cycle')<i class="fas fa-sort ml-2"></i></span></th>
                    <th class="p-1 text-left cursor-pointer" data-sort="output"><span class="flex items-center" >window.translationManager.translate('Output')<i class="fas fa-sort ml-2"></i></span></th>
                `;
            break;
        default:
            headerHtml = '<th>No data structure available</th>';
    }
    thead.innerHTML = headerHtml;
}

function createMoreCard(count) {
    const divElement = document.createElement('div');
    divElement.className = 'info-card';
    divElement.title = `View ${count} more devices`;
    divElement.dataset.action = 'show-more';
    divElement.style.cursor = 'pointer';
    divElement.innerHTML = `<div class="card-header" style="background-color: #3498db;"><div class="vt-code" style="font-size: 2.0em;" data-translate="VIEW MORE">VIEW MORE</div></div><div class="card-quantity" style="color: #3498db;font-size: 6em; text">+${count}</div>`;
    return divElement;
}

function createBackCard() {
    const divElement = document.createElement('div');
    divElement.className = 'info-card';
    divElement.title = 'Back to main page';
    divElement.dataset.action = 'show-main';
    divElement.style.cursor = 'pointer';
    divElement.innerHTML = `<div class="card-header" style="background-color: #3498db;"><div class="vt-code" style="font-size: 2.0em;" data-translate="BACK">BACK</div></div><div class="card-quantity"style="color: #3498db;font-size: 6em;"><i class="fas fa-arrow-left"></i></div>`;
    return divElement;
}

function formatNumber(value) {
    if (value === null || value === undefined || isNaN(value)) {
        return 'N/A';
    }
    const roundedValue = Math.round(parseFloat(value));
    return roundedValue.toLocaleString('en-US');
}

function formatDbDate(dateString) {
    if (!dateString) return 'N/A';
    return new Date(dateString).toLocaleString('en-CA', {
        hour12: false
    }).replace(/,/g, '');
}
function formatDbDateOnly(dateString) {
  if (!dateString) return '-';
  // ưu tiên cắt phần YYYY-MM-DD nếu có sẵn
  const m = String(dateString).match(/^(\d{4}-\d{2}-\d{2})/);
  if (m) return m[1];
  // fallback: vẫn cố parse nhưng chỉ lấy YYYY-MM-DD
  const d = new Date(dateString);
  return isNaN(d) ? '-' : d.toISOString().slice(0,10);
}


function updateTime() {
    const now = new Date();
    const options = {
        weekday: 'short',
        year: 'numeric',
        month: '2-digit',
        day: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
        second: '2-digit',
        hour12: false
    };
    document.getElementById('datetime-display').textContent = new Intl.DateTimeFormat('en-CA', options).format(now).replace(/,/g, '');
}

function updateFontSize() {
    if (window.innerWidth >= 1280) {
        if (gridElement.children.length === 0) return;
        const baseFontSize = gridElement.clientWidth / 8 / 20;
        gridElement.style.fontSize = `${baseFontSize}px`;
    } else {
        gridElement.style.fontSize = '';
    }
}
// const sel = document.getElementById('idx_assignee');
function renderDashboard() {
  gridElement.innerHTML = '';
  const fragment = document.createDocumentFragment();

  // ⬇️ THAY THẾ statusWeight bằng phiên bản này (ngay trong renderDashboard)
const statusWeight = (d) => {
  const s = String(d.status || '').toUpperCase();
  // Disconnected luôn xếp cuối cùng
  if (s === 'DISCONNECTED') return 2;

  const ld = d.live_data || {};
  const ll = parseFloat(d.lower_limit);
  const ul = parseFloat(d.upper_limit);

  let isBreach = false;

  if (d.display_type === 'mold') {
    const cyc = parseFloat(ld.cycle_time);
    const actual = parseInt(ld.cavities, 10);
    const mold   = parseInt(d.cavities, 10);
    const cycleOut = (!isNaN(cyc) && !isNaN(ll) && cyc < ll) ||
                     (!isNaN(cyc) && !isNaN(ul) && cyc > ul);
    const cavityMismatch = (!isNaN(actual) && !isNaN(mold) && actual < mold);
    isBreach = cycleOut || cavityMismatch;
  } else if (d.display_type === 'tuft') {
    const out = parseFloat(ld.output);
    isBreach = (!isNaN(out) && !isNaN(ll) && out < ll) ||
               (!isNaN(out) && !isNaN(ul) && out > ul);
  } else if (d.display_type === 'blister') {
    const cc = parseFloat(ld.cyclecount);
    isBreach = (!isNaN(cc) && !isNaN(ll) && cc < ll) ||
               (!isNaN(cc) && !isNaN(ul) && cc > ul);
  }

  // Ưu tiên: breach (0) → normal (1) → disconnected (2)
  return isBreach ? 0 : 1;
};


  const allDevices = Array.from(deviceDataMap.values())
    .filter(d => d.display_type === currentView)
    .filter(d => {
        if (currentClientFilter === 'all') return true;
        // So sánh không phân biệt hoa thường và trim khoảng trắng
        const dClient = String(d.client || '').trim().toLowerCase();
        const filter  = String(currentClientFilter).trim().toLowerCase();
        return dClient === filter;
    })
    .sort((a, b) => {
      const dw = statusWeight(a) - statusWeight(b);
      if (dw) return dw; // ưu tiên theo trạng thái
      // phụ: giữ sort alphabet bên trong từng nhóm cho dễ tìm
      return String(a.device_id || '').localeCompare(String(b.device_id || ''));
    });

  if (currentPage === 1) {
    const devicesToShow = allDevices.slice(0, 35);
    devicesToShow.forEach(item => fragment.appendChild(createInfoCard(item)));
    if (allDevices.length > 35) {
      fragment.appendChild(createMoreCard(allDevices.length - 35));
    }
  } else {
    fragment.appendChild(createBackCard());
    const remainingDevices = allDevices.slice(35);
    remainingDevices.forEach(item => fragment.appendChild(createInfoCard(item)));
  }
  gridElement.appendChild(fragment);
  updateFontSize();

  gridElement.querySelectorAll('.info-card.status-breached').forEach(syncAnimationOffset);
}


const vm = new Vue({
    el: '#app',
    data: {
        loading: true,
        connectedDevices: 0,
        breachedDevices: 0,
        disconnectedDevices: 0,
        totalDevices: 0,
        flexibleDevices: 0,
        actionOpen: 0
    },
    
    methods: {
        async loadExtraCounters() {
        try {
            const [flexRes, actRes] = await Promise.all([
            fetch('./api.php?action=count_flexible'),
            fetch('./api.php?action=count_actions')
            ]);
            const flex = await flexRes.json();
            const act  = await actRes.json();
            this.flexibleDevices = Number(flex?.count || 0);
            this.actionOpen      = Number(act?.count || 0);
        } catch (e) {
            console.error('loadExtraCounters failed:', e);
            // fallback giữ nguyên để UI không vỡ
        }
        },
async loadExtraCountersByTab(proc, family = '') {
    const qs = new URLSearchParams({ process: proc });
    if (family) qs.set('family', family);

    try {
        // Gọi 3 API cùng lúc: Flexible, Action, và Status (MỚI)
        const [flexRes, actRes, statusRes] = await Promise.all([
            fetch(`./api.php?action=count_flexible&${qs.toString()}`, { cache: 'no-store' }),
            fetch(`./api.php?action=count_actions&${qs.toString()}`, { cache: 'no-store' }),
            fetch(`./api.php?action=count_device_status&${qs.toString()}`, { cache: 'no-store' }) // <--- Gọi API mới
        ]);

        const flex = await flexRes.json();
        const act  = await actRes.json();
        const stats = await statusRes.json();

        // Cập nhật Flexible & Action
        this.flexibleDevices = Number(flex?.count || 0);
        this.actionOpen      = Number(act?.count || 0);

        // Cập nhật 4 chỉ số trạng thái (Online/Warning/Offline/Total)
        if (stats && typeof stats.total !== 'undefined') {
            this.totalDevices        = Number(stats.total || 0);
            this.connectedDevices    = Number(stats.connected || 0); // Online
            this.breachedDevices     = Number(stats.breached || 0);  // Warning
            this.disconnectedDevices = Number(stats.disconnected || 0); // Offline
        }

    } catch (e) {
        console.error('loadExtraCountersByTab failed:', e);
    }
},

        
        fetchData(abortSignal) {
            this.loading = true;
            let apiUrl = `./api.php?view=${currentView}`;
            if (lastUpdateTimestamp) {
                apiUrl += `&since=${encodeURIComponent(lastUpdateTimestamp)}`;
            }

            const fetchOpts = { cache: 'no-store' };
            if (abortSignal) fetchOpts.signal = abortSignal;
            return fetch(apiUrl, fetchOpts)
                .then(response => {
                    if (!response.ok) throw new Error(`Could not load data. Server error.`);
                    return response.json();
                })
                .then(response => {
                  const data = response.devices;
                  if (data.error) throw new Error(data.error);
                  data.forEach(item => {
                    item.status = String(item.status || '');
                    deviceDataMap.set(String(item.device_id), item);

                    // đảm bảo có default để createInfoCard không bị undefined
                    if (item.action_count_open == null)     item.action_count_open = 0;
                    if (item.action_urgent_overdue == null) item.action_urgent_overdue = 0;
                  });

                  lastUpdateTimestamp = response.newTimestamp;
                  if (typeof window.updateClientFilterOptions === 'function') {
                      window.updateClientFilterOptions();
                  }

                  if (data.length > 0 || !lastUpdateTimestamp) {
                      const devicesForCurrentView = Array.from(deviceDataMap.values())
                        .filter(d => d.display_type === currentView);


                      // this.connectedDevices    = devicesForCurrentView.filter(i => i.status === 'Normal').length;  
                      // this.connectedDevices = devicesForCurrentView.filter(i => i.status === 'Normal' || i.status === 'Breached').length;
                      // this.breachedDevices     = devicesForCurrentView.filter(i => i.status === 'Breached').length;
                      // this.disconnectedDevices = devicesForCurrentView.filter(i => i.status === 'DISCONNECTED').length;
                      // this.totalDevices        = devicesForCurrentView.length;
                  }

                  renderDashboard();
                })

                .catch(error => {
                    if (error.name === 'AbortError') {
                      // request bị cancel (tab ẩn hoặc AbortController) -> bỏ qua
                      return;
                    }
                    console.error('Error fetching data:', error);
                    lastUpdateTimestamp = null;
                    gridElement.innerHTML = `<p style="color: red; grid-column: 1 / -1; text-align: center; padding-top: 20px;">${error.message}</p>`;
                })
                .finally(() => {});
        }

        
    }
});
async function fetchActionsSummary(displayType) {
  const url = `./api.php?action=devices_action_table&display_type=${encodeURIComponent(displayType)}`;
  const r = await fetch(url, { cache: 'no-store' });
  if (!r.ok) throw new Error(`actions HTTP ${r.status}`);
  const rows = await r.json();
  const map = new Map();
  (rows || []).forEach(row => {
    const id = String(row.device_id);
    map.set(id, {
      action_count_open: Number(row.open_count || 0),
      action_urgent_overdue: Number(row.urgent_overdue || 0),
    });
  });
  return map;
}
// THAY thế nguyên hàm hiện tại bằng phiên bản này
async function enrichDevicesWithActionsAndRender(displayType = currentView) {
  const myVer = ++__actionsFetchVer; // token cho lần gọi này

  try {
    const actionsMap = await fetchActionsSummary(displayType);

    // Nếu trong lúc chờ, tab đã đổi -> bỏ kết quả cũ
    if (myVer !== __actionsFetchVer || displayType !== currentView) return;

    // Chỉ merge cho đúng tab (displayType) đang mở
    deviceDataMap.forEach((item, key) => {
      if (String(item.display_type) !== String(displayType)) return;
      const a = actionsMap.get(String(key));
      item.action_count_open     = a?.action_count_open ?? 0;
      item.action_urgent_overdue = a?.action_urgent_overdue ?? 0;
    });

    renderDashboard();
  } catch (err) {
    // Nếu request bị hủy do đổi tab, im lặng thoát
    if (myVer !== __actionsFetchVer || displayType !== currentView) return;
    console.error('[actions] load failed:', err);
    renderDashboard(); // vẫn render phần device nếu action lỗi
  }
}

function setView(viewName, isInitialLoad = false) {
  const vueApp = document.getElementById('app').__vue__;
  if (!vueApp) { console.warn('Vue app chưa sẵn sàng'); return; }
  if (isViewTransitioning && !isInitialLoad) return;

  isViewTransitioning = true;
  document.querySelectorAll('.page-nav-link').forEach(l => l.classList.add('disabled'));

  const prevView = currentView;
  currentView = viewName;
  currentPage = 1;

  document.querySelectorAll('.page-nav-link').forEach(link => {
    link.classList.toggle('active', link.dataset.view === viewName);
  });

  if (!isInitialLoad) detachGridToCache(prevView);

  // ✨ Nếu đã có DOM cache: vẫn phải refresh badge Actions
  if (attachGridFromCache(viewName)) {
    vueApp.loadExtraCountersByTab(viewName);
    enrichDevicesWithActionsAndRender(viewName); // ✅ THÊM DÒNG NÀY
    isViewTransitioning = false;
    document.querySelectorAll('.page-nav-link').forEach(l => l.classList.remove('disabled'));
    return;
  }

  lastUpdateTimestamp = null;
  renderGridSkeleton(36);

  vueApp.loadExtraCountersByTab(viewName);

  // ✨ Tải thiết bị xong thì enrich action & render
  vueApp.fetchData()
    .then(() => enrichDevicesWithActionsAndRender(viewName)) // ✅ THÊM DÒNG NÀY
    .finally(() => {
      isViewTransitioning = false;
      document.querySelectorAll('.page-nav-link').forEach(l => l.classList.remove('disabled'));
    });

  startPolling();
}



function updateSearchStats(data) {
    if (!data) return;

    let totalOutput = 0;
    let totalMetric = 0;
    let avgMetric = 0;
    let avgCycleLabel = window.translationManager.translate("AverageCycle");

    switch (currentModalDeviceType) {
        case 'tuft':
            totalOutput = data.reduce((sum, row) => sum + parseInt(row.output || 0), 0);
            totalMetric = totalOutput;
            avgCycleLabel = window.translationManager.translate("AverageOutput");
            break;
        case 'blister':
            totalOutput = data.reduce((sum, row) => sum + parseInt(row.output || 0), 0);
            totalMetric = data.reduce((sum, row) => sum + parseFloat(row.cyclecount || 0), 0);
            avgCycleLabel = window.translationManager.translate("AverageCycle");
            break;
        case 'mold':
        default:
            totalOutput = data.reduce((sum, row) => sum + parseInt(row.output || 0), 0);
            totalMetric = data.reduce((sum, row) => sum + parseFloat(row.cycle_time || 0), 0);
            avgCycleLabel = window.translationManager.translate("AverageCycle");
            break;
    }

    if (data.length > 0) {
        avgMetric = totalMetric / data.length;
    }

    document.getElementById('search-stat-total-output').textContent = formatNumber(totalOutput);
    document.getElementById('avg-cycle-label').textContent = avgCycleLabel;
    document.getElementById('search-stat-avg-cycle').textContent = avgMetric.toFixed(2);
}

function populateSearchTable(data) {
    const tbody = document.getElementById('search-tbody');
    if (!data || data.length === 0) {
        const colCount = document.getElementById('search-table-header').cells.length || 4;
        tbody.innerHTML = `<tr><td colspan="${colCount}" class="text-center p-4 text-gray-500 font-semibold text-lg">NO DATA</td></tr>`;
        return;
    }

    let rowsHtml = '';
    switch (currentModalDeviceType) {
        case 'mold':
            rowsHtml = data.map((row) => `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-1">${formatDbDate(row.datetime)}</td>
                        <td class="p-1 text-center">${row.cavities}</td>
                        <td class="p-1 text-center">${!isNaN(parseFloat(row.cycle_time)) ? parseFloat(row.cycle_time).toFixed(2) : 'N/A'}</td>
                        <td class="p-1 text-center">${formatNumber(row.output)}</td>
                    </tr>`).join('');
            break;
        case 'tuft':
            rowsHtml = data.map((row) => `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-1">${formatDbDate(row.datetime)}</td>
                        <td class="p-1 text-center">${row.output}</td>
                    </tr>`).join('');
            break;
        case 'blister':
            rowsHtml = data.map((row) => `
                    <tr class="border-b hover:bg-gray-50">
                        <td class="p-1">${formatDbDate(row.datetime)}</td>
                        <td class="p-1 text-center">${row.BrushesperCycle}</td>
                        <td class="p-1 text-center">${!isNaN(parseFloat(row.cyclecount)) ? parseFloat(row.cyclecount).toFixed(2) : 'N/A'}</td>
                        <td class="p-1 text-center">${row.output}</td>
                    </tr>`).join('');
            break;
    }
    tbody.innerHTML = rowsHtml;
}

/**
 * Exports the current search results to a CSV file.
 */
function exportToExcel() {
    if (!currentSearchResults || currentSearchResults.length === 0) {
        // alert("No data available to export. Please perform a search first.");
        alert(window.translationManager.translate("NoDataToExport"));
        return;
    }

    const deviceId = currentModalDeviceId || 'data';
    let fromDateStr = "nodate";
    let toDateStr = "nodate";

    const fromDateInstance = document.querySelector("#from-date")._flatpickr;
    if (fromDateInstance && fromDateInstance.selectedDates.length > 0) {
        fromDateStr = formatISODate(fromDateInstance.selectedDates[0]).replace(/[-: ]/g, '_');
    }

    const toDateInstance = document.querySelector("#to-date")._flatpickr;
    if (toDateInstance && toDateInstance.selectedDates.length > 0) {
        toDateStr = formatISODate(toDateInstance.selectedDates[0]).replace(/[-: ]/g, '_');
    }

    const fileName = `export_${deviceId}_from_${fromDateStr}_to_${toDateStr}.csv`;

    let headers = [];
    let csvRows = [];

    const escapeCsvField = (field) => {
        const strField = String(field ?? '');
        if (strField.includes(',')) {
            return `"${strField.replace(/"/g, '""')}"`;
        }
        return strField;
    };

    try {
        switch (currentModalDeviceType) {
            case 'mold':
                headers = [
                  window.translationManager.translate("DateTime"),
                  window.translationManager.translate("Cavities"),
                  window.translationManager.translate("CycleTime"),
                  window.translationManager.translate("Output"),
                ];
                csvRows = currentSearchResults.map(row => [
                    formatDbDate(row.datetime),
                    row.cavities, !isNaN(parseFloat(row.cycle_time)) ? parseFloat(row.cycle_time).toFixed(2) : 'N/A',
                    formatNumber(row.output)
                ].map(escapeCsvField));
                break;
            case 'tuft':
                headers = [
                  window.translationManager.translate("DateTime"),
                  window.translationManager.translate("Output"),
                ];
                csvRows = currentSearchResults.map(row => [
                    formatDbDate(row.datetime),
                    row.output
                ].map(escapeCsvField));
                break;
            case 'blister':
                headers = [
                  window.translationManager.translate("DateTime"),
                  window.translationManager.translate("BrushesPerCycle"),
                  window.translationManager.translate("CycleCount"),
                  window.translationManager.translate("OutputPcsMin"),
                ];
                csvRows = currentSearchResults.map(row => [
                    formatDbDate(row.datetime),
                    row.BrushesperCycle, !isNaN(parseFloat(row.cyclecount)) ? parseFloat(row.cyclecount).toFixed(2) : 'N/A',
                    row.output
                ].map(escapeCsvField));
                break;
            default:
                throw new Error("Unknown device type for export.");
        }

        const csvHeader = headers.join(',');
        const csvBody = csvRows.map(row => row.join(',')).join('\n');
        const csvContent = `${csvHeader}\n${csvBody}`;

        const blob = new Blob(['\uFEFF' + csvContent], {
            type: 'text/csv;charset=utf-8;'
        });

        const link = document.createElement("a");
        const url = URL.createObjectURL(blob);
        link.setAttribute("href", url);
        link.setAttribute("download", fileName);
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
        URL.revokeObjectURL(url);

    } catch (error) {
        alert(`Failed to export data: ${error.message}`);
        console.error("Export Error:", error);
    }
}


function clearSearchTable() {
    updateSearchStats([]);
    populateSearchTable([]);
}

function formatISODate(date) {
    if (!date) return '';
    const pad = (num) => num.toString().padStart(2, '0');
    return `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())} ${pad(date.getHours())}:${pad(date.getMinutes())}:${pad(date.getSeconds())}`;
}

document.addEventListener('DOMContentLoaded', function() {
    autoSwitchToggle = document.getElementById('auto-switch-toggle');
  //   fetch(`./api.php?action=get_total_count_all`, { credentials: 'include' })
  // .then(r => r.json())
  // .catch(()=>{});
  // refreshAllTotalCounts();
  autoSwitchToggle = document.getElementById('auto-switch-toggle');

  // Nạp RAM-cache từ LS ngay lập tức (không chờ mạng)
  tc_loadFromLS();

  // Đặt lịch refresh nền (không chặn render UI)
  tc_refreshAllIfExpired(); // tự kiểm TTL, chỉ gọi khi cần

    Chart.register(ChartDataLabels);
    const flatpickrConfig = {
        enableTime: true,
        dateFormat: "d/m/Y H:i",
        time_24hr: true,
        altInput: true,
        altFormat: "Y/m/d H:i",
        plugins: [new confirmDatePlugin({
            confirmText: "OK",
            showAlways: true,
            theme: "light",
            confirmIcon: ""
        })]
    };
    flatpickr("#from-date", flatpickrConfig);
    flatpickr("#to-date", {
        ...flatpickrConfig,       
        defaultDate: new Date()   
    });

    function sortSearchResults() {
        const {
            column,
            direction
        } = currentSortState;
        currentSearchResults.sort((a, b) => {
            let valA = a[column];
            let valB = b[column];
            let comparison = 0;
            const numA = parseFloat(valA);
            const numB = parseFloat(valB);
            if (!isNaN(numA) && !isNaN(numB)) {
                comparison = numA - numB;
            } else if (valA > valB) {
                comparison = 1;
            } else if (valA < valB) {
                comparison = -1;
            }
            return direction === 'asc' ? comparison : -comparison;
        });
        populateSearchTable(currentSearchResults);
        updateSortIcons();
    }

    function updateSortIcons() {
        document.querySelectorAll('#search-table-header th').forEach(th => {
          console.log(th);
          
            const icon = th.querySelector('i');
            const sortKey = th.dataset.sort;
            console.log(icon);
            
            icon.className = 'fas fa-sort';
            if (sortKey === currentSortState.column) {
                icon.className = currentSortState.direction === 'asc' ? 'fas fa-sort-up' : 'fas fa-sort-down';
            }
        });
    }

    document.getElementById('date-shift-controls').addEventListener('click', (e) => {
        if (e.target.classList.contains('date-shift-btn')) {
            const button = e.target;
            document.querySelectorAll('.date-shift-btn').forEach(btn => btn.classList.remove('active'));
            button.classList.add('active');
            const shift = parseInt(button.dataset.shift, 10);
            const targetDate = new Date();
            targetDate.setDate(targetDate.getDate() + shift);
            const dateString = targetDate.toISOString().slice(0, 10);
            if (currentModalDeviceId) {
                fetchAndRenderHourlyReport(currentModalDeviceId, dateString);
            }
        }
    });

    document.getElementById('search-table-header').addEventListener('click', (e) => {
        const header = e.target.closest('th');
        if (!header || !header.dataset.sort) return;
        const sortKey = header.dataset.sort;
        if (currentSortState.column === sortKey) {
            currentSortState.direction = currentSortState.direction === 'asc' ? 'desc' : 'asc';
        } else {
            currentSortState.column = sortKey;
            currentSortState.direction = 'desc';
        }
        sortSearchResults();
    });
    autoSwitchToggle.addEventListener('change', function() {
        if (this.checked) {
            startAutoSwitching();
        } else {
            stopAutoSwitching();
        }
    });

    document.querySelectorAll('.page-nav-link').forEach(link => {
        link.addEventListener('click', (event) => {
            event.preventDefault();

            stopAutoSwitching();
            if (autoSwitchToggle) autoSwitchToggle.checked = false;

            const newView = link.dataset.view;
            if (newView !== currentView) {
                setView(newView);
            }
        });
    });

    gridElement.addEventListener('click', (event) => {
        const actBtn = event.target.closest('.action-indicator');
        if (actBtn) {
        event.stopPropagation();
        const deviceId = actBtn.closest('.info-card')?.dataset.deviceId;
        if (deviceId) openActionsModal(deviceId);
        return;
        }
        
        const card = event.target.closest('.info-card');
        if (!card) return;
        const action = card.dataset.action;
        const deviceId = card.dataset.deviceId;
        if (deviceId) {
            openChartModal(deviceId);
        } else if (action === 'show-more') {
            currentPage = 2;
            enrichDevicesWithActionsAndRender();
        } else if (action === 'show-main') {
            currentPage = 1;
            enrichDevicesWithActionsAndRender();
        }
    });

    const chartModal = document.getElementById('chart-modal');
    document.getElementById('modal-close-btn').addEventListener('click', () => {
        document.getElementById('reset-btn').click();
        chartModal.classList.add('hidden');
        currentModalDeviceId = null;
    });

    const menuIcon = document.getElementById('menu-icon');
    const dropdownMenu = document.getElementById('dropdown-menu');
    menuIcon.addEventListener('click', (event) => {
        event.stopPropagation();
        dropdownMenu.classList.toggle('show');
    });
    window.addEventListener('click', () => dropdownMenu.classList.remove('show'));

    document.getElementById('search-btn').addEventListener('click', () => {
        const fromDateInstance = document.querySelector("#from-date")._flatpickr;
        const toDateInstance = document.querySelector("#to-date")._flatpickr;
        if (fromDateInstance.selectedDates.length === 0 || toDateInstance.selectedDates.length === 0) {
            alert(window.translationManager.translate('SelectBothDates'));
            return;
        }
        const fromDate = formatISODate(fromDateInstance.selectedDates[0]);
        const toDate = formatISODate(toDateInstance.selectedDates[0]);
        if (!currentModalDeviceId) {
            alert('No device selected. Please close and reopen the modal for a device.');
            return;
        }

        const tableContainer = document.getElementById('search-table-container');
        const containerHeight = tableContainer.clientHeight;
        const rowHeight = 22;
        let limit = Math.floor(containerHeight / rowHeight);
        limit = Math.max(1000, limit);
        limit = Math.min(2000, limit);

        const tbody = document.getElementById('search-tbody');
        tbody.innerHTML = '<tr><td colspan="5" class="text-center p-4">Loading...</td></tr>';
        const url = `./api.php?action=search_device&device_id=${currentModalDeviceId}&from=${encodeURIComponent(fromDate)}&to=${encodeURIComponent(toDate)}&limit=${limit}`;
        fetch(url)
            .then(response => {
                if (!response.ok) throw new Error(`HTTP error! status: ${response.status}`);
                return response.json();
            })
            .then(data => {
            if (data.error) throw new Error(data.error);
            const processedData = (data || []).map((row, index) => ({ 
                ...row, 
                serial_id: index + 1 
            }));
                currentSearchResults = processedData;
                updateSearchStats(currentSearchResults);
                currentSortState = {
                    column: 'datetime',
                    direction: 'desc'
                };
                sortSearchResults();
            })
            .catch(error => {
                console.error('Search error:', error);
                currentSearchResults = [];
                const errorMsg = `<tr><td colspan="5" class="text-center p-4 text-red-500">Error: ${error.message}</td></tr>`;
                tbody.innerHTML = errorMsg;
            });
    });

    document.getElementById('export-excel-btn').addEventListener('click', exportToExcel);

    document.getElementById('reset-btn').addEventListener('click', () => {
        document.querySelector("#from-date")._flatpickr.clear(); // Ô "Từ ngày" thì để trống
        document.querySelector("#to-date")._flatpickr.setDate(new Date()); // Ô "Đến ngày" về hiện tại
        clearSearchTable();
    });
    const clientSelect = document.getElementById('client-filter');
    if (clientSelect) {
        clientSelect.addEventListener('change', (e) => {
            currentClientFilter = e.target.value;
            currentPage = 1; // Reset về trang 1 khi lọc
            renderDashboard(); // Render lại
        });
    }

    // 2. Hàm cập nhật danh sách Client vào Dropdown (Tránh trùng lặp)
    window.updateClientFilterOptions = function() {
        const select = document.getElementById('client-filter');
        if (!select) return;

        // Lấy tất cả Client duy nhất từ dữ liệu đã tải
        const allClients = new Set();
        deviceDataMap.forEach(device => {
            if (device.client && device.client.trim() !== '') {
                allClients.add(device.client.trim());
            }
        });

        // Lưu giá trị đang chọn để không bị reset khi polling
        const currentVal = select.value;

        // Xóa cũ, giữ lại option All
        select.innerHTML = `<option value="all">${window.translationManager.translate('All Clients') || 'All Clients'}</option>`;

        // Thêm option mới
        Array.from(allClients).sort().forEach(clientName => {
            const option = document.createElement('option');
            option.value = clientName;
            option.textContent = clientName;
            select.appendChild(option);
        });

        // Khôi phục giá trị đã chọn (nếu nó vẫn còn tồn tại)
        if (Array.from(allClients).includes(currentVal) || currentVal === 'all') {
            select.value = currentVal;
        } else {
            // Nếu client đang chọn bị mất khỏi list (ít khi xảy ra), reset về all
            currentClientFilter = 'all';
            select.value = 'all';
        }
    };
    updateTime();
    setInterval(updateTime, 1000);
    setView(currentView, true);
    vm.loadExtraCountersByTab(currentView);
});

document.addEventListener('visibilitychange', () => {
  if (document.hidden) stopPolling();
  else startPolling();
});
window.addEventListener('beforeunload', stopPolling);

// Lấy device_id đang hiển thị trong popup từ thẻ #info-device-id
function getDeviceIdFromPopup() {
  const span = document.getElementById('info-device-id');
  const v = span ? (span.textContent || span.innerText || '').trim() : '';
  return v || '';
}
// Lấy mã preview từ backend khi mở modal
async function fetchPreviewCodes() {
  try {
    const r = await fetch('./api.php?action=preview_next_codes', { credentials: 'include', cache: 'no-store' });
    if (!r.ok) throw new Error('HTTP ' + r.status);
    return await r.json(); // { next_action_id, next_action_code, next_plan_id, next_plan_code }
  } catch (e) {
    console.warn('preview_next_codes failed', e);
    return { next_action_id:null, next_action_code:'', next_plan_id:null, next_plan_code:'' };
  }
}

// Tạo 1 dòng plan đúng layout (có cột Action ID)
function apMakeRowWithCode(apCode = '') {
  const tr = document.createElement('tr');
  tr.innerHTML = `
    <td class="p-2 border text-center">
      <input class="ap-code w-full text-center bg-gray-100 border rounded px-1 py-0.5" readonly ${apCode ? `value="${apCode}"` : ''}>
    </td>
    <td class="p-2 border">
      <input class="ap-text w-full border rounded px-2 py-1" placeholder="translate('Action plan...')">
    </td>
    <td class="p-2 border">
      <input type="date" class="ap-est w-full border rounded px-2 py-1">
    </td>
    <td class="p-2 border">
      <input class="ap-owner w-full border rounded px-2 py-1" placeholder="translate('Example: John Doe')">
    </td>
    <td class="p-2 border text-center">
      <button type="button" class="ap-del px-2 py-1 rounded bg-red-100 text-red-700">×</button>
    </td>`;
  tr.querySelector('.ap-del').onclick = () => tr.remove();
  return tr;
}

// Điền SSxxxxx & APxxxxx bằng dữ liệu TRẢ VỀ từ backend sau khi submit
function setActionCodesOnUI(json) {
  const issueIdEl = document.getElementById('idxp_issue_id');
  if (issueIdEl && json?.action_code) issueIdEl.value = json.action_code;

  const rows = document.querySelectorAll('#ap-table tbody tr');
  (json?.plans || []).forEach((p, i) => {
    const codeInput = rows[i]?.querySelector('.ap-code');
    if (codeInput && p?.plan_code) codeInput.value = p.plan_code;
  });
}


// —— dùng bubble mặc định ——
// Gắn/xoá thông báo lỗi tùy theo input thay đổi (để bubble biến mất khi người dùng sửa)
function wirePlanValidity(tr) {
  const text  = tr.querySelector('.ap-text');
  const est   = tr.querySelector('.ap-est');
  const owner = tr.querySelector('.ap-owner');

  const clearRowValidity = () => {
    if (text)  text.setCustomValidity('');
    if (est)   est.setCustomValidity('');
    if (owner) owner.setCustomValidity('');
  };

  [text, est, owner].forEach(el => {
    if (!el) return;
    el.addEventListener('input',  clearRowValidity);
    el.addEventListener('change', clearRowValidity);
  });
}


// Kiểm tra: phải có ÍT NHẤT 1 hàng plan đầy đủ (plan + date + owner).
// Nếu thiếu: gắn lỗi vào ô .ap-text đầu tiên và gọi reportValidity() để hiện bubble.
function validateAtLeastOneFullPlan() {
  const rows = document.querySelectorAll('#ap-table tbody tr');
  let hasFull = false, firstText = null;

  for (const tr of rows) {
    const plan  = tr.querySelector('.ap-text');
    const est   = tr.querySelector('.ap-est');
    const owner = tr.querySelector('.ap-owner');
    const vP = (plan?.value  || '').trim();
    const vE = (est?.value   || '').trim();
    const vO = (owner?.value || '').trim();
    if (!firstText) firstText = plan;
    if (vP && vE && vO) { hasFull = true; break; }
  }

  if (!hasFull) {
    if (firstText) {
      firstText.setCustomValidity('Please add at least one full action plan (plan + date + owner).');
      firstText.reportValidity();
      // xoá lỗi khi người dùng gõ lại
      firstText.addEventListener('input', () => firstText.setCustomValidity(''), { once:true });
    }
    return false;
  }
  // clear mọi customValidity còn sót
  document.querySelectorAll('#ap-table .ap-text, #ap-table .ap-est, #ap-table .ap-owner')
    .forEach(el => el && el.setCustomValidity(''));
  return true;
}

// ===== Add Action: open modal from any "+ Add Action" button =====
(function () {

  

  // Lấy Device ID đang hiển thị trong popup thiết bị (#info-device-id)
  function getDeviceIdFromInfoPanel() {
    const el = document.getElementById('info-device-id');
    return (el ? (el.textContent || el.innerText || '').trim() : '');
  }

  const modal     = document.getElementById('idx-action-modal');
  const form      = document.getElementById('idx-action-modal-form');
  const closeBtn  = document.getElementById('idx-action-close');
  const cancelBtn = document.getElementById('idx-action-cancel');

  const fDevice   = document.getElementById('idxp_device_id');
  // const fIssue    = document.getElementById('idxp_issue');
  // const fPlan     = document.getElementById('idxp_plan');
  // const fPriority = document.getElementById('idxp_priority');
  // const fDue      = document.getElementById('idxp_due');
  // const fCreator  = document.getElementById('idxp_creator');
  // const fTitle    = document.getElementById('idxp_title');
  // const fOwner    = document.getElementById('idxp_assignee');
  // const fTarget   = document.getElementById('idxp_target');
  // const fActual   = document.getElementById('idxp_actual');
  // const fIssueType = document.getElementById('idxp_issue_type');
  if (!modal || !form) return;


  window.__apRecycle = [];

  function parseApNum(code) {
    const n = parseInt(String(code || '').replace(/\D/g, ''), 10);
    return Number.isFinite(n) ? n : null;
  }

  function allocPlanCode() {
    if (window.__apRecycle.length) {
      window.__apRecycle.sort((a,b) => a - b);      // lấy số nhỏ nhất
      const n = window.__apRecycle.shift();
      return 'AP' + String(n).padStart(5,'0');
    }
    const n = window.__nextPlanNum++;
    return 'AP' + String(n).padStart(5,'0');
  }

  function wireRecycleOnDelete(tr) {
    const delBtn = tr.querySelector('.ap-remove, .ap-del, [data-ap-remove]');
    if (!delBtn) return;
    delBtn.onclick = () => {
      const code = tr.querySelector('.ap-code')?.value || '';
      const num  = parseApNum(code);
      if (num) window.__apRecycle.push(num);        // trả số về pool
      tr.remove();
    };
  }

const DEFAULT_ISSUE_TYPES = [
  { code:'Current Cycle', name:'Cycle'         },
  { code:'Efficiency',    name:'Efficiency'    },
  { code:'Cavity',        name:'Cavity'        },
  { code:'Defect',        name:'Defect'        },
];

async function loadIssueTypes() {
  // bảo đảm phần tử là <select>
  const sel = ensureIssueTypeSelect();
  if (!sel) return;

  let payload = null;
  try {
    const r  = await fetch('./api.php?action=get_issue_types', {cache:'no-store', credentials:'include'});
    const ct = (r.headers.get('content-type') || '').toLowerCase();
    payload  = ct.includes('application/json') ? await r.json() : null;
  } catch {}

  let items = [];
  if (Array.isArray(payload)) items = payload;
  else if (payload?.data && Array.isArray(payload.data)) items = payload.data;
  else if (payload?.rows && Array.isArray(payload.rows)) items = payload.rows;
  else if (payload?.types && Array.isArray(payload.types)) items = payload.types;
  else items = DEFAULT_ISSUE_TYPES;

    sel.innerHTML =
    `<option value="" data-translate="-- Select issue type --">-- Select issue type --</option>` +
    items.map(o => {
      const val = (o.code ?? o.value ?? o).toString();
      const txt = (o.name ?? o.label ?? o.code ?? o.value ?? o).toString();
      return `<option value="${val}">${txt}</option>`;
    }).join('');

  sel.required = true;     // 🔒 đảm bảo bắt buộc ở runtime
  sel.value = '';          // 🔒 reset về trạng thái chưa chọn
  sel.selectedIndex = 0;   // 🔒 đứng ở option placeholder
  sel.disabled = false;



}
  function safeNum(v) {
  if (v === null || v === undefined || v === '') return '';
  const n = Number(v);
  return Number.isFinite(n) ? n : v;
}

function setRO(selectors, value, labelText) {
  // selectors: string | string[]
  const list = Array.isArray(selectors) ? selectors : [selectors];

  // 1) thử theo list selector
  let el = null;
  for (const sel of list) {
    el = document.querySelector(sel);
    if (el) break;
  }

  // 2) fallback: tìm theo label có text khớp
  if (!el && labelText) {
    const labels = document.querySelectorAll('#idx-action-modal label');
    for (const lb of labels) {
      if (!lb.textContent) continue;
      if (lb.textContent.trim().toLowerCase() === labelText.toLowerCase()) {
        // cùng group với input
        el = lb.parentElement?.querySelector('input,select,textarea');
        if (el) break;
      }
    }
  }

  if (!el) {
    console.warn('', list, labelText);
    return false;
  }

  const v = (value ?? '') + '';
  if ('value' in el) el.value = v; else el.textContent = v;
  if ('readOnly' in el) { el.readOnly = true; el.classList.add('bg-gray-100'); }
  return true;
}


function fillFromInfoPanelCache(deviceId) {
  const c = window.__infoPanelCache;
  if (!c || String(c.device_id) !== String(deviceId)) return false;

  // Actuals
  setRO('#idxp_actual_cavity',   c.actual_cavities);
  setRO('#idxp_actual_eff',      c.actual_efficiency);
  setRO('#idxp_actual_cycle',    c.actual_cycle);

  // Targets & limits
  setRO('#idxp_target',          c.target_limit);
  setRO('#idxp_mold_cavity',     c.cavities);
// Efficiency Required / Upper / Lower
  setRO(['#idxp_eff_required', '#idxp_efficiency_required', '[name="efficiency_required"]'],
        c.efficiency_lower_limit, 'Efficiency Required');

  setRO(['#idxp_upper', '#idxp_upper_limit', '[name="upper_limit"]'],
        c.upper_limit, 'Upper Limit');

  setRO(['#idxp_lower', '#idxp_lower_limit', '[name="lower_limit"]'],
        c.lower_limit, 'Lower Limit');
  return true;
}
function ensureIssueTypeSelect() {
  let el = document.getElementById('idxp_issue_type');
  if (!el) return null;

  // Nếu đã là <select>, đảm bảo bật required
  if (el.tagName.toLowerCase() === 'select') {
    el.required = true;            // 🔒 luôn bắt buộc
    return el;
  }

  // Nếu chưa là <select> (vd: input placeholder), thay thế nhưng giữ nguyên thuộc tính quan trọng
  const wasRequired = el.hasAttribute('required'); // nếu bạn đã đặt sẵn ở HTML
  const sel = document.createElement('select');
  sel.id   = el.id;
  sel.name = el.getAttribute('name') || 'issue_type';
  sel.className = (el.className || '').replace('bg-gray-100','bg-white');

  // copy một số attrs hữu ích (tùy bạn thêm)
  if (wasRequired) sel.setAttribute('required', '');
  // hoặc ép bắt buộc luôn (đúng theo yêu cầu hiện tại):
  sel.required = true;             // 🔒 luôn bắt buộc

  // thay thế
  el.replaceWith(sel);
  return sel;
}


  async function ensureLoggedIn() {
    try {
      const r = await fetch('./api.php?action=whoami', { cache:'no-store' });
      if (!r.ok) throw 0;
      window.CURRENT_USER = await r.json();
      return true;
    } catch {
      alert(window.translationManager.translate('You need to log in to create an Action.'));
      return false;
    }
  }
  // A) Click vào bất kỳ nút có id #idx-add-action hoặc #idx-add-action-popup -> mở modal
  document.addEventListener('click', async (e) => {
    const btn = e.target.closest('#idx-add-action, #idx-add-action-popup');
    if (!btn) return;
    e.preventDefault();
    if (!(await ensureLoggedIn())) return;

    const deviceId = getDeviceIdFromInfoPanel();
    if (!deviceId) { alert('Device ID not found in the device popup.'); return; }

   // reset + set device id
    form.reset();
    fDevice.value = deviceId;
    window.currentModalDeviceId = deviceId;

    // >>> Đổ từ info panel (raw) — không lệ thuộc API, không bị format
    const ok = fillFromInfoPanelCache(deviceId);

    // (tuỳ bạn) nếu muốn fallback khi cache không cùng device:
    if (!ok) {
      // fillLimitsFromDeviceData(deviceId);
    }

    await loadIssueTypes();
// === HIỂN THỊ MÃ NGAY KHI MỞ (version recycle) ===
const tbody = document.querySelector('#ap-table tbody');
if (tbody) tbody.innerHTML = '';

try {
  const pv = await fetchPreviewCodes();
  
  // Issue ID trên đầu form
  const issueInput = document.getElementById('idxp_issue_id');
  if (issueInput) issueInput.value = pv.next_action_code || '';

  // reset pool & chuẩn hóa nextPlan
  window.__apRecycle = [];
  window.__nextPlanNum = Number(pv.next_plan_id || 1);

  // dòng đầu tiên
  const firstRow = apMakeRowWithCode(allocPlanCode());
  wireRecycleOnDelete(firstRow);
  wirePlanValidity(firstRow); 
  tbody?.appendChild(firstRow);

  // nút + Add Plan (chỉ 1 handler)
  const addBtn = document.getElementById('ap-add');
  if (addBtn) {
    addBtn.onclick = () => {
      const row = apMakeRowWithCode(allocPlanCode());
      wireRecycleOnDelete(row);
      wirePlanValidity(row); 
      tbody?.appendChild(row);
    };
  }
} catch {
  // fallback: vẫn tạo 1 dòng rỗng
  const r = apMakeRowWithCode('');
  wireRecycleOnDelete(r);
  tbody?.appendChild(r);
}

    // LẤY TARGET TỪ PANEL VÀ ĐỔ VÀO Ô READONLY
// const tEl = document.getElementById('info-tg');
// if (fTarget) fTarget.value = tEl && tEl.textContent ? tEl.textContent.replace(/[^\d.\-]/g,'') : '';
// if (fActual) fActual.value = '';
    
    // load owners for the dropdown
// async function loadUsersForAssignee() {
//   try {
//     const r = await fetch('./api.php?action=list_users', {
//       credentials: 'include',           
//       cache: 'no-store',
//       headers: { 'Accept':'application/json' }
//     });

//     if (r.status === 401) {
//       // Guest mode: ẩn owner, hiện "Your name"
//       document.querySelector('[data-assignee-wrap]')?.classList.add('hidden');
//       document.querySelector('[data-guest-name-wrap]')?.classList.remove('hidden');
//       return; // ĐỪNG gọi r.json() nữa
//     }

//     if (!r.ok) throw new Error('HTTP ' + r.status);
//     const users = await r.json();

//     // Fill select
//     const sel = document.getElementById('idxp_assignee');
//     if (sel) {
//       sel.innerHTML = '<option value="">-- Owner (optional) --</option>' +
//         users.map(u => `<option value="${u.id}">${u.username}</option>`).join('');
//       document.querySelector('[data-assignee-wrap]')?.classList.remove('hidden');
//       document.querySelector('[data-guest-name-wrap]')?.classList.add('hidden');
//     }
//   } catch (err) {
//     console.error('loadUsersForAssignee error:', err);
//     document.querySelector('[data-assignee-wrap]')?.classList.add('hidden');
//     document.querySelector('[data-guest-name-wrap]')?.classList.remove('hidden');
//   }
// }
//     await loadUsersForAssignee();
    modal.classList.remove('hidden');
  });

  // B) Đóng modal
  function closeModal() { modal.classList.add('hidden'); form.reset(); }
  closeBtn?.addEventListener('click', (e)=>{ e.preventDefault(); closeModal(); });
  cancelBtn?.addEventListener('click', (e)=>{ e.preventDefault(); closeModal(); });
  modal.addEventListener('click', (e)=>{ if (e.target === modal) closeModal(); });

// C) Submit -> tạo action (PENDING) qua create_action_public
let __creating = false;

form.addEventListener('submit', async (e) => {
  e.preventDefault();

  // 1) Validate HTML5 + yêu cầu có ít nhất 1 plan đầy đủ
  if (!form.reportValidity()) return;
  if (!validateAtLeastOneFullPlan()) return;

  // 2) Chặn double submit
  if (__creating) return;
  __creating = true;

  // 3) Busy UI
  const submitBtn = form.querySelector('[type="submit"]');
  const prevBtnHtml = submitBtn?.innerHTML;
  submitBtn?.setAttribute('disabled', 'disabled');
  if (submitBtn) submitBtn.innerHTML = `<i class="fas fa-circle-notch fa-spin mr-2"></i>Creating…`;
  form.querySelectorAll('input, select, textarea, button').forEach(el => el.disabled = true);
  if (typeof window.setFormBusy === 'function') window.setFormBusy(true);

  try {
    // ======= build payload
    const fdForm = new FormData(e.currentTarget);
    const get  = (name) => (fdForm.get(name) ?? '').toString().trim();
    const pick = (...vals) => (vals.find(v => (v ?? '').toString().trim() !== '') ?? '').toString().trim();

    const deviceId = pick(get('device_id'), document.getElementById('idxp_device_id')?.value, window.currentModalDeviceId);
    const issue    = pick(get('issue'), get('title'), document.getElementById('idxp_issue')?.value, document.getElementById('idxp_title')?.value);
    if (!deviceId) throw new Error('Missing device_id');

    const assignee  = (document.getElementById('idxp_assignee')?.value || '').trim();
    const issueType = (document.getElementById('idxp_issue_type')?.value || '').trim();

    const plans = (window.collectActionPlans?.() || []);
    const shortForm = [
      { label: 'Description of issue', value: issue },
      ...plans.map((r,i) => ({
        label: `Plan #${i+1}${r.est ? ' ('+r.est+')' : ''}`,
        value: (r.plan   || '').trim(),
        est:   (r.est    || '').trim(),
        owner: (r.owner  || '').trim()
      }))
    ];

    // Idempotency (giảm trùng nếu user spam hoặc net lag)
    const idemKey = (crypto?.randomUUID && crypto.randomUUID()) ||
                    (Date.now() + '-' + Math.random().toString(16).slice(2));

    // ======= FormData body (giữ nguyên như cũ)
    const fd = new FormData();
    fd.append('action', 'create_action_public'); // (giữ để tương thích backend đọc POST)
    fd.append('device_id', deviceId);
    fd.append('title', issue);
    if (assignee)  fd.append('assigned_to_user_id', assignee);
    if (issueType) fd.append('issue_type', issueType);
    fd.append('short_form', JSON.stringify(shortForm));
    fd.append('idempotency_key', idemKey);

    // ======= call API (CÁCH 1): đưa action lên URL để dễ thấy trong Network
    const API = window.API || './api.php';
    const url = `${API}?action=create_action_public`; // <— thay đổi duy nhất quan trọng

    // (tuỳ chọn) debug payload
    // { const preview = {}; fd.forEach((v,k)=>preview[k]=v); console.debug('[create_action_public]', url, preview); }

    const r = await fetch(url, {
      method: 'POST',
      body: fd,
      credentials: 'include',
      cache: 'no-store'
    });

    const raw = await r.text();
    let j = null;
    try { j = JSON.parse(raw); } catch {}

    if (!r.ok || (j && (j.error || j.status === 'error'))) {
      const msg = (j && (j.error || j.message)) || raw || ('HTTP ' + r.status);
      throw new Error(msg);
    }

    // ======= SUCCESS: đóng form + hiển thị action vừa tạo
    const actionCode = j?.action_code || (j?.data && j.data.action_code) || '';
    showFlash?.(window.translationManager.translate('Action created successfully') + (actionCode ? ` (${actionCode})` : ''), 'success');

    // Đóng modal tạo action
    document.getElementById('idx-action-modal')?.classList.add('hidden');
    form.reset();

    // Refresh các bộ đếm & grid badge
    vm?.loadExtraCountersByTab?.(currentView);
    enrichDevicesWithActionsAndRender?.();

    // Mở modal Issues cho đúng device, giúp người dùng thấy record vừa tạo
    if (window.currentModalDeviceId) {
      openActionsModal(window.currentModalDeviceId);
    }

  } catch (err) {
    console.error('Create action failed:', err);
    showFlash?.((err && err.message) || 'Create action failed', 'error');
  } finally {
    // 4) Khôi phục UI dù success hay error
    __creating = false;
    if (typeof window.setFormBusy === 'function') window.setFormBusy(false);
    submitBtn?.removeAttribute('disabled');
    if (submitBtn) submitBtn.innerHTML = prevBtnHtml || 'Create';
    form.querySelectorAll('input, select, textarea, button').forEach(el => el.disabled = false);
  }
});




})();


window.collectActionPlans = () => {
  const rows = document.querySelectorAll('#ap-table tbody tr');
  return [...rows].map(tr => ({
    plan:  tr.querySelector('.ap-text')?.value.trim() || '',
    est:   tr.querySelector('.ap-est')?.value || '',
    owner: tr.querySelector('.ap-owner')?.value.trim() || ''
  })).filter(r => r.plan || r.est || r.owner);
};


// logic account-level flash message (toast) ở góc dưới
(function () {
  // chạy fn khi body sẵn sàng
  function onBodyReady(fn) {
    if (document.body) fn();
    else window.addEventListener('DOMContentLoaded', fn, { once: true });
  }

  // Toast bottom-center
  window.showFlash = function (msg, type = 'success') {
    onBodyReady(() => {
      // root chứa các toast
      let root = document.getElementById('toast-root');
      if (!root) {
        root = document.createElement('div');
        root.id = 'toast-root';
        Object.assign(root.style, {
          position: 'fixed',
          left: '50%',
          bottom: 'calc(16px + env(safe-area-inset-bottom))',
          transform: 'translateX(-50%)',
          zIndex: '9999',
          display: 'flex',
          flexDirection: 'column',
          alignItems: 'center',
          gap: '8px',
          pointerEvents: 'none',
        });
        document.body.appendChild(root);
      }

      const box = document.createElement('div');
      box.textContent = msg;
      Object.assign(box.style, {
        pointerEvents: 'auto',
        padding: '10px 14px',
        borderRadius: '10px',
        boxShadow: '0 6px 20px rgba(0,0,0,.18)',
        color: '#fff',
        fontWeight: '600',
        maxWidth: '90vw',
        textAlign: 'center',
        backdropFilter: 'blur(4px)',
        background: (type === 'error') ? '#dc2626'
                 : (type === 'info')  ? '#34c4b8ff'
                 : '#16a34a', // success
        opacity: '0',
        transform: 'translateY(12px)',
        transition: 'opacity .25s ease, transform .25s ease',
      });

      root.appendChild(box);

      // fade-in
      requestAnimationFrame(() => {
        box.style.opacity = '1';
        box.style.transform = 'translateY(0)';
      });

      // auto-hide
      setTimeout(() => {
        box.style.opacity = '0';
        box.style.transform = 'translateY(12px)';
      }, 2600);

      // remove
      setTimeout(() => {
        box.remove();
        if (!root.childElementCount) root.remove();
      }, 3000);
    });
  };

  // tiện test nhanh trên console
  window.testToast = () => showFlash('Test bottom-center', 'info');
})();








document.addEventListener('DOMContentLoaded', async () => {
  // 1) Xác định "gốc project" (nếu đang ở /backend/* thì lùi về trước /backend/)
  function projectBase() {
    const path = location.pathname;
    const i = path.toLowerCase().indexOf('/backend/');
    const basePath = (i >= 0) ? path.slice(0, i + 1) : path.replace(/\/[^/]*$/, '/');
    return new URL(basePath, location.origin);           // ví dụ: https://host/fmcs/
  }
  const BASE = projectBase();

  // 2) Helper gắn ?return=... an toàn theo BASE
  function withReturn(relHref, retRel) {
    const u   = new URL(relHref, BASE);
    const ret = new URL(retRel, BASE);
    u.searchParams.set('return', ret.pathname + ret.search + ret.hash);
    return u.pathname + '?' + u.searchParams.toString();
  }

  // 3) Lấy trạng thái đăng nhập (dùng whoami cho chắc ăn)
  async function getAuth() {
    try {
      // Đặt window.API = 'backend/api.php' nếu API bạn ở backend/.
      const api = new URL((window.API || 'api.php'), BASE).href;
      const r   = await fetch(api + '?action=whoami', { credentials:'include', cache:'no-store' });
      if (!r.ok) return { logged_in:false, role:'', username:null };
      const u   = await r.json();
      return { logged_in:true, role:(u.role || ''), username:(u.username || null) };
    } catch {
      return { logged_in:false, role:'', username:null };
    }
  }

  const auth   = await getAuth();
  const role   = auth.role || '';
  const logged = !!auth.logged_in;
  window.__EMS_AUTH__ = auth;

  // 4) Gom đủ mọi biến thể nút (nếu bạn có menu mobile/desktop trùng id)
  const loginEls   = Array.from(document.querySelectorAll('#menu-login'));
  const logoutEls  = Array.from(document.querySelectorAll('#menu-logout'));
  const settingEls = Array.from(document.querySelectorAll('#nav-system-settings, a[data-nav="system-settings"], a[href="backend/backend.php"], a[href="/backend/backend.php"]'));

  const hideAll = (els) => els.forEach(el => { el.classList.add('hidden'); el.style.display = 'none'; });
  const showAll = (els) => els.forEach(el => { el.classList.remove('hidden'); el.style.display = ''; });

  // 5) Khai báo các URL chuẩn neo theo BASE (không hard-code domain/thư mục cha)
  const URL_INDEX        = 'index.html';
  const URL_BACKEND_HOME = 'backend/backend.php';
  const URL_LOGIN        = 'backend/login.php';
  const URL_LOGOUT       = 'backend/logout.php';

  // 6) Quy tắc System Settings cho non-admin
  // if (logged && role !== 'admin') {
  //   settingEls.forEach(a => {
  //     a.addEventListener('click', (e) => {
  //       e.preventDefault();
  //       (window.showFlash ? showFlash : alert)(
  //         'Access denied: your account does not have permission to access System Settings.'
  //       );
  //     });
  //     a.style.opacity = '0.6';
  //     a.style.cursor  = 'not-allowed';
  //   });
  // }

  // 7) Toggle Login/Logout + set href
  if (logged) {
    hideAll(loginEls);
    showAll(logoutEls);

    // System Settings: vào thẳng backend.php
    settingEls.forEach(a => a.setAttribute('href', new URL(URL_BACKEND_HOME, BASE).pathname));

    // Logout xong quay về index của project
    logoutEls.forEach(a => a.setAttribute('href', withReturn(URL_LOGOUT, URL_INDEX)));
    logoutEls.forEach(a => {
      a.addEventListener('click', () => { try { clearToken(); } catch {} });
    });
  } else {
    showAll(loginEls);
    hideAll(logoutEls);

    // System Settings: qua login rồi về backend.php
    settingEls.forEach(a => a.setAttribute('href', withReturn(URL_LOGIN, URL_BACKEND_HOME)));

    // Login xong quay về index của project
    loginEls.forEach(a => a.setAttribute('href', withReturn(URL_LOGIN, URL_INDEX)));
  }
});