<?php /** Super Admin Dashboard */ ?>
<div x-data="superadminDash()" x-init="init()">

    <!-- Topbar -->
    <div class="topbar">
        <div>
            <h1>Super Admin Dashboard</h1>
            <div class="sub">Overview & Management Semua Tenant</div>
        </div>
        <div class="topbar-actions">
            <div class="pill"><i class="fa-solid fa-magnifying-glass" style="color:var(--muted)"></i>
                <input x-model="search" @input.debounce.400ms="loadTenants()" placeholder="Cari Tenant, Domain, Email..." style="background:none;border:none;outline:none;color:var(--text);font-size:13px;width:200px">
            </div>
            <div class="icon-btn"><i class="fa-solid fa-bell"></i><span class="badge-dot" x-text="stats.pending_payments || 0"></span></div>
            <button class="btn btn-primary" @click="openAdd=true" data-testid="add-tenant-btn"><i class="fa-solid fa-plus"></i> Tambah Tenant</button>
        </div>
    </div>

    <!-- Stat cards -->
    <div class="stat-grid">
        <template x-for="c in cards" :key="c.label">
            <div class="stat-card glass glass-hover reveal">
                <div class="stat-top">
                    <span class="stat-label" x-text="c.label"></span>
                    <div class="stat-icon" :class="c.ic"><i class="fa-solid" :class="c.icon"></i></div>
                </div>
                <div class="stat-value" x-text="c.value"></div>
                <div class="stat-meta"><span x-text="c.sub" style="color:var(--muted)"></span></div>
            </div>
        </template>
    </div>

    <!-- Main grid: tenant table + sidebar -->
    <div class="grid" style="grid-template-columns: 1fr 340px; align-items:start">
        <div class="glass panel reveal">
            <div class="panel-head">
                <span class="panel-title">Daftar Semua Tenant</span>
                <div class="flex gap-2">
                    <select class="field" style="width:auto;padding:8px 12px" x-model="fStatus" @change="loadTenants()">
                        <option value="">Semua Status</option><option value="active">Aktif</option><option value="suspend">Suspend</option><option value="expired">Expired</option>
                    </select>
                    <select class="field" style="width:auto;padding:8px 12px" x-model="fPackage" @change="loadTenants()">
                        <option value="">Semua Paket</option><option value="basic">Basic</option><option value="professional">Professional</option><option value="enterprise">Enterprise</option>
                    </select>
                </div>
            </div>

            <div style="overflow-x:auto">
                <table class="nv" data-testid="tenant-table">
                    <thead><tr><th>Nama Tenant</th><th>Domain</th><th>Paket</th><th>Admin</th><th>Status</th><th>Expired</th><th>Aksi</th></tr></thead>
                    <tbody>
                        <template x-if="loading">
                            <template x-for="i in 5"><tr><td colspan="7"><div class="skel" style="height:36px"></div></td></tr></template>
                        </template>
                        <template x-for="t in tenants" :key="t.id">
                            <tr>
                                <td><div style="font-weight:600" x-text="t.name"></div><div style="font-size:12px;color:var(--muted)" x-text="t.isp_name || '-'"></div></td>
                                <td class="mono" style="font-size:12.5px" x-text="t.domain"></td>
                                <td><span class="badge-pkg" :class="'pkg-'+(t.package_code||'basic')" x-text="(t.package_code||'basic').charAt(0).toUpperCase()+(t.package_code||'basic').slice(1)"></span></td>
                                <td style="font-size:12.5px;color:var(--muted)" x-text="t.admin_email || t.email || '-'"></td>
                                <td><span class="chip" :class="t.status" x-text="statusLabel(t.status)"></span></td>
                                <td><div style="font-size:13px" x-text="fmtDate(t.expired_at)"></div></td>
                                <td>
                                    <div class="flex gap-2">
                                        <i class="fa-solid fa-pen icon-btn" style="width:32px;height:32px;font-size:12px" @click="edit(t)" :title="'Edit '+t.name"></i>
                                        <i class="fa-solid fa-pause icon-btn" style="width:32px;height:32px;font-size:12px" @click="setStatus(t,'suspend')" title="Suspend"></i>
                                        <i class="fa-solid fa-trash icon-btn" style="width:32px;height:32px;font-size:12px;color:var(--red)" @click="remove(t)" title="Hapus"></i>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!loading && tenants.length===0">
                            <tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">Belum ada tenant. Klik "Tambah Tenant".</td></tr>
                        </template>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between" style="margin-top:16px">
                <span style="font-size:13px;color:var(--muted)" x-text="'Menampilkan '+tenants.length+' dari '+meta.total+' tenant'"></span>
                <div class="flex gap-2">
                    <button class="btn btn-ghost" style="padding:7px 12px" :disabled="meta.page<=1" @click="page--;loadTenants()"><i class="fa-solid fa-chevron-left"></i></button>
                    <span class="pill" x-text="meta.page+' / '+meta.last_page"></span>
                    <button class="btn btn-ghost" style="padding:7px 12px" :disabled="meta.page>=meta.last_page" @click="page++;loadTenants()"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>

        <!-- Right column -->
        <div class="grid">
            <div class="glass panel reveal">
                <div class="panel-head"><span class="panel-title">Top 5 Tenant</span></div>
                <template x-for="(t,i) in top" :key="t.id">
                    <div style="margin-bottom:14px">
                        <div class="flex items-center justify-between" style="margin-bottom:6px">
                            <span style="font-size:13.5px;font-weight:600"><span class="text-green" x-text="(i+1)+'. '"></span><span x-text="t.name"></span></span>
                            <span style="font-size:12px;color:var(--muted)" x-text="NV.fmt(t.customer_count)+' pelanggan'"></span>
                        </div>
                        <div style="height:6px;border-radius:4px;background:rgba(255,255,255,0.05)">
                            <div style="height:6px;border-radius:4px;background:linear-gradient(90deg,var(--green),var(--green-deep))" :style="'width:'+barWidth(t.customer_count)+'%'"></div>
                        </div>
                    </div>
                </template>
            </div>

            <div class="glass panel reveal">
                <div class="panel-head"><span class="panel-title">Alert Tenant</span></div>
                <template x-for="a in alerts" :key="a.id">
                    <div class="alert-row">
                        <div class="alert-ic" :class="a.severity==='critical'?'ic-red':'ic-amber'"><i class="fa-solid fa-triangle-exclamation"></i></div>
                        <div style="flex:1">
                            <div class="alert-title" x-text="a.tenant_name || a.source"></div>
                            <div class="alert-desc" x-text="a.message"></div>
                        </div>
                        <div class="alert-time" x-text="NV.timeAgo(a.created_at)"></div>
                    </div>
                </template>
                <template x-if="alerts.length===0"><div style="color:var(--muted);font-size:13px;padding:10px">Tidak ada alert.</div></template>
            </div>
        </div>
    </div>

    <!-- Charts row -->
    <div class="grid" style="grid-template-columns: repeat(3,1fr); margin-top:20px">
        <div class="glass panel reveal"><div class="panel-head"><span class="panel-title">Status Tenant</span></div><div id="chartStatus"></div></div>
        <div class="glass panel reveal"><div class="panel-head"><span class="panel-title">Tenant Baru (30 Hari)</span></div><div id="chartGrowth"></div></div>
        <div class="glass panel reveal"><div class="panel-head"><span class="panel-title">Distribusi Paket</span></div><div id="chartPackage"></div></div>
    </div>

    <!-- Add/Edit tenant modal -->
    <div x-show="openAdd" x-cloak style="position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:200;display:grid;place-items:center;padding:20px" @click.self="openAdd=false">
        <div class="glass panel reveal" style="width:100%;max-width:540px" data-testid="tenant-modal">
            <div class="panel-head"><span class="panel-title" x-text="form.id?'Edit Tenant':'Tambah Tenant'"></span><i class="fa-solid fa-xmark" style="cursor:pointer;color:var(--muted)" @click="openAdd=false"></i></div>
            <div class="grid" style="grid-template-columns:1fr 1fr">
                <div><label class="lbl">Nama ISP</label><input class="field" x-model="form.name" data-testid="form-name"></div>
                <div><label class="lbl">Domain</label><input class="field" x-model="form.domain" placeholder="noc.example.id" data-testid="form-domain"></div>
                <div><label class="lbl">Email</label><input class="field" x-model="form.email"></div>
                <div><label class="lbl">No WhatsApp</label><input class="field" x-model="form.phone_wa"></div>
                <div><label class="lbl">Paket</label><select class="field" x-model="form.package"><option value="basic">Basic</option><option value="professional">Professional</option><option value="enterprise">Enterprise</option></select></div>
                <div><label class="lbl">Expired</label><input type="date" class="field" x-model="form.expired_at"></div>
                <div style="grid-column:1/3"><label class="lbl">Alamat</label><input class="field" x-model="form.address"></div>
                <div><label class="lbl">Admin Email</label><input class="field" x-model="form.admin_email"></div>
                <div><label class="lbl">Admin Password</label><input class="field" x-model="form.admin_password" placeholder="auto-generate jika kosong"></div>
            </div>
            <div class="flex gap-3" style="margin-top:18px;justify-content:flex-end">
                <button class="btn btn-ghost" @click="openAdd=false">Batal</button>
                <button class="btn btn-primary" @click="save()" data-testid="form-submit"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>

