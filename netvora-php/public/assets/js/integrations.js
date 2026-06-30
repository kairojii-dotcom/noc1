/* Extra live integrations layered on top of the generic module registry. */
(() => {
    if (!window.NV_MODULES || !window.NV_MODULES.tenant) return;

    const F = window.F || {};
    const chip = F.chip || ((v) => `<span class="chip ${('' + v).toLowerCase()}">${v ?? '-'}</span>`);
    const money = F.money || ((v) => NV.rupiah(v || 0));
    const date = F.date || ((v) => v || '-');
    const ago = F.ago || ((v) => v || '-');

    window.NV_MODULES.tenant.overview = {
        type: 'reports',
        title: 'Overview Monitoring',
        icon: 'fa-circle-chevron-right',
    };

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

    window.NV_MODULES.tenant['paket-internet'] = {
        type: 'crud',
        title: 'Paket Internet',
        icon: 'fa-box-open',
        endpoint: '/subscriptions',
        columns: [
            { key: 'package_name', label: 'Paket' },
            { key: 'customer_name', label: 'Pelanggan', fmt: (v) => v || '-' },
            { key: 'amount', label: 'Harga', fmt: money },
            { key: 'cycle', label: 'Siklus' },
            { key: 'next_due', label: 'Jatuh Tempo', fmt: date },
            { key: 'status', label: 'Status', fmt: chip },
        ],
        fields: [
            { key: 'customer_id', label: 'Customer ID', type: 'text' },
            { key: 'package_name', label: 'Nama Paket', type: 'text', required: true },
            { key: 'amount', label: 'Harga', type: 'number' },
            { key: 'cycle', label: 'Siklus', type: 'select', options: ['monthly', 'yearly'], def: 'monthly' },
            { key: 'next_due', label: 'Jatuh Tempo', type: 'date' },
            { key: 'status', label: 'Status', type: 'select', options: ['active', 'paused', 'cancelled'], def: 'active' },
        ],
    };

    window.NV_MODULES.tenant.pembayaran = {
        type: 'crud',
        readonly: true,
        title: 'Pembayaran',
        icon: 'fa-credit-card',
        endpoint: '/payments',
        columns: [
            { key: 'method', label: 'Metode' },
            { key: 'reference', label: 'Referensi' },
            { key: 'amount', label: 'Nominal', fmt: money },
            { key: 'status', label: 'Status', fmt: chip },
            { key: 'created_at', label: 'Waktu', fmt: ago },
        ],
    };

    window.NV_MODULES.tenant['pelanggan-belum-bayar'] = {
        type: 'crud',
        readonly: true,
        title: 'Pelanggan Belum Bayar',
        icon: 'fa-user-clock',
        endpoint: '/invoices',
        columns: [
            { key: 'number', label: 'No Invoice' },
            { key: 'customer_name', label: 'Pelanggan', fmt: (v) => v || '-' },
            { key: 'total', label: 'Total', fmt: money },
            { key: 'due_date', label: 'Jatuh Tempo', fmt: date },
            { key: 'status', label: 'Status', fmt: chip },
            { key: 'created_at', label: 'Dibuat', fmt: ago },
        ],
    };

    window.NV_MODULES.tenant['isolir-buka-isolir'] = {
        type: 'crud',
        title: 'Isolir / Buka Isolir',
        icon: 'fa-user-lock',
        endpoint: '/customers',
        columns: [
            { key: 'name', label: 'Nama' },
            { key: 'phone', label: 'No HP' },
            { key: 'pppoe_user', label: 'PPPoE' },
            { key: 'package_name', label: 'Paket' },
            { key: 'monthly_fee', label: 'Tagihan', fmt: money },
            { key: 'status', label: 'Status', fmt: chip },
        ],
        fields: [
            { key: 'name', label: 'Nama Pelanggan', type: 'text', required: true },
            { key: 'phone', label: 'No HP', type: 'text' },
            { key: 'pppoe_user', label: 'PPPoE User', type: 'text' },
            { key: 'package_name', label: 'Paket', type: 'text' },
            { key: 'monthly_fee', label: 'Tagihan Bulanan', type: 'number' },
            { key: 'status', label: 'Status', type: 'select', options: ['active', 'suspend', 'expired', 'isolir'], def: 'active' },
        ],
    };

    window.NV_MODULES.tenant.roles = {
        type: 'crud',
        title: 'Roles',
        icon: 'fa-user-shield',
        endpoint: '/users',
        columns: [
            { key: 'name', label: 'Nama' },
            { key: 'email', label: 'Email' },
            { key: 'role_code', label: 'Role' },
            { key: 'is_active', label: 'Aktif', fmt: (v) => v ? chip('active') : chip('offline') },
        ],
        fields: [
            { key: 'name', label: 'Nama', type: 'text', required: true },
            { key: 'email', label: 'Email', type: 'email', required: true },
            { key: 'password', label: 'Password', type: 'password' },
            { key: 'role_code', label: 'Role', type: 'select', options: ['admin', 'noc', 'teknisi', 'cs', 'finance', 'marketing', 'readonly'], required: true },
            { key: 'is_active', label: 'Aktif', type: 'select', options: [1, 0], def: 1 },
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
            { key: 'last_seen', label: 'Last Seen', fmt: ago },
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