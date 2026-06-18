<?php /** Generic module page. @var string $scope @var string $module */ ?>
<script>window.NV_SCOPE = <?= json_encode($scope) ?>; window.NV_MODULE = <?= json_encode($module) ?>;</script>

<div x-data="modulePage()" x-init="init()">

    <!-- Topbar -->
    <div class="topbar">
        <div>
            <h1><i class="fa-solid" :class="cfg?.icon || 'fa-cube'" style="color:var(--green)"></i> <span x-text="cfg?.title || 'Module'"></span></h1>
            <div class="sub" x-text="(scope==='superadmin'?'Super Admin':'Tenant NOC')+' · '+module"></div>
        </div>
        <div class="topbar-actions">
            <a :href="scope==='superadmin' ? '/superadmin' : '/dashboard'" class="btn btn-ghost"><i class="fa-solid fa-arrow-left"></i> Dashboard</a>
            <template x-if="type==='crud' && !cfg.readonly">
                <button class="btn btn-primary" @click="openCreate()" data-testid="module-create"><i class="fa-solid fa-plus"></i> Tambah</button>
            </template>
            <template x-if="type==='crud' && cfg.topActions">
                <template x-for="a in cfg.topActions" :key="a.label">
                    <button class="btn btn-ghost" @click="runTopAction(a)"><i class="fa-solid" :class="a.icon"></i> <span x-text="a.label"></span></button>
                </template>
            </template>
        </div>
    </div>

    <!-- Missing module -->
    <template x-if="type==='missing'">
        <div class="glass panel" style="text-align:center;padding:60px">
            <i class="fa-solid fa-screwdriver-wrench" style="font-size:40px;color:var(--green);margin-bottom:14px"></i>
            <h2 style="margin:0 0 6px">Modul belum dikonfigurasi</h2>
            <p style="color:var(--muted)">Menu "<span x-text="module"></span>" akan tersedia pada update berikutnya.</p>
        </div>
    </template>

    <!-- ============ CRUD ============ -->
    <template x-if="type==='crud'">
        <div class="glass panel">
            <div class="panel-head">
                <span class="panel-title" x-text="cfg.title"></span>
                <div class="pill"><i class="fa-solid fa-magnifying-glass" style="color:var(--muted)"></i>
                    <input x-model="search" @input.debounce.400ms="page=1;loadList()" placeholder="Cari..." style="background:none;border:none;outline:none;color:var(--text);font-size:13px">
                </div>
            </div>
            <div style="overflow-x:auto">
                <table class="nv" data-testid="module-table">
                    <thead><tr>
                        <template x-for="col in cfg.columns" :key="col.key"><th x-text="col.label"></th></template>
                        <th style="text-align:right">Aksi</th>
                    </tr></thead>
                    <tbody>
                        <template x-if="loading"><template x-for="i in 6"><tr><td :colspan="cfg.columns.length+1"><div class="skel" style="height:34px"></div></td></tr></template></template>
                        <template x-for="row in rows" :key="row.id">
                            <tr>
                                <template x-for="col in cfg.columns" :key="col.key"><td x-html="cell(row,col)"></td></template>
                                <td style="text-align:right">
                                    <div class="flex gap-2" style="justify-content:flex-end">
                                        <template x-if="cfg.rowActions"><template x-for="a in cfg.rowActions" :key="a.label">
                                            <i class="fa-solid icon-btn" :class="a.icon" style="width:30px;height:30px;font-size:11px;color:var(--green)" @click="runRowAction(a,row)" :title="a.label"></i>
                                        </template></template>
                                        <template x-if="!cfg.readonly">
                                            <i class="fa-solid fa-pen icon-btn" style="width:30px;height:30px;font-size:11px" @click="openEdit(row)"></i>
                                        </template>
                                        <template x-if="!cfg.readonly">
                                            <i class="fa-solid fa-trash icon-btn" style="width:30px;height:30px;font-size:11px;color:var(--red)" @click="remove(row)"></i>
                                        </template>
                                        <template x-if="cfg.readonly"><span style="color:var(--faint);font-size:12px">—</span></template>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="!loading && rows.length===0"><tr><td :colspan="cfg.columns.length+1" style="text-align:center;color:var(--muted);padding:30px">Belum ada data.</td></tr></template>
                    </tbody>
                </table>
            </div>
            <div class="flex items-center justify-between" style="margin-top:16px">
                <span style="font-size:13px;color:var(--muted)" x-text="'Total '+meta.total+' data'"></span>
                <div class="flex gap-2">
                    <button class="btn btn-ghost" style="padding:7px 12px" :disabled="meta.page<=1" @click="page--;loadList()"><i class="fa-solid fa-chevron-left"></i></button>
                    <span class="pill" x-text="meta.page+' / '+meta.last_page"></span>
                    <button class="btn btn-ghost" style="padding:7px 12px" :disabled="meta.page>=meta.last_page" @click="page++;loadList()"><i class="fa-solid fa-chevron-right"></i></button>
                </div>
            </div>
        </div>
    </template>

    <!-- ============ CHARTS (Traffic/Loss) ============ -->
    <template x-if="type==='charts'">
        <div class="grid" style="grid-template-columns:1fr 1fr">
            <div class="glass panel"><div class="panel-head"><span class="panel-title">Traffic (24 Jam)</span></div><div id="mTraffic"></div></div>
            <div class="glass panel"><div class="panel-head"><span class="panel-title">Loss Network (24 Jam)</span></div><div id="mLoss"></div></div>
        </div>
    </template>

    <!-- ============ MAPS ============ -->
    <template x-if="type==='maps'">
        <div class="glass panel" style="padding:0;overflow:hidden"><div id="bigMap" style="height:72vh"></div></div>
    </template>

    <!-- ============ TOPOLOGY ============ -->
    <template x-if="type==='topology'">
        <div class="glass panel">
            <div class="panel-head"><span class="panel-title">Topologi Network (Drag &amp; Drop)</span>
                <button class="btn btn-primary" @click="saveTopology()" data-testid="topo-save"><i class="fa-solid fa-floppy-disk"></i> Simpan Topologi</button>
            </div>
            <div style="font-size:12.5px;color:var(--muted);margin-bottom:10px"><i class="fa-solid fa-circle-info"></i> Gunakan toolbar Vis untuk tambah node/link, edit label, lalu klik Simpan.</div>
            <div id="topoEdit" style="height:66vh;border-radius:12px;background:rgba(0,0,0,0.25)"></div>
        </div>
    </template>

    <!-- ============ AI ============ -->
    <template x-if="type==='ai'">
        <div class="glass panel">
            <div class="panel-head"><span class="panel-title"><i class="fa-solid fa-brain text-green"></i> AI Network Analytics</span></div>
            <div class="flex gap-3" style="margin-bottom:16px;flex-wrap:wrap">
                <select class="field" style="width:auto" x-model="ai.mode">
                    <option value="rca">Root Cause Analysis</option>
                    <option value="predictive">Predictive Alarm</option>
                    <option value="capacity">Capacity Planning</option>
                </select>
                <button class="btn btn-primary" @click="runAI()" :disabled="ai.loading" data-testid="ai-run">
                    <span x-show="!ai.loading"><i class="fa-solid fa-wand-magic-sparkles"></i> Jalankan Analisa</span>
                    <span x-show="ai.loading"><i class="fa-solid fa-spinner fa-spin"></i> Menganalisa...</span>
                </button>
            </div>
            <pre x-show="ai.result" x-text="ai.result" style="white-space:pre-wrap;font-size:13px;background:rgba(0,0,0,0.25);padding:18px;border-radius:12px;max-height:60vh;overflow:auto"></pre>
            <template x-if="!ai.result && !ai.loading"><div style="color:var(--muted);padding:20px">Pilih mode lalu jalankan analisa AI atas telemetry jaringan tenant ini.</div></template>
        </div>
    </template>

    <!-- ============ REPORTS ============ -->
    <template x-if="type==='reports'">
        <div class="glass panel">
            <div class="panel-head"><span class="panel-title">Laporan Ringkas</span>
                <button class="btn btn-primary" @click="exportCSV()"><i class="fa-solid fa-file-csv"></i> Export CSV</button>
            </div>
            <div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
                <template x-for="(v,k) in report" :key="k">
                    <div class="stat-card glass"><div class="stat-label" x-text="k"></div><div class="stat-value" style="font-size:24px" x-text="NV.fmt(v)"></div></div>
                </template>
            </div>
        </div>
    </template>

    <!-- ============ SA MONITORING ============ -->
    <template x-if="type==='sa-monitoring'">
        <div class="glass panel">
            <div class="panel-head"><span class="panel-title">Monitoring Global</span></div>
            <div class="stat-grid" style="grid-template-columns:repeat(4,1fr)">
                <template x-for="(v,k) in (report.stats||{})" :key="k">
                    <div class="stat-card glass"><div class="stat-label" x-text="k"></div><div class="stat-value" style="font-size:24px" x-text="NV.fmt(v)"></div></div>
                </template>
            </div>
        </div>
    </template>

    <!-- ============ TENANT SETTINGS ============ -->
    <template x-if="type==='tenant-settings'">
        <div class="grid" style="grid-template-columns:1fr 1fr">
            <div class="glass panel">
                <div class="panel-head"><span class="panel-title">Profil ISP &amp; Branding</span></div>
                <label class="lbl">Nama ISP</label><input class="field" x-model="settings.isp_name" style="margin-bottom:12px">
                <label class="lbl">Email</label><input class="field" x-model="settings.email" style="margin-bottom:12px">
                <label class="lbl">No WhatsApp</label><input class="field" x-model="settings.phone_wa" style="margin-bottom:12px">
                <label class="lbl">Alamat</label><input class="field" x-model="settings.address" style="margin-bottom:12px">
                <label class="lbl">Logo URL</label><input class="field" x-model="settings.logo_url">
            </div>
            <div class="glass panel">
                <div class="panel-head"><span class="panel-title">Integrasi</span></div>
                <label class="lbl">SMTP Host</label><input class="field" x-model="settings.smtp_config.host" style="margin-bottom:12px">
                <label class="lbl">SMTP User</label><input class="field" x-model="settings.smtp_config.user" style="margin-bottom:12px">
                <label class="lbl">WhatsApp Gateway URL</label><input class="field" x-model="settings.wa_config.url" style="margin-bottom:12px">
                <label class="lbl">WhatsApp Token</label><input class="field" x-model="settings.wa_config.token" style="margin-bottom:12px">
                <label class="lbl">Mikrotik API Host</label><input class="field" x-model="settings.mikrotik_api.host">
                <button class="btn btn-primary" style="margin-top:16px;width:100%;justify-content:center" @click="saveSettings()" data-testid="settings-save"><i class="fa-solid fa-floppy-disk"></i> Simpan Pengaturan</button>
            </div>
        </div>
    </template>

    <!-- ============ INTEGRATION INFO (SMTP/WA/System) ============ -->
    <template x-if="type==='integration'">
        <div class="glass panel">
            <div class="panel-head"><span class="panel-title" x-text="cfg.title"></span></div>
            <div class="alert-row" style="border:none"><div class="alert-ic ic-green"><i class="fa-solid fa-circle-info"></i></div>
                <div x-html="cfg.note" style="font-size:14px;line-height:1.7"></div>
            </div>
        </div>
    </template>

    <!-- ============ BACKUP ============ -->
    <template x-if="type==='backup'">
        <div class="glass panel" style="text-align:center;padding:50px">
            <i class="fa-solid fa-database" style="font-size:40px;color:var(--green)"></i>
            <h2 style="margin:14px 0 6px">Backup Otomatis</h2>
            <p style="color:var(--muted);max-width:480px;margin:0 auto 20px">Backup database dijalankan terjadwal melalui cron <span class="mono">php cron/scheduler.php cleanup</span> &amp; backup OLT/Mikrotik via API. Konfigurasi penuh ada di <span class="mono">docker-compose.yml</span>.</p>
            <button class="btn btn-primary" @click="runBackup()"><i class="fa-solid fa-play"></i> Jalankan Backup Sekarang</button>
            <div x-show="backupDone" class="chip active" style="margin-top:16px;display:inline-flex">Backup dijadwalkan</div>
        </div>
    </template>

    <!-- ============ ACS (TR-069) ============ -->
    <template x-if="type==='acs'">
        <div class="glass panel">
            <div class="panel-head"><span class="panel-title"><i class="fa-solid fa-satellite-dish text-green"></i> Perangkat ACS (TR-069)</span>
                <span class="pill" style="font-size:12px">ACS URL CPE: <span class="mono" x-text="location.origin + '/acs/' + (NV.user()?.tenant_id||'TENANT_ID')"></span></span>
            </div>
            <div style="overflow-x:auto">
                <table class="nv" data-testid="acs-table">
                    <thead><tr><th>Serial</th><th>Vendor</th><th>Model</th><th>IP</th><th>Status</th><th>Last Inform</th><th style="text-align:right">Remote Action</th></tr></thead>
                    <tbody>
                        <template x-for="d in acsDevices" :key="d.id">
                            <tr>
                                <td style="font-weight:600" x-text="d.serial"></td>
                                <td x-text="d.vendor || d.manufacturer || '-'"></td>
                                <td x-text="d.product_class || d.model || '-'"></td>
                                <td class="mono" x-text="d.ip_address || '-'"></td>
                                <td><span class="chip" :class="d.status" x-text="d.status"></span></td>
                                <td style="color:var(--muted);font-size:12.5px" x-text="NV.timeAgo(d.last_inform)"></td>
                                <td style="text-align:right">
                                    <div class="flex gap-2" style="justify-content:flex-end">
                                        <i class="fa-solid fa-circle-info icon-btn" style="width:30px;height:30px;font-size:11px" @click="acsShow(d)" title="Detail"></i>
                                        <i class="fa-solid fa-power-off icon-btn" style="width:30px;height:30px;font-size:11px;color:var(--amber)" @click="acsReboot(d)" title="Reboot"></i>
                                        <i class="fa-solid fa-wifi icon-btn" style="width:30px;height:30px;font-size:11px;color:var(--green)" @click="acsWifi(d)" title="WiFi Config"></i>
                                        <i class="fa-solid fa-ethernet icon-btn" style="width:30px;height:30px;font-size:11px;color:var(--blue)" @click="acsWan(d)" title="WAN/PPPoE"></i>
                                        <i class="fa-solid fa-download icon-btn" style="width:30px;height:30px;font-size:11px;color:var(--violet)" @click="acsFirmware(d)" title="Firmware"></i>
                                        <i class="fa-solid fa-arrows-rotate icon-btn" style="width:30px;height:30px;font-size:11px;color:var(--red)" @click="acsReset(d)" title="Factory Reset"></i>
                                    </div>
                                </td>
                            </tr>
                        </template>
                        <template x-if="acsDevices.length===0"><tr><td colspan="7" style="text-align:center;color:var(--muted);padding:30px">Belum ada CPE terhubung. Set ACS URL di atas pada perangkat ONU/router pelanggan (TR-069), perangkat akan muncul otomatis saat Inform.</td></tr></template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <!-- ACS detail modal -->
    <div x-show="acsModal" x-cloak style="position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:200;display:grid;place-items:center;padding:20px" @click.self="acsModal=false">
        <div class="glass panel" style="width:100%;max-width:640px;max-height:88vh;overflow:auto">
            <div class="panel-head"><span class="panel-title">Detail CPE — <span x-text="acsDetail?.serial"></span></span><i class="fa-solid fa-xmark" style="cursor:pointer;color:var(--muted)" @click="acsModal=false"></i></div>
            <div class="panel-title" style="font-size:12px;margin-bottom:8px">Parameters</div>
            <table class="nv" style="margin-bottom:18px"><tbody>
                <template x-for="p in (acsDetail?.parameters||[])" :key="p.name">
                    <tr><td class="mono" style="font-size:11px" x-text="p.name"></td><td x-text="p.value"></td></tr>
                </template>
                <template x-if="!(acsDetail?.parameters||[]).length"><tr><td style="color:var(--muted)">Belum ada parameter tersimpan.</td></tr></template>
            </tbody></table>
            <div class="panel-title" style="font-size:12px;margin-bottom:8px">Riwayat Task</div>
            <table class="nv"><tbody>
                <template x-for="t in (acsDetail?.tasks||[])" :key="t.id">
                    <tr><td x-text="t.type"></td><td><span class="chip" :class="t.status==='done'?'active':(t.status==='failed'?'offline':'warning')" x-text="t.status"></span></td><td style="color:var(--muted)" x-text="NV.timeAgo(t.created_at)"></td></tr>
                </template>
                <template x-if="!(acsDetail?.tasks||[]).length"><tr><td style="color:var(--muted)">Belum ada task.</td></tr></template>
            </tbody></table>
        </div>
    </div>

    <!-- ============ CRUD MODAL ============ -->
    <div x-show="modalOpen" x-cloak style="position:fixed;inset:0;background:rgba(0,0,0,0.6);backdrop-filter:blur(4px);z-index:200;display:grid;place-items:center;padding:20px" @click.self="modalOpen=false">
        <div class="glass panel" style="width:100%;max-width:560px;max-height:88vh;overflow:auto" data-testid="module-modal">
            <div class="panel-head"><span class="panel-title" x-text="(editing?'Edit ':'Tambah ')+(cfg?.title||'')"></span><i class="fa-solid fa-xmark" style="cursor:pointer;color:var(--muted)" @click="modalOpen=false"></i></div>
            <div class="grid" style="grid-template-columns:1fr 1fr">
                <template x-for="f in (cfg?.fields||[])" :key="f.key">
                    <div :style="f.type==='textarea' ? 'grid-column:1/3' : ''">
                        <label class="lbl" x-text="f.label + (f.required?' *':'')"></label>
                        <template x-if="f.type==='select'">
                            <select class="field" x-model="form[f.key]">
                                <option value="">- pilih -</option>
                                <template x-for="o in f.options" :key="o"><option :value="o" x-text="o"></option></template>
                            </select>
                        </template>
                        <template x-if="f.type==='textarea'"><textarea class="field" rows="3" x-model="form[f.key]"></textarea></template>
                        <template x-if="!['select','textarea'].includes(f.type)"><input :type="f.type" class="field" x-model="form[f.key]"></template>
                    </div>
                </template>
            </div>
            <div class="flex gap-3" style="margin-top:18px;justify-content:flex-end">
                <button class="btn btn-ghost" @click="modalOpen=false">Batal</button>
                <button class="btn btn-primary" @click="save()" data-testid="module-save"><i class="fa-solid fa-floppy-disk"></i> Simpan</button>
            </div>
        </div>
    </div>
</div>

<style>[x-cloak]{display:none!important}</style>
