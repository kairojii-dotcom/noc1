<?php /** @var string $content @var string $title @var string $scope */ ?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($title ?? 'NETVORA NOC') ?> — NETVORA NOC</title>

    <!-- Bootstrap 5 (utilities/grid only) -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- FontAwesome icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <!-- Leaflet -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css">
    <!-- Vis Network -->
    <link rel="stylesheet" href="https://unpkg.com/vis-network@9.1.9/styles/vis-network.min.css">
    <!-- DataTables -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.13.8/css/dataTables.dataTables.min.css">
    <!-- App theme -->
    <link rel="stylesheet" href="/assets/css/app.css">
    <?php if (($scope ?? 'tenant') !== 'superadmin'): ?>
        <link rel="stylesheet" href="/assets/css/noc-dashboard.css">
    <?php endif; ?>
</head>
<body>
<div class="app-shell" x-data="{ sidebarOpen: false }">
    <?= \App\Core\View::partial('partials/sidebar', ['scope' => $scope ?? 'tenant']) ?>
    <main class="main">
        <?= $content ?>
    </main>
</div>

<!-- AlpineJS -->
<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<!-- ApexCharts -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
<!-- Leaflet -->
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<!-- Vis Network -->
<script src="https://unpkg.com/vis-network@9.1.9/dist/vis-network.min.js"></script>
<!-- jQuery + DataTables -->
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.datatables.net/1.13.8/js/dataTables.min.js"></script>
<!-- App -->
<script src="/assets/js/app.js"></script>
<script src="/assets/js/modules.js"></script>
<script src="/assets/js/integrations.js"></script>
<script src="/assets/js/engine.js"></script>
<?= $pageScript ?? '' ?>
</body>
</html>