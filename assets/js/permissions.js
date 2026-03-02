/* /assets/js/permissions.js */
(function (global) {
  'use strict';

  // === CẤU HÌNH ===
  // Gọi trực tiếp IAM (cùng host/port) – nếu CORS chặn, dùng proxy PHP ở mục (3)
  const IAM_URL = 'http://192.168.110.2/web_develop/iam/cip3/?c=PermissionController&m=getPermissionByUsername';

  // Cache theo user để tránh gọi quá nhiều
  const CACHE_TTL_MS =0; 
  const k = (u) => `ems_perm:${u}`;

  async function _fetchFromIAM(username) {
    const res = await fetch(IAM_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
      body: JSON.stringify({ username })
    });
    if (!res.ok) throw new Error(`IAM HTTP ${res.status}`);
    return await res.json();
  }

  function _saveCache(username, data) {
    try {
      localStorage.setItem(k(username), JSON.stringify({ ts: Date.now(), data }));
    } catch {}
  }
  function _readCache(username) {
    try {
      const raw = localStorage.getItem(k(username));
      if (!raw) return null;
      const obj = JSON.parse(raw);
      if (!obj || !obj.ts) return null;
      if (Date.now() - obj.ts > CACHE_TTL_MS) return null;
      return obj.data;
    } catch { return null; }
  }

  const Permission = {
    _username: null,
    _data: null,        // raw response từ IAM
    _set(username, data) { this._username = username; this._data = data; },

    // Khởi tạo: gọi IAM (có cache)
    async init(username, opts = { forceRefresh: false }) {
      if (!username) throw new Error('Missing username for permissions');
      this._username = username;

    //   if (!opts.forceRefresh) {
    //     const cached = _readCache(username);
    //     if (cached) { this._data = cached; return cached; }
    //   }
      const fresh = await _fetchFromIAM(username);
      this._data = fresh;
      _saveCache(username, fresh);
      return fresh;
    },

    // Tuỳ theo IAM trả về format, điều chỉnh 2 hàm dưới cho đúng structure

    _list() {
    //   if (!this._data) return [];
      // ƯU TIÊN: nếu có data.permissions là array

        if (!this._data) return [];
   // Trường hợp API trả về MẢNG top-level: ["auth.login", "device.delete", ...]
   if (Array.isArray(this._data)) return this._data;
      if (Array.isArray(this._data.permissions)) return this._data.permissions;
      if (this._data.data && Array.isArray(this._data.data.permissions)) return this._data.data.permissions;
      // Nếu API trả theo key khác (vd roles -> [ {code}, ... ])
      if (this._data.roles && Array.isArray(this._data.roles)) {
        return this._data.roles.map(r => r.code || r.name).filter(Boolean);
      }
      return [];
    },

    has(code) {
      code = String(code || '').toUpperCase();
      return this._list().some(p => String(p).toUpperCase() === code);
    },
    any(codes = []) {
      return codes.some(c => this.has(c));
    },
    all(codes = []) {
      return codes.every(c => this.has(c));
    },
    data() { return this._data || {}; },

    // Helper: ẩn/hiện tự động theo data-perm / data-perm-any
    applyVisibility(root = document) {
      const list = this._list();
      const has = (c) => list.map(x=>String(x).toUpperCase()).includes(String(c).toUpperCase());

      root.querySelectorAll('[data-perm]').forEach(el => {
        const need = el.getAttribute('data-perm');
        el.style.display = has(need) ? '' : 'none';
      });
      root.querySelectorAll('[data-perm-any]').forEach(el => {
        const needAny = (el.getAttribute('data-perm-any') || '').split(',').map(s=>s.trim()).filter(Boolean);
        el.style.display = needAny.some(need => has(need)) ? '' : 'none';
      });
    }
  };

  global.EMSPermission = Permission;
})(window);
