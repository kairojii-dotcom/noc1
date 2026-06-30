<?php /** Tenant NOC Dashboard */ ?>
<div x-data="tenantDash()" x-init="init()" class="noc-dashboard">
    <div class="topbar noc-topbar">
        <div class="topbar-left">
            <button class="icon-btn sidebar-toggle" @click="sidebarOpen = !sidebarOpen" title="Menu"><i class="fa-solid fa-bars"></i></button>
            <div>
                <h1>Dashboard Monitoring</h1>
                <div class="sub">Overview status jaringan secara real-time</div>
            </div>
        </div>
        <div class="topbar-actions">
            <button class="pill select-pill" type="button"><span>Semua Tenant</span><i class="fa-solid fa-chevron-down"></i></button>
            <div class="pill"><i class="fa-regular fa-calendar"></i> <span x-text="clock"></span></div>
            <button class="icon-btn" type="button" title="Alerts"><i class="fa-regular fa-bell"></i><span class="badge-dot" x-show="alerts.length" x-text="alerts.length"></span></button>
            <button class="icon-btn" type="button" title="Message"><i class="fa-regular fa-envelope"></i></button>
            <button class="icon-btn" type="button" title="Theme" @click="toggleTheme()"><i class="fa-solid" :class="theme === 'light' ? 'fa-moon' : 'fa-sun'"></i></button>
            <a href="/dashboard/settings" class="icon-btn" title="Settings"><i class="fa-solid fa-gear"></i></a>
            <div class="top-avatar"><i class="fa-solid fa-user"></i></div>
        </div>
    </div>

    <div class="stat-grid noc-stat-grid">
        <template x-for="card in cards" :key="card.label">
            <div class="stat-card glass glass-hover reveal">
                <div class="stat-card-icon" :class="card.ic"><i class="fa-solid" :class="card.icon"></i></div>
                <div class="stat-card-body">
                    <div class="stat-label" x-text="card.label"></div>
                    <div class="stat-value" x-text="card.value"></div>
                    <div class="stat-meta"><span class="up" x-text="card.a"></span><span class="down" x-text="card.b"></span></div>
                </div>
            </div>
        </template>
    </div>

    <div class="noc-row noc-row-primary">
        <div class="glass panel reveal">
            <div class="panel-head"><span class="panel-title">Status Router</span></div>
            <div id="chartRouter" class="chart-box"></div>
        </div>
        <div class="glass panel reveal map-panel">
            <div class="panel-head map-head"><span class="panel-title">Peta Network</span></div>
            <div id="netMap" class="network-map"></div>
            <div class="map-empty" x-show="mapEmpty">Belum ada koordinat perangkat.</div>
        </div>
        <div class="glass panel reveal alert-panel">
            <div class="panel-head"><span class="panel-title">Alert Terbaru</span><a class="panel-link" href="/dashboard/alerts">Lihat Semua Alert</a></div>
            <template x-for="item in alerts" :key="item.id">
                <div class="alert-row">
                    <div class="alert-ic" :class="alertClass(item.severity)"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div class="alert-copy"><div class="alert-title" x-text="item.source || item.type || 'Alert'"></div><div class="alert-desc" x-text="item.message || '-'"></div></div>
                    <div class="alert-time" x-text="NV.timeAgo(item.created_at)"></div>
                </div>
            </template>
            <template x-if="alerts.length === 0"><div class="empty-line">Tidak ada alert aktif.</div></template>
        </div>
    </div>

    <div class="noc-row noc-row-mid">
        <div class="glass panel reveal"><div class="panel-head"><span class="panel-title">Traffic (24 Jam)</span></div><div id="chartTraffic" class="chart-box chart-wide"></div><div class="empty-line chart-empty" x-show="trafficEmpty">Belum ada data traffic 24 jam.</div></div>
        <div class="glass panel reveal"><div class="panel-head"><span class="panel-title">Loss Network (24 Jam)</span></div><div id="chartLoss" class="chart-box chart-wide"></div><div class="empty-line chart-empty" x-show="lossEmpty">Belum ada data loss 24 jam.</div></div>
        <div class="glass panel reveal"><div class="panel-head"><span class="panel-title">Status OLT</span></div><div id="chartOlt" class="chart-box"></div></div>
    </div>

    <div class="noc-row noc-row-bottom">
        <div class="glass panel reveal topo-panel">
            <div class="panel-head"><span class="panel-title">Topologi Network</span><a class="panel-link" href="/dashboard/topologi">Lihat Full Topologi</a></div>
            <div id="topo" class="topology-canvas"></div>
            <div class="topology-empty" x-show="topologyEmpty">Belum ada data topologi.</div>
            <div class="topology-legend">
                <span><i class="dot core"></i> Core</span><span><i class="dot dist"></i> Distribution</span><span><i class="dot olt"></i> OLT</span><span><i class="dot onu-on"></i> ONU Online</span><span><i class="dot onu-off"></i> ONU Offline</span>
            </div>
        </div>
        <div class="glass panel reveal">
            <div class="panel-head"><span class="panel-title">Status Perangkat Kritis</span><a class="panel-link" href="/dashboard/routers">Lihat Semua Perangkat</a></div>
            <div class="table-wrap">
                <table class="nv">
                    <thead><tr><th>Perangkat</th><th>Tipe</th><th>Lokasi</th><th>Status</th><th>CPU</th><th>Memory</th><th>Uptime</th><th>Last Seen</th></tr></thead>
                    <tbody>
                        <template x-for="device in devices" :key="device.type + '-' + device.id">
                            <tr>
                                <td class="strong" x-text="device.name || '-'"></td>
                                <td class="muted-cell" x-text="deviceType(device.type)"></td>
                                <td x-text="device.location || '-'"></td>
                                <td><span class="chip" :class="device.status" x-text="device.status || 'unknown'"></span></td>
                                <td><span class="meter"><i :style="meterStyle(device.cpu)"></i></span><span class="mono" x-text="percent(device.cpu)"></span></td>
                                <td><span class="meter amber"><i :style="meterStyle(device.memory)"></i></span><span class="mono" x-text="percent(device.memory)"></span></td>
                                <td x-text="formatUptime(device.uptime_sec)"></td>
                                <td class="muted-cell" x-text="NV.timeAgo(device.last_seen)"></td>
                            </tr>
                        </template>
                        <template x-if="devices.length === 0"><tr><td colspan="8" class="empty-table">Belum ada perangkat kritis.</td></tr></template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php $pageScript = <<<'JS'
