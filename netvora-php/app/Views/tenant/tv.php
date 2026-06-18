<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NOC TV Mode — NETVORA</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="/assets/css/app.css">
    <style>body{overflow:hidden}.tv-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:18px;padding:24px}.tv-stat .stat-value{font-size:42px}</style>
</head>
<body x-data="tv()" x-init="init()">
    <div class="topbar" style="padding:24px 24px 0">
        <div class="flex items-center gap-3">
            <div class="brand-logo" style="width:44px;height:44px"><i class="fa-solid fa-circle-nodes"></i></div>
            <div><div class="brand-name" style="font-size:20px">NETVORA<span> NOC</span></div><div class="sub">Network Operations Center — Live</div></div>
        </div>
        <div class="pill" style="font-size:18px"><i class="fa-solid fa-circle text-green" style="font-size:9px"></i> <span x-text="clock"></span></div>
    </div>
    <div class="tv-grid">
        <template x-for="c in cards" :key="c.label">
            <div class="stat-card glass tv-stat reveal">
                <div class="stat-top"><span class="stat-label" x-text="c.label"></span><div class="stat-icon" :class="c.ic"><i class="fa-solid" :class="c.icon"></i></div></div>
                <div class="stat-value" x-text="c.value"></div>
                <div class="stat-meta"><span class="up" x-text="c.a"></span><span class="down" x-text="c.b"></span></div>
            </div>
        </template>
    </div>
    <div style="padding:0 24px"><div class="glass panel" style="height:48vh"><div class="panel-head"><span class="panel-title">Traffic Realtime</span></div><div id="cTV"></div></div></div>

<script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.14.1/dist/cdn.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.1/dist/apexcharts.min.js"></script>
<script src="/assets/js/app.js"></script>
<script>
function tv(){return{cards:[],clock:'',chart:null,
 async init(){ if(!NV.guard())return; this.tick(); setInterval(()=>this.tick(),1000); await this.load(); setInterval(()=>this.load(),10000); },
 tick(){ this.clock=new Date().toLocaleTimeString('id-ID'); },
 async load(){ try{ const {data}=await NV.api('/dashboard'); const s=data.snapshot;
   this.cards=[
     {label:'Router Online',value:NV.fmt(s.routers_online)+'/'+NV.fmt(s.routers),icon:'fa-network-wired',ic:'ic-green',a:'',b:''},
     {label:'OLT Online',value:NV.fmt(s.olts_online)+'/'+NV.fmt(s.olts),icon:'fa-server',ic:'ic-violet',a:'',b:''},
     {label:'ONU Online',value:NV.fmt(s.onus_online)+'/'+NV.fmt(s.onus),icon:'fa-hard-drive',ic:'ic-blue',a:'',b:''},
     {label:'Pelanggan Aktif',value:NV.fmt(s.customers_active),icon:'fa-users',ic:'ic-green',a:'',b:''},
     {label:'Alert Aktif',value:NV.fmt(s.alerts),icon:'fa-bell',ic:'ic-red',a:'',b:''},
     {label:'Tiket Open',value:NV.fmt(s.tickets_open),icon:'fa-ticket',ic:'ic-amber',a:'',b:''},
     {label:'ODP',value:NV.fmt(s.odps),icon:'fa-box-archive',ic:'ic-blue',a:'',b:''},
     {label:'Total Pelanggan',value:NV.fmt(s.customers),icon:'fa-user',ic:'ic-violet',a:'',b:''},
   ];
   const dl=data.traffic.download||[],ul=data.traffic.upload||[];
   const opt=NV_CHART.area(dl.map(x=>x.bucket),[{name:'Download',data:dl.map(x=>+x.value)},{name:'Upload',data:ul.map(x=>+x.value)}],['#38bdf8','#16f08f']);
   opt.chart.height='40vh'; if(this.chart){try{this.chart.destroy()}catch{}} this.chart=new ApexCharts(document.querySelector('#cTV'),opt); this.chart.render();
 }catch(e){} }
}}
</script>
</body>
</html>