<?php $pageScript = <<<'JS'
<script>
function superadminDash() {
    return {
        stats: {}, cards: [], tenants: [], top: [], alerts: [], meta: { page:1, last_page:1, total:0 },
        loading: true, page: 1, search: '', fStatus: '', fPackage: '',
        openAdd: false, form: this?.blank ?? { package: 'basic' },
        blank() { return { package:'basic', name:'', domain:'', email:'', phone_wa:'', address:'', expired_at:'', admin_email:'', admin_password:'' }; },

        async init() {
            if (!NV.guard('super_admin')) return;
            this.form = this.blank();
            await this.loadDash();
            await this.loadTenants();
        },
        async loadDash() {
            try {
                const { data } = await NV.api('/superadmin/dashboard');
                this.stats = data.stats; this.top = data.top_tenants; this.alerts = data.alerts;
                this.buildCards();
                this.renderCharts(data);
            } catch (e) { NV.toast(e.message, 'err'); }
        },
        buildCards() {
            const s = this.stats;
            this.cards = [
                { label:'Total Tenant', value: NV.fmt(s.total_tenants), sub:'Semua Tenant', icon:'fa-building', ic:'ic-blue' },
                { label:'Tenant Aktif', value: NV.fmt(s.active_tenants), sub:'Aktif', icon:'fa-circle-check', ic:'ic-green' },
                { label:'Tenant Suspend', value: NV.fmt(s.suspend_tenants), sub:'Suspend', icon:'fa-circle-pause', ic:'ic-amber' },
                { label:'Total User', value: NV.fmt(s.total_users), sub:'Semua User', icon:'fa-user', ic:'ic-violet' },
                { label:'Total Router', value: NV.fmt(s.total_routers), sub:'Semua Router', icon:'fa-wifi', ic:'ic-blue' },
                { label:'Total Pelanggan', value: NV.fmt(s.total_customers), sub:'Semua Pelanggan', icon:'fa-users', ic:'ic-green' },
                { label:'Total OLT', value: NV.fmt(s.total_olts), sub:'Semua OLT', icon:'fa-server', ic:'ic-violet' },
                { label:'Total ONU', value: NV.fmt(s.total_onus), sub:'Semua ONU', icon:'fa-hard-drive', ic:'ic-blue' },
                { label:'Total Tiket', value: NV.fmt(s.open_tickets), sub:'Tiket terbuka', icon:'fa-ticket', ic:'ic-amber' },
                { label:'MRR (Bulan Ini)', value: NV.rupiah(s.mrr), sub:'Monthly Recurring', icon:'fa-sack-dollar', ic:'ic-green' },
                { label:'Pending Payment', value: NV.fmt(s.pending_payments), sub:'Belum bayar', icon:'fa-triangle-exclamation', ic:'ic-red' },
                { label:'Revenue Tahun', value: NV.rupiah(s.revenue_year), sub:'Total tahun ini', icon:'fa-chart-pie', ic:'ic-violet' },
            ];
        },
        renderCharts(data) {
            const s = this.stats;
            new ApexCharts(document.querySelector('#chartStatus'),
                NV_CHART.donut(['Aktif','Suspend','Expired'], [+s.active_tenants||0, +s.suspend_tenants||0, +s.expired_tenants||0], ['#16f08f','#ff5470','#ffb43a'])).render();
            const g = data.new_tenants || [];
            new ApexCharts(document.querySelector('#chartGrowth'),
                NV_CHART.area(g.map(x=>x.day.slice(5)), [{ name:'Tenant Baru', data: g.map(x=>+x.total) }], ['#16f08f'])).render();
            const p = data.packages || [];
            new ApexCharts(document.querySelector('#chartPackage'),
                NV_CHART.donut(p.map(x=>x.name), p.map(x=>+x.total), ['#16f08f','#38bdf8','#a78bfa'])).render();
        },
        async loadTenants() {
            this.loading = true;
            try {
                const q = new URLSearchParams({ page:this.page, per_page:8, search:this.search, status:this.fStatus, package:this.fPackage });
                const res = await NV.api('/tenants?'+q.toString());
                this.tenants = res.data; this.meta = res.meta;
            } catch (e) { NV.toast(e.message,'err'); }
            finally { this.loading = false; }
        },
        edit(t) { this.form = { id:t.id, name:t.name, domain:t.domain, email:t.email, phone_wa:t.phone_wa, address:t.address, package:t.package_code||'basic', expired_at:(t.expired_at||'').slice(0,10) }; this.openAdd=true; },
        async save() {
            try {
                if (this.form.id) { await NV.api('/tenants/'+this.form.id,{method:'PUT',body:this.form}); NV.toast('Tenant diperbarui'); }
                else { const r = await NV.api('/tenants',{method:'POST',body:this.form}); NV.toast('Tenant dibuat. Login owner: '+r.data.owner_email+' / '+r.data.owner_password); }
                this.openAdd=false; this.form=this.blank(); await this.loadTenants(); await this.loadDash();
            } catch(e){ NV.toast(e.message,'err'); }
        },
        async setStatus(t, st) { try { await NV.api('/tenants/'+t.id+'/status',{method:'PATCH',body:{status:st}}); NV.toast('Status diubah'); this.loadTenants(); } catch(e){ NV.toast(e.message,'err'); } },
        async remove(t) { if(!confirm('Hapus tenant '+t.name+'?'))return; try { await NV.api('/tenants/'+t.id,{method:'DELETE'}); NV.toast('Tenant dihapus'); this.loadTenants(); this.loadDash(); } catch(e){ NV.toast(e.message,'err'); } },
        statusLabel(s){ return {active:'Aktif',suspend:'Suspend',expired:'Expired'}[s]||s; },
        fmtDate(d){ if(!d)return '-'; return new Date(d).toLocaleDateString('id-ID',{day:'numeric',month:'short',year:'numeric'}); },
        barWidth(n){ const max = Math.max(...this.top.map(t=>+t.customer_count),1); return Math.round((+n/max)*100); },
    };
}
</script>
JS;
echo $pageScript;
?>
