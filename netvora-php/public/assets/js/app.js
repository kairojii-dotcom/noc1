/* =====================================================================
   NETVORA NOC — Frontend API client & helpers (vanilla + Alpine friendly)
   ===================================================================== */
const NV = {
    base: '',
    token() { return localStorage.getItem('nv_access'); },
    refreshToken() { return localStorage.getItem('nv_refresh'); },
    user() { try { return JSON.parse(localStorage.getItem('nv_user') || 'null'); } catch { return null; } },

    setSession(data) {
        const user = data.user || {};
        user.role = user.role || user.role_code; // normalize
        localStorage.setItem('nv_access', data.tokens.access_token);
        localStorage.setItem('nv_refresh', data.tokens.refresh_token);
        localStorage.setItem('nv_user', JSON.stringify(user));
        localStorage.setItem('nv_perms', JSON.stringify(data.permissions || []));
    },
    clear() { ['nv_access', 'nv_refresh', 'nv_user', 'nv_perms'].forEach(k => localStorage.removeItem(k)); },

    async api(path, { method = 'GET', body = null, auth = true, retry = true } = {}) {
        const headers = { 'Content-Type': 'application/json' };
        if (auth && this.token()) headers['Authorization'] = 'Bearer ' + this.token();
        const res = await fetch(this.base + '/api' + path, {
            method, headers, body: body ? JSON.stringify(body) : null,
        });
        if (res.status === 401 && auth && retry && this.refreshToken()) {
            const ok = await this.tryRefresh();
            if (ok) return this.api(path, { method, body, auth, retry: false });
        }
        const json = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(json.message || ('HTTP ' + res.status));
        return json;
    },

    async tryRefresh() {
        try {
            const res = await fetch(this.base + '/api/auth/refresh', {
                method: 'POST', headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ refresh_token: this.refreshToken() }),
            });
            if (!res.ok) { this.clear(); return false; }
            const json = await res.json();
            localStorage.setItem('nv_access', json.data.tokens.access_token);
            localStorage.setItem('nv_refresh', json.data.tokens.refresh_token);
            return true;
        } catch { this.clear(); return false; }
    },

    async login(email, password, domain) {
        const json = await this.api('/auth/login', { method: 'POST', auth: false, body: { email, password, domain } });
        this.setSession(json.data);
        return json.data;
    },

    async logout() {
        try { await this.api('/auth/logout', { method: 'POST', body: { refresh_token: this.refreshToken() } }); } catch {}
        this.clear(); location.href = '/login';
    },

    guard(role) {
        const u = this.user();
        if (!u) { location.href = '/login'; return false; }
        if (role && u.role !== role && u.role !== 'super_admin') { location.href = '/dashboard'; return false; }
        return true;
    },

    toast(msg, type = 'ok') {
        let wrap = document.querySelector('.toast-wrap');
        if (!wrap) { wrap = document.createElement('div'); wrap.className = 'toast-wrap'; document.body.appendChild(wrap); }
        const t = document.createElement('div');
        t.className = 'toast ' + type;
        t.innerHTML = msg;
        wrap.appendChild(t);
        setTimeout(() => { t.style.opacity = '0'; setTimeout(() => t.remove(), 300); }, 3200);
    },

    fmt(n) { return new Intl.NumberFormat('id-ID').format(n || 0); },
    rupiah(n) { return 'Rp ' + this.fmt(n); },
    timeAgo(ts) {
        if (!ts) return '-';
        const s = Math.floor((Date.now() - new Date(ts).getTime()) / 1000);
        if (s < 60) return s + ' detik lalu';
        if (s < 3600) return Math.floor(s / 60) + ' menit lalu';
        if (s < 86400) return Math.floor(s / 3600) + ' jam lalu';
        return Math.floor(s / 86400) + ' hari lalu';
    },
};

/* ApexCharts shared theme (emerald) */
const NV_CHART = {
    green: '#16f08f', blue: '#38bdf8', red: '#ff5470', amber: '#ffb43a', violet: '#a78bfa',
    base(extra = {}) {
        return Object.assign({
            chart: { background: 'transparent', toolbar: { show: false }, fontFamily: 'Outfit, sans-serif', animations: { easing: 'easeinout', speed: 600 } },
            theme: { mode: 'dark' },
            grid: { borderColor: 'rgba(45,212,160,0.08)', strokeDashArray: 4 },
            tooltip: { theme: 'dark' },
            dataLabels: { enabled: false },
        }, extra);
    },
    donut(labels, series, colors) {
        return this.base({
            chart: { type: 'donut', height: 230 },
            labels, series, colors,
            stroke: { width: 0 },
            plotOptions: { pie: { donut: { size: '74%', labels: { show: true, total: { show: true, label: 'Total', color: '#7d9c8c', formatter: () => series.reduce((a, b) => a + b, 0) } } } } },
            legend: { show: false },
        });
    },
    area(categories, seriesArr, colors) {
        return this.base({
            chart: { type: 'area', height: 220, sparkline: { enabled: false } },
            series: seriesArr, colors,
            xaxis: { categories, labels: { style: { colors: '#4d6258' } }, axisBorder: { show: false }, axisTicks: { show: false } },
            yaxis: { labels: { style: { colors: '#4d6258' } } },
            stroke: { curve: 'smooth', width: 2.5 },
            fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.35, opacityTo: 0.02, stops: [0, 95] } },
            legend: { show: true, labels: { colors: '#7d9c8c' }, markers: { radius: 12 } },
        });
    },
};
window.NV = NV; window.NV_CHART = NV_CHART;
