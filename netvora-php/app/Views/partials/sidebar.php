<?php
/** @var string $scope */
$superadmin = ($scope ?? '') === 'superadmin';
$current = rtrim(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/', '/') ?: '/';

$menu = $superadmin ? [
    ['DASHBOARD', [
        ['Dashboard', 'fa-gauge-high', '/superadmin'],
    ]],
    ['TENANT MANAGEMENT', [
        ['Semua Tenant', 'fa-building', '/superadmin'],
        ['Paket Tenant', 'fa-box', '/superadmin/paket-tenant'],
    ]],
    ['USER MANAGEMENT', [
        ['Semua User', 'fa-users', '/superadmin/semua-user'],
        ['Role Permission', 'fa-user-shield', '/superadmin/role-permission'],
    ]],
    ['SYSTEM', [
        ['Monitoring', 'fa-wave-square', '/superadmin/monitoring'],
        ['Audit Log', 'fa-clipboard-list', '/superadmin/audit-log'],
        ['Backup', 'fa-database', '/superadmin/backup'],
        ['SMTP', 'fa-envelope', '/superadmin/smtp'],
        ['WhatsApp Gateway', 'fa-whatsapp', '/superadmin/whatsapp-gateway'],
        ['System Settings', 'fa-gear', '/superadmin/system-settings'],
    ]],
    ['BILLING', [
        ['Subscription', 'fa-rotate', '/superadmin/subscription'],
        ['Invoice', 'fa-file-invoice', '/superadmin/invoice'],
        ['Payment', 'fa-credit-card', '/superadmin/payment'],
    ]],
] : [
    ['DASHBOARD', [
        ['Dashboard', 'fa-house-chimney-window', '/dashboard'],
    ]],
    ['MONITORING', [
        ['Overview', 'fa-circle-chevron-right', '/dashboard/overview'],
        ['Routers', 'fa-network-wired', '/dashboard/routers'],
        ['OLT', 'fa-server', '/dashboard/olt'],
        ['ONU', 'fa-hard-drive', '/dashboard/onu'],
        ['Pelanggan', 'fa-users', '/dashboard/pelanggan'],
        ['Traffic', 'fa-chart-line', '/dashboard/traffic'],
        ['Topologi', 'fa-diagram-project', '/dashboard/topologi', 'New'],
        ['Maps', 'fa-map-location-dot', '/dashboard/maps'],
        ['Alerts', 'fa-bell', '/dashboard/alerts'],
    ]],
    ['TICKETING', [
        ['Tiket', 'fa-ticket', '/dashboard/tiket'],
    ]],
    ['INVENTORY', [
        ['MikroTik', 'fa-microchip', '/dashboard/mikrotik'],
        ['OLT', 'fa-server', '/dashboard/olt'],
        ['ONU', 'fa-hard-drive', '/dashboard/onu'],
        ['ODP', 'fa-box-archive', '/dashboard/odp'],
        ['PPPoE Users', 'fa-users-line', '/dashboard/pppoe-users'],
        ['ACS (TR-069)', 'fa-satellite-dish', '/dashboard/acs'],
    ]],
    ['BILLING', [
        ['Paket Internet', 'fa-box-open', '/dashboard/paket-internet'],
        ['Invoice', 'fa-file-invoice', '/dashboard/invoice'],
        ['Pembayaran', 'fa-credit-card', '/dashboard/pembayaran'],
        ['Pelanggan Belum Bayar', 'fa-user-clock', '/dashboard/pelanggan-belum-bayar'],
        ['Isolir / Buka Isolir', 'fa-user-lock', '/dashboard/isolir-buka-isolir'],
    ]],
    ['REPORTS', [
        ['Laporan', 'fa-file-lines', '/dashboard/laporan'],
        ['Logs', 'fa-list', '/dashboard/logs'],
    ]],
    ['ADMIN', [
        ['Users', 'fa-user-gear', '/dashboard/users'],
        ['Roles', 'fa-user-shield', '/dashboard/roles'],
        ['AI Analytics', 'fa-brain', '/dashboard/ai-analytics'],
        ['Settings', 'fa-gear', '/dashboard/settings'],
    ]],
];
?>
<aside class="sidebar" :class="{ open: sidebarOpen }">
    <div class="brand">
        <div class="brand-logo"><i class="fa-solid fa-circle-nodes"></i></div>
        <div class="brand-name">NETVORA<span> NOC</span></div>
    </div>

    <?php foreach ($menu as [$section, $items]): ?>
        <div class="nav-section"><?= $section ?></div>
        <?php foreach ($items as $it): $active = ($it[2] === $current); ?>
            <a class="nav-item <?= $active ? 'active' : '' ?>" href="<?= $it[2] ?>">
                <i class="fa-solid <?= $it[1] ?>"></i><span><?= $it[0] ?></span>
                <?php if (!empty($it[3])): ?><span class="nav-badge"><?= $it[3] ?></span><?php endif; ?>
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <div class="sidebar-user" x-data="{ u: NV.user() }">
        <div class="sidebar-avatar"><i class="fa-solid fa-user"></i></div>
        <div style="min-width:0">
            <div class="sidebar-user-name" x-text="u?.name || 'Super Admin'"></div>
            <div class="sidebar-user-mail" x-text="u?.email || 'superadmin@netvora.com'"></div>
            <div class="sidebar-online"><i class="fa-solid fa-circle"></i> Online</div>
        </div>
        <i class="fa-solid fa-arrow-right-from-bracket sidebar-logout" @click="NV.logout()" title="Logout"></i>
    </div>
</aside>