/* =====================================================================
   NETVORA NOC — Module registry + generic CRUD engine (Alpine)
   Every sidebar menu maps to a config here so all menus are functional.
   ===================================================================== */
const F = {
    text: (v) => v ?? '-',
    money: (v) => NV.rupiah(v || 0),
    date: (v) => v ? new Date(v).toLocaleDateString('id-ID', { day: 'numeric', month: 'short', year: 'numeric' }) : '-',
    ago: (v) => NV.timeAgo(v),
    chip: (v) => `<span class="chip ${(''+v).toLowerCase()}">${v ?? '-'}</span>`,
    pkg: (v) => `<span class="badge-pkg pkg-${v||'basic'}">${(v||'basic')}</span>`,
};

const NV_MODULES = {
    // ===================== SUPER ADMIN =====================
    superadmin: {
        'paket-tenant': { type: 'crud', title: 'Paket Tenant', icon: 'fa-box', endpoint: '/admin/packages',
            columns: [
                { key: 'code', label: 'Kode' }, { key: 'name', label: 'Nama' },
                { key: 'price', label: 'Harga', fmt: F.money },
                { key: 'max_routers', label: 'Max Router' }, { key: 'max_customers', label: 'Max Pelanggan' },
            ],
            fields: [
                { key: 'code', label: 'Kode', type: 'select', options: ['basic','professional','enterprise'], required: true },
                { key: 'name', label: 'Nama Paket', type: 'text', required: true },
                { key: 'price', label: 'Harga', type: 'number' },
                { key: 'max_routers', label: 'Max Router (0=unlimited)', type: 'number' },
                { key: 'max_customers', label: 'Max Pelanggan (0=unlimited)', type: 'number' },
            ] },
        'semua-user': { type: 'crud', title: 'Semua User', icon: 'fa-users', endpoint: '/admin/users',
            columns: [
                { key: 'name', label: 'Nama' }, { key: 'email', label: 'Email' },
                { key: 'role_code', label: 'Role' }, { key: 'tenant_name', label: 'Tenant', fmt: (v)=>v||'— (Super Admin)' },
                { key: 'is_active', label: 'Aktif', fmt: (v)=> v ? F.chip('active') : F.chip('offline') },
            ],
            fields: [
                { key: 'name', label: 'Nama', type: 'text', required: true },
                { key: 'email', label: 'Email', type: 'email', required: true },
                { key: 'password', label: 'Password', type: 'password' },
                { key: 'role_code', label: 'Role', type: 'select', options: ['owner','admin','noc','teknisi','cs','finance','marketing','readonly'], required: true },
            ] },
        'role-permission': { type: 'crud', title: 'Role &amp; Permission', icon: 'fa-user-shield', endpoint: '/admin/roles',
            columns: [
                { key: 'code', label: 'Kode' }, { key: 'name', label: 'Nama' },
                { key: 'permissions', label: 'Permissions', fmt: (v)=> `<span class="mono" style="font-size:11px">${Array.isArray(v)?v.join(', '):v}</span>` },
            ],
            fields: [
                { key: 'code', label: 'Kode', type: 'text', required: true },
                { key: 'name', label: 'Nama Role', type: 'text', required: true },
                { key: 'permissions', label: 'Permissions (pisah koma, * = semua)', type: 'text' },
            ] },
        'audit-log': { type: 'crud', readonly: true, title: 'Audit Log', icon: 'fa-clipboard-list', endpoint: '/admin/audit_logs',
            columns: [
                { key: 'action', label: 'Aksi' }, { key: 'entity', label: 'Entity' },
                { key: 'tenant_name', label: 'Tenant', fmt: (v)=>v||'-' }, { key: 'ip_address', label: 'IP' },
                { key: 'created_at', label: 'Waktu', fmt: F.ago },
            ] },
        'subscription': { type: 'crud', title: 'Subscription', icon: 'fa-rotate', endpoint: '/admin/subscriptions',
            columns: [
                { key: 'package_name', label: 'Paket' }, { key: 'amount', label: 'Nominal', fmt: F.money },
                { key: 'cycle', label: 'Siklus' }, { key: 'next_due', label: 'Jatuh Tempo', fmt: F.date },
                { key: 'status', label: 'Status', fmt: F.chip },
            ],
            fields: [
                { key: 'package_name', label: 'Paket', type: 'text' },
                { key: 'amount', label: 'Nominal', type: 'number' },
                { key: 'cycle', label: 'Siklus', type: 'select', options: ['monthly','yearly'] },
                { key: 'next_due', label: 'Jatuh Tempo', type: 'date' },
                { key: 'status', label: 'Status', type: 'select', options: ['active','paused','cancelled'] },
            ] },
        'invoice': { type: 'crud', title: 'Invoice', icon: 'fa-file-invoice', endpoint: '/admin/invoices',
            columns: [
                { key: 'number', label: 'No Invoice' }, { key: 'tenant_name', label: 'Tenant', fmt:(v)=>v||'-' },
                { key: 'total', label: 'Total', fmt: F.money }, { key: 'due_date', label: 'Jatuh Tempo', fmt: F.date },
                { key: 'status', label: 'Status', fmt: F.chip },
            ],
            fields: [
                { key: 'number', label: 'No Invoice', type: 'text', required: true },
                { key: 'amount', label: 'Amount', type: 'number' },
                { key: 'total', label: 'Total', type: 'number' },
                { key: 'due_date', label: 'Jatuh Tempo', type: 'date' },
                { key: 'status', label: 'Status', type: 'select', options: ['unpaid','paid','overdue','void'] },
            ] },
        'payment': { type: 'crud', title: 'Payment', icon: 'fa-credit-card', endpoint: '/admin/payments',
            columns: [
                { key: 'method', label: 'Metode' }, { key: 'reference', label: 'Referensi' },
                { key: 'amount', label: 'Nominal', fmt: F.money }, { key: 'status', label: 'Status', fmt: F.chip },
                { key: 'created_at', label: 'Waktu', fmt: F.ago },
            ],
            fields: [
                { key: 'amount', label: 'Nominal', type: 'number', required: true },
                { key: 'method', label: 'Metode', type: 'select', options: ['midtrans','xendit','tripay','manual','bca','bni','mandiri'] },
                { key: 'reference', label: 'Referensi', type: 'text' },
                { key: 'status', label: 'Status', type: 'select', options: ['pending','success','failed'] },
            ] },
        'monitoring': { type: 'sa-monitoring', title: 'Monitoring', icon: 'fa-wave-square' },
        'backup': { type: 'backup', title: 'Backup', icon: 'fa-database' },
        'smtp': { type: 'integration', title: 'SMTP', icon: 'fa-envelope', note: 'SMTP default platform diatur via file <code>.env</code> (SMTP_HOST, SMTP_USER...). Tiap tenant dapat mengatur SMTP sendiri di menu Settings tenant.' },
        'whatsapp-gateway': { type: 'integration', title: 'WhatsApp Gateway', icon: 'fa-whatsapp', note: 'Gateway default diatur via <code>.env</code> (WA_GATEWAY_URL, WA_GATEWAY_TOKEN). Tiap tenant mengatur WA gateway sendiri di Settings.' },
        'system-settings': { type: 'integration', title: 'System Settings', icon: 'fa-gear', note: 'Konfigurasi sistem global (JWT, rate limit, AI) diatur via file <code>.env</code> pada server.' },
    },

    // ===================== TENANT NOC =====================
    tenant: {
        'routers': { type: 'crud', title: 'Routers', icon: 'fa-network-wired', endpoint: '/routers',
            columns: [
                { key: 'name', label: 'Nama' }, { key: 'ip_address', label: 'IP' }, { key: 'model', label: 'Model' },
                { key: 'location', label: 'Lokasi' }, { key: 'status', label: 'Status', fmt: F.chip },
                { key: 'cpu_load', label: 'CPU', fmt:(v)=>(v||0)+'%' }, { key: 'last_seen', label: 'Last Seen', fmt: F.ago },
            ],
            fields: [
                { key: 'name', label: 'Nama Router', type: 'text', required: true },
                { key: 'ip_address', label: 'IP Address', type: 'text', required: true },
                { key: 'api_port', label: 'API Port', type: 'number', def: 8728 },
                { key: 'username', label: 'Username', type: 'text' },
                { key: 'password_enc', label: 'Password', type: 'password' },
                { key: 'snmp_community', label: 'SNMP Community', type: 'text', def: 'public' },
                { key: 'model', label: 'Model', type: 'text' },
                { key: 'location', label: 'Lokasi', type: 'text' },
                { key: 'latitude', label: 'Latitude', type: 'number' },
                { key: 'longitude', label: 'Longitude', type: 'number' },
                { key: 'status', label: 'Status', type: 'select', options: ['online','offline','unknown'] },
            ] },
        'mikrotik': { alias: 'routers' },
        'olt': { type: 'crud', title: 'OLT', icon: 'fa-server', endpoint: '/olts',
            columns: [
                { key: 'name', label: 'Nama' }, { key: 'vendor', label: 'Vendor' }, { key: 'ip_address', label: 'IP' },
                { key: 'location', label: 'Lokasi' }, { key: 'pon_ports', label: 'PON' },
                { key: 'status', label: 'Status', fmt: F.chip }, { key: 'last_seen', label: 'Last Seen', fmt: F.ago },
            ],
            fields: [
                { key: 'name', label: 'Nama OLT', type: 'text', required: true },
                { key: 'vendor', label: 'Vendor', type: 'select', options: ['huawei','zte','vsol','fiberhome','bdcom','cdata','raisecom','nokia'], required: true },
                { key: 'ip_address', label: 'IP Address', type: 'text', required: true },
                { key: 'snmp_community', label: 'SNMP Community', type: 'text', def: 'public' },
                { key: 'pon_ports', label: 'Jumlah PON', type: 'number' },
                { key: 'location', label: 'Lokasi', type: 'text' },
                { key: 'latitude', label: 'Latitude', type: 'number' },
                { key: 'longitude', label: 'Longitude', type: 'number' },
                { key: 'status', label: 'Status', type: 'select', options: ['online','offline','unknown'] },
            ] },
        'onu': { type: 'crud', title: 'ONU', icon: 'fa-hard-drive', endpoint: '/onus',
            columns: [
                { key: 'serial', label: 'Serial' }, { key: 'name', label: 'Nama' }, { key: 'pon_port', label: 'PON' },
                { key: 'rx_power', label: 'RX (dBm)' }, { key: 'distance_m', label: 'Jarak (m)' },
                { key: 'status', label: 'Status', fmt: F.chip },
            ],
            fields: [
                { key: 'serial', label: 'Serial Number', type: 'text', required: true },
                { key: 'name', label: 'Nama Pelanggan', type: 'text' },
                { key: 'mac', label: 'MAC', type: 'text' },
                { key: 'pon_port', label: 'PON Port', type: 'text' },
                { key: 'rx_power', label: 'RX Power (dBm)', type: 'number' },
                { key: 'distance_m', label: 'Jarak (m)', type: 'number' },
                { key: 'firmware', label: 'Firmware', type: 'text' },
                { key: 'status', label: 'Status', type: 'select', options: ['online','offline','los','unknown'] },
            ] },
        'odp': { type: 'crud', title: 'ODP', icon: 'fa-box-archive', endpoint: '/odps',
            columns: [
                { key: 'name', label: 'Nama' }, { key: 'pon', label: 'PON' }, { key: 'capacity', label: 'Kapasitas' },
                { key: 'used_port', label: 'Terpakai' }, { key: 'status', label: 'Status', fmt: F.chip },
            ],
            fields: [
                { key: 'name', label: 'Nama ODP', type: 'text', required: true },
                { key: 'pon', label: 'PON', type: 'text' },
                { key: 'capacity', label: 'Kapasitas Port', type: 'number', def: 8 },
                { key: 'used_port', label: 'Port Terpakai', type: 'number' },
                { key: 'splitter', label: 'Splitter', type: 'text' },
                { key: 'latitude', label: 'Latitude', type: 'number' },
                { key: 'longitude', label: 'Longitude', type: 'number' },
                { key: 'status', label: 'Status', type: 'select', options: ['active','full','maintenance','down'] },
            ] },
        'pelanggan': { type: 'crud', title: 'Pelanggan', icon: 'fa-users', endpoint: '/customers',
            columns: [
                { key: 'name', label: 'Nama' }, { key: 'phone', label: 'No HP' }, { key: 'conn_type', label: 'Tipe' },
                { key: 'package_name', label: 'Paket' }, { key: 'monthly_fee', label: 'Tagihan', fmt: F.money },
                { key: 'status', label: 'Status', fmt: F.chip },
            ],
            fields: [
                { key: 'name', label: 'Nama Pelanggan', type: 'text', required: true },
                { key: 'phone', label: 'No HP', type: 'text' },
                { key: 'email', label: 'Email', type: 'email' },
                { key: 'address', label: 'Alamat', type: 'text' },
                { key: 'conn_type', label: 'Tipe Koneksi', type: 'select', options: ['pppoe','onu','hybrid'] },
                { key: 'pppoe_user', label: 'PPPoE User', type: 'text' },
                { key: 'package_name', label: 'Paket', type: 'text' },
                { key: 'monthly_fee', label: 'Tagihan Bulanan', type: 'number' },
                { key: 'latitude', label: 'Latitude', type: 'number' },
                { key: 'longitude', label: 'Longitude', type: 'number' },
                { key: 'status', label: 'Status', type: 'select', options: ['active','suspend','expired','isolir'] },
            ] },
        'alerts': { type: 'crud', title: 'Alerts', icon: 'fa-bell', endpoint: '/alerts',
            columns: [
                { key: 'severity', label: 'Severity', fmt: F.chip }, { key: 'type', label: 'Tipe' },
                { key: 'source', label: 'Sumber' }, { key: 'message', label: 'Pesan' },
                { key: 'is_resolved', label: 'Status', fmt:(v)=> v ? F.chip('resolved') : F.chip('warning') },
                { key: 'created_at', label: 'Waktu', fmt: F.ago },
            ],
            fields: [
                { key: 'severity', label: 'Severity', type: 'select', options: ['info','warning','critical'], required: true },
                { key: 'type', label: 'Tipe', type: 'text', required: true },
                { key: 'source', label: 'Sumber', type: 'text' },
                { key: 'message', label: 'Pesan', type: 'text', required: true },
            ] },
        'tiket': { type: 'crud', title: 'Tiket', icon: 'fa-ticket', endpoint: '/tickets',
            columns: [
                { key: 'subject', label: 'Subjek' }, { key: 'category', label: 'Kategori' },
                { key: 'priority', label: 'Prioritas', fmt: F.chip }, { key: 'status', label: 'Status', fmt: F.chip },
                { key: 'created_at', label: 'Dibuat', fmt: F.ago },
            ],
            fields: [
                { key: 'subject', label: 'Subjek', type: 'text', required: true },
                { key: 'category', label: 'Kategori', type: 'text' },
                { key: 'priority', label: 'Prioritas', type: 'select', options: ['low','medium','high','critical'] },
                { key: 'status', label: 'Status', type: 'select', options: ['open','in_progress','resolved','closed'] },
                { key: 'description', label: 'Deskripsi', type: 'textarea' },
            ] },
        'users': { type: 'crud', title: 'Users', icon: 'fa-user-gear', endpoint: '/users',
            columns: [
                { key: 'name', label: 'Nama' }, { key: 'email', label: 'Email' }, { key: 'role_code', label: 'Role' },
                { key: 'is_active', label: 'Aktif', fmt:(v)=> v ? F.chip('active') : F.chip('offline') },
            ],
            fields: [
                { key: 'name', label: 'Nama', type: 'text', required: true },
                { key: 'email', label: 'Email', type: 'email', required: true },
                { key: 'password', label: 'Password', type: 'password' },
                { key: 'role_code', label: 'Role', type: 'select', options: ['admin','noc','teknisi','cs','finance','marketing','readonly'], required: true },
            ] },
        'logs': { type: 'crud', readonly: true, title: 'Logs', icon: 'fa-list', endpoint: '/audit_logs',
            columns: [
                { key: 'action', label: 'Aksi' }, { key: 'entity', label: 'Entity' },
                { key: 'ip_address', label: 'IP' }, { key: 'created_at', label: 'Waktu', fmt: F.ago },
            ] },
        'traffic': { type: 'charts', title: 'Traffic &amp; Loss', icon: 'fa-chart-line' },
        'maps': { type: 'maps', title: 'Maps', icon: 'fa-map-location-dot' },
        'invoice': { type: 'crud', title: 'Invoice', icon: 'fa-file-invoice', endpoint: '/invoices',
            topActions: [{ label: 'Generate Jatuh Tempo', icon: 'fa-bolt', call: 'generateInvoices' }],
            rowActions: [
                { label: 'Bayar Manual', icon: 'fa-money-bill', call: 'payInvoice' },
                { label: 'Link Pembayaran', icon: 'fa-link', call: 'paymentLink' },
            ],
            columns: [
                { key: 'number', label: 'No Invoice' }, { key: 'total', label: 'Total', fmt: F.money },
                { key: 'due_date', label: 'Jatuh Tempo', fmt: F.date }, { key: 'status', label: 'Status', fmt: F.chip },
                { key: 'created_at', label: 'Dibuat', fmt: F.ago },
            ],
            fields: [
                { key: 'amount', label: 'Nominal', type: 'number', required: true },
                { key: 'tax', label: 'Pajak', type: 'number' },
                { key: 'discount', label: 'Diskon', type: 'number' },
                { key: 'due_date', label: 'Jatuh Tempo', type: 'date' },
                { key: 'status', label: 'Status', type: 'select', options: ['unpaid','paid','overdue','void'] },
            ] },
        'payment': { type: 'crud', title: 'Payment', icon: 'fa-credit-card', endpoint: '/payments', readonly: true,
            columns: [
                { key: 'method', label: 'Metode' }, { key: 'reference', label: 'Referensi' },
                { key: 'amount', label: 'Nominal', fmt: F.money }, { key: 'status', label: 'Status', fmt: F.chip },
                { key: 'created_at', label: 'Waktu', fmt: F.ago },
            ] },
        'subscription': { type: 'crud', title: 'Subscription', icon: 'fa-rotate', endpoint: '/subscriptions',
            columns: [
                { key: 'package_name', label: 'Paket' }, { key: 'amount', label: 'Nominal', fmt: F.money },
                { key: 'cycle', label: 'Siklus' }, { key: 'next_due', label: 'Jatuh Tempo', fmt: F.date },
                { key: 'status', label: 'Status', fmt: F.chip },
            ],
            fields: [
                { key: 'customer_id', label: 'Customer ID', type: 'text' },
                { key: 'package_name', label: 'Paket', type: 'text' },
                { key: 'amount', label: 'Nominal', type: 'number' },
                { key: 'cycle', label: 'Siklus', type: 'select', options: ['monthly','yearly'] },
                { key: 'next_due', label: 'Jatuh Tempo', type: 'date' },
                { key: 'status', label: 'Status', type: 'select', options: ['active','paused','cancelled'] },
            ] },
        'acs': { type: 'acs', title: 'ACS (TR-069)', icon: 'fa-satellite-dish' },
        'topologi': { type: 'topology', title: 'Topologi Network', icon: 'fa-diagram-project' },
        'ai-analytics': { type: 'ai', title: 'AI Analytics', icon: 'fa-brain' },
        'laporan': { type: 'reports', title: 'Laporan', icon: 'fa-file-lines' },
        'settings': { type: 'tenant-settings', title: 'Settings', icon: 'fa-gear' },
    },
};

function moduleConfig(scope, module) {
    let cfg = (NV_MODULES[scope] || {})[module];
    if (cfg && cfg.alias) cfg = NV_MODULES[scope][cfg.alias];
    return cfg || null;
}
window.NV_MODULES = NV_MODULES; window.moduleConfig = moduleConfig; window.F = F;
