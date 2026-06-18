<?php
/** @var string $scope */
$superadmin = ($scope ?? '') === 'superadmin';

$menu = $superadmin ? [
    ['DASHBOARD', [
        ['Dashboard', 'fa-gauge-high', '/superadmin', true],
    ]],
    ['TENANT MANAGEMENT', [
        ['Semua Tenant', 'fa-building', '/superadmin#tenants'],
        ['Tambah Tenant', 'fa-circle-plus', '/superadmin#add'],
        ['Paket Tenant', 'fa-box', '/superadmin#packages'],
        ['Suspend Tenant', 'fa-pause', '/superadmin#suspend'],
    ]],
    ['USER MANAGEMENT', [
        ['Semua User', 'fa-users', '/superadmin#users'],
        ['Role Permission', 'fa-user-shield', '/superadmin#roles'],
    ]],
    ['SYSTEM', [
        ['Monitoring', 'fa-wave-square', '/superadmin#monitoring'],
        ['Audit Log', 'fa-clipboard-list', '/superadmin#audit'],
        ['Backup', 'fa-database', '/superadmin#backup'],
        ['SMTP', 'fa-envelope', '/superadmin#smtp'],
        ['WhatsApp Gateway', 'fa-whatsapp', '/superadmin#wa'],
        ['System Settings', 'fa-gear', '/superadmin#settings'],
    ]],
    ['BILLING', [
        ['Subscription', 'fa-rotate', '/superadmin#subs'],
        ['Invoice', 'fa-file-invoice', '/superadmin#invoice'],
        ['Payment', 'fa-credit-card', '/superadmin#payment'],
    ]],
] : [
    ['MONITORING', [
        ['Dashboard', 'fa-gauge-high', '/dashboard', true],
        ['Overview', 'fa-eye', '/dashboard#overview'],
        ['Routers', 'fa-network-wired', '/dashboard#routers'],
        ['OLT', 'fa-server', '/dashboard#olt'],
        ['ONU', 'fa-hard-drive', '/dashboard#onu'],
        ['Pelanggan', 'fa-users', '/dashboard#customers'],
        ['Traffic', 'fa-chart-line', '/dashboard#traffic'],
        ['Topologi', 'fa-diagram-project', '/dashboard#topology'],
        ['Maps', 'fa-map-location-dot', '/dashboard#maps'],
        ['Alerts', 'fa-bell', '/dashboard#alerts'],
    ]],
    ['TICKETING', [
        ['Tiket', 'fa-ticket', '/dashboard#tickets'],
    ]],
    ['INVENTORY', [
        ['MikroTik', 'fa-microchip', '/dashboard#mikrotik'],
        ['ODP', 'fa-box-archive', '/dashboard#odp'],
    ]],
    ['REPORTS', [
        ['Laporan', 'fa-file-lines', '/dashboard#reports'],
        ['Logs', 'fa-list', '/dashboard#logs'],
    ]],
    ['ADMIN', [
        ['Users', 'fa-user-gear', '/dashboard#users'],
        ['AI Analytics', 'fa-brain', '/dashboard#ai'],
        ['Settings', 'fa-gear', '/dashboard#settings'],
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
        <?php foreach ($items as $it): ?>
            <a class="nav-item <?= ($it[3] ?? false) ? 'active' : '' ?>" href="<?= $it[2] ?>">
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
