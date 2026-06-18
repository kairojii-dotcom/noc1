/* =====================================================================
   NETVORA NOC — Module page engine (Alpine). Handles every menu type.
   ===================================================================== */
function modulePage() {
    return {
        scope: window.NV_SCOPE, module: window.NV_MODULE, cfg: null, type: 'unknown',
        rows: [], meta: { page: 1, last_page: 1, total: 0 }, page: 1, search: '',
        loading: true, modalOpen: false, form: {}, editing: false,
        // custom-state
        ai: { mode: 'rca', loading: false, result: '' },
        settings: {}, report: {}, topoNet: null,

        init() {
            if (!NV.guard(this.scope === 'superadmin' ? 'super_admin' : null)) return;
            this.cfg = moduleConfig(this.scope, this.module);
            if (!this.cfg) { this.type = 'missing'; this.loading = false; return; }
            this.type = this.cfg.type;
            switch (this.type) {
                case 'crud': this.loadList(); break;
                case 'charts': this.loadCharts(); break;
                case 'maps': this.loadMaps(); break;
                case 'topology': this.loadTopology(); break;
                case 'ai': this.loading = false; break;
                case 'tenant-settings': this.loadSettings(); break;
                case 'sa-monitoring': this.loadMonitoring(); break;
                case 'reports': this.loadReports(); break;
                default: this.loading = false;
            }
        },

        /* ---------- CRUD ---------- */
        async loadList() {
            this.loading = true;
            try {
                const q = new URLSearchParams({ page: this.page, per_page: 12, search: this.search });
                const res = await NV.api(this.cfg.endpoint + '?' + q.toString());
                this.rows = res.data; this.meta = res.meta || { page:1,last_page:1,total:this.rows.length };
            } catch (e) { NV.toast(e.message, 'err'); }
            finally { this.loading = false; }
        },
        cell(row, col) {
            const v = row[col.key];
            return col.fmt ? col.fmt(v) : (v ?? '-');
        },
        openCreate() {
            this.editing = false; this.form = {};
            (this.cfg.fields || []).forEach(f => { if (f.def !== undefined) this.form[f.key] = f.def; });
            this.modalOpen = true;
        },
        openEdit(row) {
            this.editing = true;
            this.form = JSON.parse(JSON.stringify(row));
            if (Array.isArray(this.form.permissions)) this.form.permissions = this.form.permissions.join(', ');
            this.modalOpen = true;
        },
        async save() {
            try {
                const body = { ...this.form };
                delete body.password_hash; delete body.tenant_name; delete body.created_at; delete body.updated_at;
                if (this.editing) { await NV.api(this.cfg.endpoint + '/' + this.form.id, { method: 'PUT', body }); NV.toast('Data diperbarui'); }
                else { await NV.api(this.cfg.endpoint, { method: 'POST', body }); NV.toast('Data dibuat'); }
                this.modalOpen = false; this.loadList();
            } catch (e) { NV.toast(e.message, 'err'); }
        },
        async remove(row) {
            if (!confirm('Hapus data ini?')) return;
            try { await NV.api(this.cfg.endpoint + '/' + row.id, { method: 'DELETE' }); NV.toast('Data dihapus'); this.loadList(); }
            catch (e) { NV.toast(e.message, 'err'); }
        },

        /* ---------- Charts (Traffic/Loss) ---------- */
        async loadCharts() {
            this.loading = false;
            try {
                const { data } = await NV.api('/dashboard');
                const dl = data.traffic.download || [], ul = data.traffic.upload || [], ls = data.loss || [];
                this.$nextTick(() => {
                    new ApexCharts(document.querySelector('#mTraffic'),
                        NV_CHART.area(dl.map(x=>x.bucket), [{name:'Download',data:dl.map(x=>+x.value)},{name:'Upload',data:ul.map(x=>+x.value)}], ['#38bdf8','#16f08f'])).render();
                    new ApexCharts(document.querySelector('#mLoss'),
                        NV_CHART.area(ls.map(x=>x.bucket), [{name:'Loss %',data:ls.map(x=>+x.value)}], ['#ff5470'])).render();
                });
            } catch (e) { NV.toast(e.message, 'err'); }
        },

        /* ---------- Maps ---------- */
        async loadMaps() {
            this.loading = false;
            try {
                const [r, o, od, c] = await Promise.all([
                    NV.api('/routers?per_page=200'), NV.api('/olts?per_page=200'),
                    NV.api('/odps?per_page=200'), NV.api('/customers?per_page=500'),
                ]);
                this.$nextTick(() => {
                    const el = document.getElementById('bigMap'); if (!el) return;
                    const map = L.map(el, { attributionControl:false }).setView([-2.5,118], 5);
                    L.tileLayer('https://{s}.basemaps.cartocdn.com/dark_all/{z}/{x}/{y}{r}.png', { maxZoom:19 }).addTo(map);
                    const add = (arr, color, label) => arr.filter(d=>d.latitude&&d.longitude).forEach(d=>
                        L.circleMarker([d.latitude,d.longitude], { radius:7,color,fillColor:color,fillOpacity:0.85,weight:2 })
                         .bindPopup('<b>'+(d.name||d.serial||'-')+'</b><br>'+label).addTo(map));
                    add(r.data, '#38bdf8', 'Router'); add(o.data, '#a78bfa', 'OLT');
                    add(od.data, '#ffb43a', 'ODP'); add(c.data, '#16f08f', 'Pelanggan');
                    const all = [...r.data,...o.data,...od.data,...c.data].filter(d=>d.latitude&&d.longitude).map(d=>[d.latitude,d.longitude]);
                    if (all.length) map.fitBounds(all, { padding:[40,40], maxZoom:12 });
                });
            } catch (e) { NV.toast(e.message, 'err'); }
        },

        /* ---------- Topology editor ---------- */
        async loadTopology() {
            this.loading = false;
            try {
                const [topo, r, o] = await Promise.all([ NV.api('/topology'), NV.api('/routers?per_page=100'), NV.api('/olts?per_page=100') ]);
                this.$nextTick(() => this.renderTopo(topo.data, r.data, o.data));
            } catch (e) { NV.toast(e.message, 'err'); }
        },
        renderTopo(topo, routers, olts) {
            const el = document.getElementById('topoEdit'); if (!el) return;
            const nodes = new vis.DataSet(); const edges = new vis.DataSet();
            const colorOf = (t)=>({router:'#38bdf8',switch:'#34d399',olt:'#ffb43a',odp:'#f59e0b',splitter:'#a78bfa',onu:'#16f08f',server:'#22d3ee',internet:'#60a5fa',cloud:'#93c5fd'}[t]||'#16f08f');
            if (topo.nodes && topo.nodes.length) {
                topo.nodes.forEach(n => nodes.add({ id:n.id, label:n.label, color:colorOf(n.node_type), font:{color:'#e8f5ee'}, _type:n.node_type }));
                topo.edges.forEach(e => edges.add({ from:e.from_node, to:e.to_node, color:{color:e.color||'#16f08f'} }));
            } else {
                nodes.add({ id:'core', label:'INTERNET', shape:'dot', size:22, color:'#60a5fa', font:{color:'#e8f5ee'}, _type:'internet' });
                routers.forEach((d,i)=>{ nodes.add({ id:'r'+i, label:d.name, color:colorOf('router'), font:{color:'#e8f5ee'}, _type:'router' }); edges.add({ from:'core', to:'r'+i, color:{color:'#16f08f'} }); });
                olts.forEach((d,i)=>{ nodes.add({ id:'o'+i, label:d.name, shape:'square', color:colorOf('olt'), font:{color:'#e8f5ee'}, _type:'olt' }); edges.add({ from:routers.length?('r'+(i%routers.length)):'core', to:'o'+i, color:{color:'#34d399'} }); });
            }
            const net = new vis.Network(el, { nodes, edges }, {
                physics:{ enabled:true, stabilization:true }, interaction:{ dragNodes:true, multiselect:true },
                manipulation:{ enabled:true,
                    addNode:(data,cb)=>{ data.label=prompt('Label node:','Node')||'Node'; data._type='router'; data.color=colorOf('router'); data.font={color:'#e8f5ee'}; cb(data); },
                    addEdge:(data,cb)=>{ data.color={color:'#16f08f'}; cb(data); },
                    editNode:(data,cb)=>{ data.label=prompt('Edit label:',data.label)||data.label; cb(data); },
                },
                nodes:{ borderWidth:2, shadow:true }, edges:{ smooth:true, arrows:'to' },
            });
            this.topoNet = { net, nodes, edges };
        },
        async saveTopology() {
            if (!this.topoNet) return;
            const ns = this.topoNet.nodes.get().map(n => ({ id:n.id, label:n.label, node_type:n._type||'router' }));
            const es = this.topoNet.edges.get().map(e => ({ from:e.from, to:e.to, color:(e.color&&e.color.color)||'#16f08f' }));
            try { await NV.api('/topology', { method:'POST', body:{ nodes:ns, edges:es } }); NV.toast('Topologi disimpan'); }
            catch (e) { NV.toast(e.message, 'err'); }
        },

        /* ---------- AI ---------- */
        async runAI() {
            this.ai.loading = true; this.ai.result = '';
            try { const { data } = await NV.api('/ai/analyze', { method:'POST', body:{ mode:this.ai.mode } }); this.ai.result = JSON.stringify(data.result, null, 2); }
            catch (e) { this.ai.result = 'Gagal: ' + e.message + '\n(Set OPENAI_API_KEY & paket Enterprise).'; }
            finally { this.ai.loading = false; }
        },

        /* ---------- Tenant settings ---------- */
        async loadSettings() {
            this.loading = false;
            try { const { data } = await NV.api('/tenant/profile'); this.settings = data;
                ['branding','smtp_config','wa_config','mikrotik_api','acs_api','billing_config'].forEach(k=>{ if(typeof this.settings[k]==='string'){ try{ this.settings[k]=JSON.parse(this.settings[k]); }catch{} } });
            } catch (e) { NV.toast(e.message,'err'); }
        },
        async saveSettings() {
            try { await NV.api('/tenant/profile', { method:'PUT', body:this.settings }); NV.toast('Pengaturan disimpan'); }
            catch (e) { NV.toast(e.message,'err'); }
        },

        /* ---------- SA Monitoring ---------- */
        async loadMonitoring() {
            this.loading = false;
            try { const { data } = await NV.api('/superadmin/dashboard'); this.report = data; }
            catch (e) { NV.toast(e.message,'err'); }
        },

        /* ---------- Reports ---------- */
        async loadReports() {
            this.loading = false;
            try { const { data } = await NV.api('/dashboard'); this.report = data.snapshot; }
            catch (e) { NV.toast(e.message,'err'); }
        },
        exportCSV() {
            const rows = Object.entries(this.report || {});
            const csv = 'Metric,Value\n' + rows.map(([k,v])=>`${k},${v}`).join('\n');
            const blob = new Blob([csv], { type:'text/csv' }); const a = document.createElement('a');
            a.href = URL.createObjectURL(blob); a.download = 'laporan-noc.csv'; a.click(); NV.toast('CSV diunduh');
        },

        /* ---------- Backup ---------- */
        backupDone: false,
        runBackup() { this.backupDone = true; NV.toast('Backup dijadwalkan via cron (php cron/scheduler.php). Lihat README.'); },
    };
}
window.modulePage = modulePage;
