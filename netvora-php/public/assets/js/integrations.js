/* Extra live integrations layered on top of the generic module registry. */
(() => {
    if (!window.NV_MODULES || !window.NV_MODULES.tenant) return;

    const F = window.F || {};
    const chip = F.chip || ((v) => `<span class="chip ${('' + v).toLowerCase()}">${v ?? '-'}</span>`);

    window.NV_MODULES.tenant['pppoe-users'] = {
        type: 'crud',
        readonly: true,
        title: 'PPPoE Users',
        icon: 'fa-users-line',
        endpoint: '/pppoe-users',
        columns: [
            { key: 'router_name', label: 'MikroTik' },
            { key: 'user', label: 'User PPPoE' },
            { key: 'ip_address', label: 'IP' },
            { key: 'profile', label: 'Profile' },
            { key: 'limit', label: 'Limit' },
            { key: 'status', label: 'Status', fmt: chip },
            { key: 'uptime', label: 'Uptime' },
        ],
    };

    const routers = window.NV_MODULES.tenant.routers;
    if (routers) {
        routers.title = 'MikroTik';
        routers.icon = 'fa-microchip';
        routers.columns = [
            { key: 'name', label: 'Nama' },
            { key: 'ip_address', label: 'IP' },
            { key: 'api_port', label: 'API' },
            { key: 'username', label: 'User API' },
            { key: 'model', label: 'Model' },
            { key: 'status', label: 'Status', fmt: chip },
            { key: 'cpu_load', label: 'CPU', fmt: (v) => (v || 0) + '%' },
            { key: 'last_seen', label: 'Last Seen', fmt: F.ago || ((v) => v || '-') },
        ];
        routers.fields = [
            { key: 'name', label: 'Nama MikroTik', type: 'text', required: true },
            { key: 'ip_address', label: 'IP Address', type: 'text', required: true, def: '192.168.55.2' },
            { key: 'api_port', label: 'API Port', type: 'number', def: 8728 },
            { key: 'username', label: 'Username API', type: 'text', def: 'admin' },
            { key: 'password_enc', label: 'Password API', type: 'password' },
            { key: 'snmp_community', label: 'SNMP Community', type: 'text', def: 'public' },
            { key: 'model', label: 'Model', type: 'text' },
            { key: 'location', label: 'Lokasi', type: 'text' },
            { key: 'latitude', label: 'Latitude', type: 'number' },
            { key: 'longitude', label: 'Longitude', type: 'number' },
            { key: 'status', label: 'Status', type: 'select', options: ['online', 'offline', 'unknown'], def: 'unknown' },
        ];
    }
})();
