<?php /** Tenant NOC Dashboard */ ?>
<div x-data="tenantDash()" x-init="init()">

    <div class="topbar">
        <div>
            <h1>Dashboard Monitoring</h1>
            <div class="sub">Overview status jaringan secara real-time</div>
        </div>
        <div class="topbar-actions">
            <div class="pill"><i class="fa-solid fa-calendar text-green"></i> <span x-text="clock"></span></div>
            <a href="/tv" class="icon-btn" title="TV Mode"><i class="fa-solid fa-tv"></i></a>
            <div class="icon-btn"><i class="fa-solid fa-bell"></i><span class="badge-dot" x-text="snap.alerts || 0"></span></div>
            <button class="btn btn-ghost" @click="runAI()" data-testid="ai-btn"><i class="fa-solid fa-brain text-green"></i> AI Analisa</button>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
        <template x-for="c in cards" :key="c.label">
            <div class="stat-card glass glass-hover reveal">
                <div class="stat-top"><span class="stat-label" x-text="c.label"></span>
                    <div class="stat-icon" :class="c.ic"><i class="fa-solid" :class="c.icon"></i></div></div>
                <div class="stat-value" x-text="c.value"></div>
                <div class="stat-meta"><span class="up" x-text="c.a"></span><span class="down" x-text="c.b"></span></div>
            </div>
        </template>
    </div>

    <!-- Row: router donut + map + alerts -->
    <div class="grid" style="grid-template-columns: 1fr 1.4fr 340px; align-items:start">
        <div class="glass panel reveal">
            <div class="panel-head"><span class="panel-title">Status Router</span></div>
            <div id="chartRouter"></div>
        </div>
        <div class="glass panel reveal" style="padding:0;overflow:hidden">
            <div class="panel-head" style="padding:18px 20px 0"><span class="panel-title">Peta Network</span></div>
            <div id="netMap" style="height:300px;margin-top:12px"></div>
        </div>
        <div class="glass panel reveal">
            <div class="panel-head"><span class="panel-title">Alert Terbaru</span><a class="panel-link" href="/dashboard#alerts">Lihat Semua</a></div>
            <template x-for="a in alerts" :key="a.id">
                <div class="alert-row">
                    <div class="alert-ic" :class="a.severity==='critical'?'ic-red':'ic-amber'"><i class="fa-solid fa-triangle-exclamation"></i></div>
                    <div style="flex:1"><div class="alert-title" x-text="a.source || a.type"></div><div class="alert-desc" x-text="a.message"></div></div>
                    <div class="alert-time" x-text="NV.timeAgo(a.created_at)"></div>
                </div>
            </template>
            <template x-if="alerts.length===0"><div style="color:var(--muted);font-size:13px;padding:14px">Tidak ada alert aktif. 🎉</div></template>
        </div>
    </div>

    <!-- Row: traffic + loss + olt donut -->
    <div class="grid" style="grid-template-columns: 1fr 1fr 0.9fr; margin-top:20px">
        <div class="glass panel reveal"><div class="panel-head"><span class="panel-title">Traffic (24 Jam)</span></div><div id="chartTraffic"></div></div>
        <div class="glass panel reveal"><div class="panel-head"><span class="panel-title">Loss Network (24 Jam)</span></div><div id="chartLoss"></div></div>
        <div class="glass panel reveal"><div class="panel-head"><span class="panel-title">Status OLT</span></div><div id="chartOlt"></div></div>
    </div>

    <!-- Row: topology + critical devices -->
    <div class="grid" style="grid-template-columns: 1fr 1.3fr; margin-top:20px; align-items:start">
        <div class="glass panel reveal">
            <div class="panel-head"><span class="panel-title">Topologi Network</span><a class="panel-link" href="/dashboard#topology">Full Topologi</a></div>
            <div id="topo" style="height:320px;border-radius:12px;background:rgba(0,0,0,0.2)"></div>
        </div>
        <div class="glass panel reveal">
            <div class="panel-head"><span class="panel-title">Status Perangkat Kritis</span><a class="panel-link" href="/dashboard#routers">Semua Perangkat</a></div>
            <div style="overflow-x:auto">
                <table class="nv">
                    <thead><tr><th>Perangkat</th><th>Tipe</th><th>Lokasi</th><th>Status</th><th>CPU</th><th>Memory</th><th>Last Seen</th></tr></thead>
                    <tbody>
                        <template x-for="d in devices" :key="d.id">
                            <tr>
                                <td style="font-weight:600" x-text="d.name"></td>
                                <td style="color:var(--muted)" x-text="d.type"></td>
                                <td x-text="d.location || '-'"></td>
                                <td><span class="chip" :class="d.status" x-text="d.status"></span></td>
                                <td><span class="mono" :style="'color:'+(d.cpu>80?'var(--red)':'var(--text)')" x-text="(d.cpu??0)+'%'"></span></td>
                                <td><span class="mono" x-text="(d.mem??0)+'%'"></span></td>
                                <td style="color:var(--muted);font-size:12.5px" x-text="NV.timeAgo(d.last_seen)"></td>
                            </tr>
                        </template>
                        <template x-if="devices.length===0"><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:24px">Belum ada perangkat. Tambahkan Router/OLT.</td></tr></template>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <!-- AI result modal -->
    <div x-show="aiOpen" x-cloak style="position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:200;display:grid;place-items:center;padding:20px" @click.self="aiOpen=false">
        <div class="glass panel" style="width:100%;max-width:560px">
            <div class="panel-head"><span class="panel-title"><i class="fa-solid fa-brain text-green"></i> AI Root Cause Analysis</span><i class="fa-solid fa-xmark" style="cursor:pointer;color:var(--muted)" @click="aiOpen=false"></i></div>
            <div x-show="aiLoading" style="padding:20px;text-align:center;color:var(--muted)"><i class="fa-solid fa-spinner fa-spin"></i> Menganalisa telemetry...</div>
            <pre x-show="!aiLoading" x-text="aiResult" style="white-space:pre-wrap;font-size:13px;color:var(--text);background:rgba(0,0,0,0.25);padding:16px;border-radius:12px;max-height:50vh;overflow:auto"></pre>
        </div>
    </div>
