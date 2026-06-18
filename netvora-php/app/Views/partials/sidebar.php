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
    ['MONITORING', [
        ['Dashboard', 'fa-gauge-high', '/dashboard'],
        ['Routers', 'fa-network-wired', '/dashboard/routers'],
        ['OLT', 'fa-server', '/dashboard/olt'],
        ['ONU', 'fa-hard-drive', '/dashboard/onu'],
        ['Pelanggan', 'fa-users', '/dashboard/pelanggan'],
        ['Traffic', 'fa-chart-line', '/dashboard/traffic'],
        ['Topologi', 'fa-diagram-project', '/dashboard/topologi'],
        ['Maps', 'fa-map-location-dot', '/dashboard/maps'],
        ['Alerts', 'fa-bell', '/dashboard/alerts'],
    ]],
    ['TICKETING', [
        ['Tiket', 'fa-ticket', '/dashboard/tiket'],
    ]],
    ['INVENTORY', [
        ['MikroTik', 'fa-microchip', '/dashboard/mikrotik'],
        ['ODP', 'fa-box-archive', '/dashboard/odp'],
    ]],
    ['REPORTS', [
        ['Laporan', 'fa-file-lines', '/dashboard/laporan'],
        ['Logs', 'fa-list', '/dashboard/logs'],
    ]],
    ['ADMIN', [
        ['Users', 'fa-user-gear', '/dashboard/users'],
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
            </a>
        <?php endforeach; ?>
    <?php endforeach; ?>

    <div style="margin-top:24px;border-top:1px solid var(--line);padding-top:16px" x-data="{ u: NV.user() }">
        <div class="flex items-center gap-3" style="padding:6px 8px">
            <div class="brand-logo" style="width:36px;height:36px;border-radius:10px"><i class="fa-solid fa-user"></i></div>
            <div style="min-width:0">
                <div style="font-weight:600;font-size:13px" x-text="u?.name || 'User'"></div>
                <div style="font-size:11px;color:var(--green)"><i class="fa-solid fa-circle" style="font-size:7px"></i> Online</div>
            </div>
            <i class="fa-solid fa-arrow-right-from-bracket" style="margin-left:auto;cursor:pointer;color:var(--muted)" @click="NV.logout()" title="Logout"></i>
        </div>
    </div>
</aside>
