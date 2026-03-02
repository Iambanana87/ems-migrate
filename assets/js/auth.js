/* assets/js/fmcs-auth.js */
(function (global) {
  'use strict';

  const STORAGE_KEY = 'ems_token';

  function getToken() {
    try { return localStorage.getItem(STORAGE_KEY) || ''; } catch { return ''; }
  }
  function setToken(t) {
    try { t ? localStorage.setItem(STORAGE_KEY, t) : localStorage.removeItem(STORAGE_KEY); } catch {}
  }
  function clearToken() { setToken(''); }

  function authHeaders() {
    const t = getToken();
    return t ? { Authorization: `Bearer ${t}` } : {};
  }

  function apiFetch(url, opts = {}) {
    const headers = { ...(opts.headers || {}), ...authHeaders() };
    return fetch(url, { ...opts, headers, credentials: 'same-origin', cache: 'no-store' });
  }

  async function getJSON(u) {
    const r = await apiFetch(u);
    if (!r.ok) throw new Error((await r.text()) || r.status);
    return r.json();
  }

  async function postForm(action, data, apiBase) {
    // apiBase: ví dụ '../api_action.php'
    apiBase = new URL(apiBase || (global.API || 'api_action.php'), location.href).href;
    const url = new URL(apiBase);
    url.searchParams.set('action', action);

    const r = await apiFetch(url.href, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
      body: new URLSearchParams(data || {})
    });

    const text = await r.text();
    if (!r.ok) throw new Error(text || r.status);
    try { return JSON.parse(text); } catch { return text; }
  }

  async function whoami(apiBase) {
    apiBase = new URL(apiBase || (global.API || 'api_action.php'), location.href).href;
    return getJSON(`${apiBase}?action=whoami&_=${Date.now()}`);
  }

  function updateMenuByWhoAmI(me) {
    try {
      const pill = document.getElementById('user-pill');
      if (pill) pill.textContent = me?.username || 'signed in';

      const menuLogin = document.getElementById('menu-login');
      const menuSettings = document.getElementById('menu-settings');

      if (me?.logged_in) {
        menuLogin && menuLogin.classList.add('hidden');
        menuSettings && menuSettings.setAttribute('href', './backend.php');
      } else {
        menuLogin && menuLogin.classList.remove('hidden');
        menuSettings && menuSettings.setAttribute('href', './login.php?redirect=backend.php');
      }
    } catch {}
  }

  async function enforce(apiBase, opts = {}) {
    const {
      requireAdmin = false,
      redirect = 'login.php',
      returnTo = location.pathname.split('/').pop() || 'index.html'
    } = opts;

    try {
      const me = await whoami(apiBase);
      updateMenuByWhoAmI(me);

      if (!me.logged_in) throw new Error('Login required');
      if (requireAdmin && (me.role || '') !== 'admin') throw new Error('Admin only');

      return true;
    } catch (err) {
      const main = document.querySelector('main') || document.body;
      const ret = encodeURIComponent(returnTo);
      main.innerHTML = `
        <div class="max-w-xl mx-auto mt-10 border rounded bg-yellow-50 p-4">
          <div class="font-semibold mb-1">${err.message}</div>
          <a class="inline-block mt-2 px-3 py-2 rounded bg-blue-600 text-white"
             href="./${redirect}?redirect=${ret}">
            Go to Login
          </a>
        </div>`;
      return false;
    }
  }

  function enforceAdmin(apiBase, returnTo) {
    return enforce(apiBase, { requireAdmin: true, returnTo });
  }

  // Gắn lên window để trang khác dùng
  global.EMSAuth = {
    // token
    getToken, setToken, clearToken,
    // fetch helpers
    authHeaders, apiFetch, getJSON, postForm,
    // whoami & UI
    whoami, updateMenuByWhoAmI,
    // gates
    enforce, enforceAdmin,
  };
})(window);