</div>

<?php $pageScript = <<<'JS'
<script>
function tenantDash() {
    return {
        snap: {}, cards: [], alerts: [], devices: [], clock: '', aiOpen:false, aiLoading:false, aiResult:'',
        async init() {
            if (!NV.guard()) return;
            this.tick(); setInterval(()=>this.tick(), 1000);
            await this.load();
            await this.loadDevices();
            setInterval(()=>this.load(), 15000); // realtime refresh
        },
        tick(){ this.clock = new Date().toLocaleString('id-ID',{day:'numeric',month:'short',year:'numeric',hour:'2-digit',minute:'2-digit'}); },
        async load() {
            try {
                const { data } = await NV.api('/dashboard');
                this.snap = data.snapshot; this.alerts = data.alerts;
                this.buildCards();
                this.renderCharts(data);
            } catch(e){ NV.toast(e.message,'err'); }
        },
        buildCards() {
            const s = this.snap;
            this.cards = [
                { label:'Total Router', value: NV.fmt(s.routers), icon:'fa-network-wired', ic:'ic-blue', a:'Online: '+NV.fmt(s.routers_online), b:'Offline: '+NV.fmt((s.routers||0)-(s.routers_online||0)) },
                { label:'Total Pelanggan', value: NV.fmt(s.customers), icon:'fa-users', ic:'ic-green', a:'Aktif: '+NV.fmt(s.customers_active), b:'' },
                { label:'Total OLT', value: NV.fmt(s.olts), icon:'fa-server', ic:'ic-violet', a:'Online: '+NV.fmt(s.olts_online), b:'Offline: '+NV.fmt((s.olts||0)-(s.olts_online||0)) },
                { label:'Total ONU', value: NV.fmt(s.onus), icon:'fa-hard-drive', ic:'ic-amber', a:'Online: '+NV.fmt(s.onus_online), b:'Offline: '+NV.fmt((s.onus||0)-(s.onus_online||0)) },
                { label:'Total ODP', value: NV.fmt(s.odps), icon:'fa-box-archive', ic:'ic-blue', a:'', b:'' },
                { label:'Total Tiket', value: NV.fmt(s.tickets_open), icon:'fa-ticket', ic:'ic-red', a:'Open: '+NV.fmt(s.tickets_open), b:'' },
            ];
        },
        _charts: {},
        renderCharts(data) {
            const r = data.router_status, o = data.olt_status;
            this.draw('chartRouter','#cR', NV_CHART.donut(['Online','Offline'],[+r.online||0,+r.offline||0],['#16f08f','#ff5470']));
            this.draw('chartOlt','#cO', NV_CHART.donut(['Online','Offline'],[+o.online||0,+o.offline||0],['#16f08f','#ff5470']));
            const dl = data.traffic.download||[], ul = data.traffic.upload||[];
            this.draw('chartTraffic','#cT', NV_CHART.area(dl.map(x=>x.bucket), [
                { name:'Download', data: dl.map(x=>+x.value) }, { name:'Upload', data: ul.map(x=>+x.value) }], ['#38bdf8','#16f08f']));
            const ls = data.loss||[];
            this.draw('chartLoss','#cL', NV_CHART.area(ls.map(x=>x.bucket), [{ name:'Loss %', data: ls.map(x=>+x.value) }], ['#ff5470']));
        },
        draw(elId, key, opts) {
            if (this._charts[elId]) { try { this._charts[elId].destroy(); } catch{} }
            const el = document.querySelector('#'+elId); if(!el) return;
            this._charts[elId] = new ApexCharts(el, opts); this._charts[elId].render();
        },
        async loadDevices() {
            try {
                const [routers, olts] = await Promise.all([ NV.api('/routers?per_page=10'), NV.api('/olts?per_page=10') ]);
                const rs = routers.data.map(d=>({ id:d.id, name:d.name, type:'MikroTik', location:d.location, status:d.status, cpu:d.cpu_load, mem:d.mem_usage, last_seen:d.last_seen }));
                const os = olts.data.map(d=>({ id:d.id, name:d.name, type:'OLT '+(d.vendor||'').toUpperCase(), location:d.location, status:d.status, cpu:0, mem:0, last_seen:d.last_seen }));
                this.devices = [...rs, ...os].slice(0,8);
                this.initMap(routers.data, olts.data);
                this.initTopo(routers.data, olts.data);
            } catch(e){ /* devices optional */ }
        },
        initMap(routers, olts) {
            const el = document.getElementById('netMap'); if(!el || el._init) return; el._init=true;
            const map = L.map(el,{ attributionControl:false, zoomControl:true }).setView([-2.5,118],4.4);
            L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png',{ maxZoom:19 }).addTo(map);
            const pts = [...routers, ...olts].filter(d=>d.latitude&&d.longitude);
            pts.forEach(d=>{
                const color = d.status==='online'?'#16f08f':d.status==='offline'?'#ff5470':'#ffb43a';
                L.circleMarker([d.latitude,d.longitude],{ radius:7,color,fillColor:color,fillOpacity:0.9,weight:2 })
                 .bindPopup('<b>'+d.name+'</b><br>'+(d.location||'')).addTo(map);
            });
            if (pts.length) map.fitBounds(pts.map(d=>[d.latitude,d.longitude]),{ padding:[40,40], maxZoom:7 });
        },
        initTopo(routers, olts) {
            const el = document.getElementById('topo'); if(!el || el._init) return; el._init=true;
            const nodes = [{ id:'core', label:'CORE', shape:'dot', size:20, color:'#38bdf8', font:{color:'#e8f5ee'} }];
            routers.slice(0,4).forEach((r,i)=> nodes.push({ id:'r'+i, label:r.name, color: r.status==='online'?'#16f08f':'#ff5470', font:{color:'#e8f5ee'} }));
            olts.slice(0,4).forEach((o,i)=> nodes.push({ id:'o'+i, label:o.name, shape:'square', color:'#ffb43a', font:{color:'#e8f5ee'} }));
            const edges = [];
            routers.slice(0,4).forEach((r,i)=> edges.push({ from:'core', to:'r'+i, color:{color:'#16f08f'} }));
            olts.slice(0,4).forEach((o,i)=> edges.push({ from:'r'+(i% Math.max(routers.length,1)), to:'o'+i, color:{color:'#34d399'} }));
            new vis.Network(el, { nodes:new vis.DataSet(nodes), edges:new vis.DataSet(edges) }, {
                layout:{ hierarchical:{ enabled:true, direction:'UD', sortMethod:'directed', levelSeparation:90 } },
                physics:false, interaction:{ dragNodes:true, zoomView:true },
                nodes:{ borderWidth:2, shadow:true }, edges:{ smooth:true, arrows:'to' }
            });
        },
        async runAI() {
            this.aiOpen=true; this.aiLoading=true; this.aiResult='';
            try { const { data } = await NV.api('/ai/analyze',{ method:'POST', body:{ mode:'rca' } }); this.aiResult = JSON.stringify(data.result, null, 2); }
            catch(e){ this.aiResult = 'Gagal: '+e.message+'\n(Pastikan OPENAI_API_KEY diset & paket Enterprise).'; }
            finally { this.aiLoading=false; }
        },
    };
}
</script>
JS;
echo $pageScript;
?>