<script>
function tenantDash() {
    return {
        summary: {}, cards: [], alerts: [], devices: [], routers: [], olts: [], mapItems: [], topology: { nodes: [], edges: [] },
        clock: '', theme: 'dark', mapEmpty: true, topologyEmpty: true, trafficEmpty: true, lossEmpty: true,
        _charts: {}, _map: null, _mapLayer: null, _topoNet: null,
        async init() {
            if (!NV.guard()) return;
            this.initTheme();
            this.tick();
            setInterval(() => this.tick(), 1000);
            await this.load();
            setInterval(() => this.load(), 30000);
        },
        initTheme() {
            this.theme = localStorage.getItem('nv_theme') || 'dark';
            document.body.classList.toggle('theme-light', this.theme === 'light');
        },
        toggleTheme() {
            this.theme = this.theme === 'light' ? 'dark' : 'light';
            localStorage.setItem('nv_theme', this.theme);
            document.body.classList.toggle('theme-light', this.theme === 'light');
            this.renderCharts();
        },
        tick() {
            this.clock = new Date().toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' });
        },
        async load() {
            try {
                const [summary, routerStatus, oltStatus, traffic, loss, alerts, critical, topology, map, routers, olts] = await Promise.all([
                    NV.api('/noc/dashboard/summary'),
                    NV.api('/noc/dashboard/router-status'),
                    NV.api('/noc/dashboard/olt-status'),
                    NV.api('/noc/dashboard/traffic-24h'),
                    NV.api('/noc/dashboard/loss-24h'),
                    NV.api('/noc/dashboard/alerts?limit=8'),
                    NV.api('/noc/dashboard/critical-devices'),
                    NV.api('/noc/dashboard/topology'),
                    NV.api('/noc/dashboard/map'),
                    NV.api('/routers?per_page=20'),
                    NV.api('/olts?per_page=20'),
                ]);
                this.summary = summary.data || {};
                this.routerStatus = routerStatus.data || {};
                this.oltStatus = oltStatus.data || {};
                this.traffic = traffic.data || { download: [], upload: [] };
                this.loss = loss.data || { loss: [] };
                this.alerts = alerts.data || [];
                this.devices = critical.data || [];
                this.topology = topology.data || { nodes: [], edges: [] };
                this.mapItems = map.data || [];
                this.routers = routers.data || [];
                this.olts = olts.data || [];
                this.buildCards();
                this.$nextTick(() => {
                    this.renderCharts();
                    this.renderMap();
                    this.renderTopology();
                });
            } catch (e) {
                NV.toast(e.message, 'err');
            }
        },
        buildCards() {
            const s = this.summary;
            const routers = s.routers || {};
            const customers = s.customers || {};
            const olts = s.olts || {};
            const onus = s.onus || {};
            const loss = s.loss || {};
            const tickets = s.tickets || {};
            this.cards = [
                { label: 'Total Router', value: NV.fmt(routers.total), icon: 'fa-router', ic: 'ic-blue', a: 'Online: ' + NV.fmt(routers.online), b: 'Offline: ' + NV.fmt(routers.offline) },
                { label: 'Total Pelanggan', value: NV.fmt(customers.total), icon: 'fa-users', ic: 'ic-green', a: 'Aktif: ' + NV.fmt(customers.active), b: 'Nonaktif: ' + NV.fmt((customers.inactive || 0) + (customers.isolir || 0)) },
                { label: 'Total OLT', value: NV.fmt(olts.total), icon: 'fa-server', ic: 'ic-violet', a: 'Online: ' + NV.fmt(olts.online), b: 'Offline: ' + NV.fmt(olts.offline) },
                { label: 'Total ONU', value: NV.fmt(onus.total), icon: 'fa-hard-drive', ic: 'ic-amber', a: 'Online: ' + NV.fmt(onus.online), b: 'Offline: ' + NV.fmt((onus.offline || 0) + (onus.los || 0)) },
                { label: 'Total Loss', value: this.formatNumber(loss.percent, 2) + ' %', icon: 'fa-wave-square', ic: 'ic-red', a: 'Normal: ' + NV.fmt(loss.normal), b: 'Critical: ' + NV.fmt(loss.critical) },
                { label: 'Total Tiket', value: NV.fmt(tickets.total), icon: 'fa-ticket', ic: 'ic-orange', a: 'Open: ' + NV.fmt(tickets.open), b: 'Closed: ' + NV.fmt(tickets.closed) },
            ];
        },
        renderCharts() {
            const router = this.routerStatus || {};
            const olt = this.oltStatus || {};
            const download = (this.traffic && this.traffic.download) || [];
            const upload = (this.traffic && this.traffic.upload) || [];
            const loss = (this.loss && this.loss.loss) || [];
            this.trafficEmpty = download.length === 0 && upload.length === 0;
            this.lossEmpty = loss.length === 0;
            this.draw('chartRouter', this.donut(['Online', 'Offline'], [Number(router.online || 0), Number(router.offline || 0)], ['#22c55e', '#ef4444']));
            this.draw('chartOlt', this.donut(['Online', 'Offline'], [Number(olt.online || 0), Number(olt.offline || 0)], ['#22c55e', '#ef4444']));
            this.draw('chartTraffic', this.area(download.map(x => x.bucket), [
                { name: 'Download', data: download.map(x => Number(x.value || 0)) },
                { name: 'Upload', data: upload.map(x => Number(x.value || 0)) },
            ], ['#2563eb', '#22c55e']));
            this.draw('chartLoss', this.area(loss.map(x => x.bucket), [{ name: 'Loss (%)', data: loss.map(x => Number(x.value || 0)) }], ['#ef4444']));
        },
        draw(elId, opts) {
            const el = document.getElementById(elId);
            if (!el || !window.ApexCharts) return;
            if (this._charts[elId]) {
                try { this._charts[elId].destroy(); } catch (e) {}
            }
            this._charts[elId] = new ApexCharts(el, opts);
            this._charts[elId].render();
        },
        chartTextColor() { return getComputedStyle(document.body).getPropertyValue('--text').trim() || '#e5edf7'; },
        chartMutedColor() { return getComputedStyle(document.body).getPropertyValue('--muted').trim() || '#8090a5'; },
        chartLineColor() { return getComputedStyle(document.body).getPropertyValue('--line').trim() || 'rgba(148,163,184,.18)'; },
        donut(labels, series, colors) {
            return {
                chart: { type: 'donut', height: 238, background: 'transparent', toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
                labels, series, colors, dataLabels: { enabled: false }, stroke: { width: 0 },
                plotOptions: { pie: { donut: { size: '72%', labels: { show: true, value: { color: this.chartTextColor(), fontSize: '24px', fontWeight: 700 }, total: { show: true, label: 'Total', color: this.chartMutedColor(), formatter: () => series.reduce((a, b) => a + b, 0) } } } } },
                legend: { show: true, position: 'right', labels: { colors: this.chartMutedColor() }, markers: { radius: 12 } },
                tooltip: { theme: this.theme === 'light' ? 'light' : 'dark' },
            };
        },
        area(categories, series, colors) {
            return {
                chart: { type: 'area', height: 222, background: 'transparent', toolbar: { show: false }, fontFamily: 'Outfit, sans-serif' },
                series, colors, dataLabels: { enabled: false }, stroke: { curve: 'smooth', width: 2 },
                grid: { borderColor: this.chartLineColor(), strokeDashArray: 4 },
                fill: { type: 'gradient', gradient: { opacityFrom: .32, opacityTo: .03, stops: [0, 95] } },
                xaxis: { categories, labels: { style: { colors: this.chartMutedColor() } }, axisBorder: { show: false }, axisTicks: { show: false } },
                yaxis: { labels: { style: { colors: this.chartMutedColor() } } },
                legend: { show: true, labels: { colors: this.chartMutedColor() } },
                tooltip: { theme: this.theme === 'light' ? 'light' : 'dark' },
            };
        },
        renderMap() {
            const el = document.getElementById('netMap');
            if (!el || !window.L) return;
            if (!this._map) {
                this._map = L.map(el, { attributionControl: false, zoomControl: true }).setView([-2.5, 118], 4.4);
                L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom: 19 }).addTo(this._map);
                this._mapLayer = L.layerGroup().addTo(this._map);
            }
            this._mapLayer.clearLayers();
            const points = this.mapItems.filter(item => item.latitude && item.longitude);
            this.mapEmpty = points.length === 0;
            points.forEach(item => {
                const color = item.status === 'online' || item.status === 'active' ? '#22c55e' : (item.status === 'offline' || item.status === 'down' ? '#ef4444' : '#f59e0b');
                L.circleMarker([item.latitude, item.longitude], { radius: 7, color, fillColor: color, fillOpacity: .9, weight: 2 })
                    .bindPopup('<b>' + (item.name || '-') + '</b><br>' + (item.type || '-') + '<br>' + (item.location || item.address || ''))
                    .addTo(this._mapLayer);
            });
            if (points.length) {
                this._map.fitBounds(points.map(item => [item.latitude, item.longitude]), { padding: [38, 38], maxZoom: 12 });
            }
            setTimeout(() => this._map.invalidateSize(), 80);
        },
        renderTopology() {
            const el = document.getElementById('topo');
            if (!el || !window.vis) return;
            if (this._topoNet) {
                try { this._topoNet.destroy(); } catch (e) {}
            }
            const savedNodes = (this.topology.nodes || []).map(node => ({
                id: node.id, label: node.label, shape: node.node_type === 'olt' ? 'square' : 'dot', color: this.topoColor(node.node_type), font: { color: this.chartTextColor() }
            }));
            const savedEdges = (this.topology.edges || []).map(edge => ({ from: edge.from_node, to: edge.to_node, color: { color: edge.color || '#22c55e' }, arrows: 'to' }));
            let nodes = savedNodes;
            let edges = savedEdges;
            if (!nodes.length) {
                const realRouters = this.routers || [];
                const realOlts = this.olts || [];
                if (!realRouters.length && !realOlts.length) {
                    this.topologyEmpty = true;
                    el.innerHTML = '';
                    return;
                }
                nodes = [{ id: 'core', label: 'CORE', shape: 'dot', size: 24, color: '#2563eb', font: { color: this.chartTextColor() } }];
                edges = [];
                realRouters.slice(0, 5).forEach((router, index) => {
                    nodes.push({ id: 'router-' + router.id, label: router.name, shape: 'dot', size: 18, color: router.status === 'online' ? '#22c55e' : '#ef4444', font: { color: this.chartTextColor() } });
                    edges.push({ from: 'core', to: 'router-' + router.id, color: { color: router.status === 'online' ? '#22c55e' : '#ef4444' }, arrows: 'to' });
                });
                realOlts.slice(0, 6).forEach((olt, index) => {
                    const parent = realRouters.length ? 'router-' + realRouters[index % realRouters.length].id : 'core';
                    nodes.push({ id: 'olt-' + olt.id, label: olt.name, shape: 'square', size: 16, color: olt.status === 'online' ? '#f59e0b' : '#ef4444', font: { color: this.chartTextColor() } });
                    edges.push({ from: parent, to: 'olt-' + olt.id, color: { color: olt.status === 'online' ? '#f59e0b' : '#ef4444' }, arrows: 'to' });
                });
            }
            this.topologyEmpty = nodes.length === 0;
            this._topoNet = new vis.Network(el, { nodes: new vis.DataSet(nodes), edges: new vis.DataSet(edges) }, {
                layout: { hierarchical: { enabled: true, direction: 'UD', sortMethod: 'directed', levelSeparation: 82, nodeSpacing: 135 } },
                physics: false,
                interaction: { dragNodes: true, zoomView: true },
                nodes: { borderWidth: 2, shadow: true },
                edges: { smooth: true },
            });
        },
        topoColor(type) {
            return { core: '#2563eb', router: '#22c55e', switch: '#22c55e', olt: '#f59e0b', odp: '#f59e0b', onu: '#16a34a', customer: '#38bdf8' }[type] || '#64748b';
        },
        alertClass(severity) { return severity === 'critical' ? 'ic-red' : (severity === 'warning' ? 'ic-orange' : 'ic-blue'); },
        deviceType(type) { return { router: 'MikroTik', olt: 'OLT', onu: 'ONU' }[type] || type || '-'; },
        percent(value) { return value === null || value === undefined ? '-' : Number(value || 0) + '%'; },
        meterStyle(value) { return 'width:' + Math.min(100, Math.max(0, Number(value || 0))) + '%'; },
        formatUptime(value) {
            const sec = Number(value || 0);
            if (!sec) return '-';
            const d = Math.floor(sec / 86400);
            const h = Math.floor((sec % 86400) / 3600);
            const m = Math.floor((sec % 3600) / 60);
            return (d ? d + 'd ' : '') + h + 'h ' + m + 'm';
        },
        formatNumber(value, max = 0) { return new Intl.NumberFormat('id-ID', { maximumFractionDigits: max }).format(Number(value || 0)); },
    };
}
</script>
JS;
echo $pageScript;
?>