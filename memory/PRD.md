# NETVORA NOC & ISP Management System — PRD

## Original Problem Statement
Bangun NETVORA NOC & ISP Management System: multi-tenant, dark mode dominan hijau, realtime,
clean architecture. Stack diminta user: **PHP 8.3 Native MVC + Supabase PostgreSQL** (Opsi B —
source code untuk di-host sendiri). UI mengacu pada 2 screenshot referensi (Super Admin Dashboard
& Tenant NOC Monitoring Dashboard).

## ⚠️ Platform Note
Emergent hanya bisa run/preview/deploy React+FastAPI+MongoDB. User memilih **Opsi B**: dikirim
sebagai **source code PHP/Supabase** (di-host sendiri). Divalidasi lokal dengan PostgreSQL 15
(end-to-end works). Tidak menggunakan stack platform.

## Deliverable
- Lokasi source: `/app/netvora-php/` (64 file)
- Paket zip: `/app/netvora-noc-php.zip`

## Arsitektur
PHP 8.3 Native MVC, Clean Architecture: Front Controller → Router → Middleware (auth/RBAC/tenant/
ratelimit) → Controller (Api+Web) → Service Layer → Repository Pattern → PDO PostgreSQL.
JWT + Refresh Token (rotasi) + RBAC. Multi-tenant isolation (repo scoping + RLS).

## Implemented (2026-06-18)
- ✅ Core framework: Router, Database(PDO/Supabase), Jwt, Request/Response, Middleware, View, Env
- ✅ Auth: login/refresh/logout/me, bcrypt, refresh-token rotation, audit log
- ✅ Multi-tenant: CRUD tenant + auto-create owner, RLS PostgreSQL, 9 roles RBAC, 3 paket (features JSONB)
- ✅ Super Admin Dashboard (stat cards, tabel tenant, Top 5, donut status/paket, growth) — match ref img 2
- ✅ Tenant NOC Dashboard (stat cards, donut router/OLT, Leaflet map, traffic/loss chart, topology Vis, alert, critical devices) — match ref img 1
- ✅ TV Mode (/tv)
- ✅ Generic resource CRUD: routers, olts, onus, customers, alerts, tickets
- ✅ Monitoring: SnmpService, MikrotikApiService (RouterOS native), OltOidService (template JSON per-vendor), PollerService
- ✅ AI Analytics (RCA/Predictive/Capacity via OpenAI)
- ✅ Cron scheduler (poll/billing/cleanup)
- ✅ DB: migrations, FK, trigger, view, function, RLS, device_metrics, topology
- ✅ Deployment: Dockerfile, docker-compose (app+web+cron), nginx.conf, health check
- ✅ UI: dark emerald glass theme (Sora/Outfit/JetBrains Mono), Bootstrap5+Alpine+ApexCharts+Leaflet+Vis+DataTables

## Validated (local Postgres 15)
- Login super admin & owner (domain-scoped) ✓
- Super admin dashboard stats + tenant list ✓
- Tenant create → owner auto-created ✓
- Resource CRUD + **RLS isolation** (tenant B tidak melihat data tenant A) ✓
- All 64 PHP files lint-clean ✓
- UI screenshots verified (login, super admin, tenant NOC) ✓

## Backlog (P1/P2 — modul lanjutan)
- P1: Billing penuh (Midtrans/Xendit/Tripay, mutasi bank, rekonsiliasi), Inventory, Ticket workflow + foto/signature
- P1: ACS TR-069 (auto-provision, firmware, WiFi/WAN config) untuk Enterprise
- P1: OLT/Mikrotik auto-discovery & import (interface/queue/PPPoE/board/PON/ODP)
- P2: Topology editor (drag-drop save), GIS maps polygon/heatmap, Customer Portal, Radius, Hotspot
- P2: Swagger/OpenAPI, Webhook marketplace, SSO, Grafana/Prometheus export, Syslog/Netflow/SNMP Trap
- P2: Supabase Realtime channel binding (saat ini polling 15s)

## Update 2026-06-18 (iterasi 2) — Semua menu fungsional
- ✅ Module engine generik (`modules.js` + `engine.js` + `module.php`): semua menu sidebar bisa dibuka & berfungsi
- ✅ Super Admin: Paket, Semua User, Role Permission, Audit Log, Subscription, Invoice, Payment (CRUD `/api/admin/{resource}`), Monitoring, Backup, SMTP/WA/System (info)
- ✅ Tenant NOC: Routers, OLT, ONU, ODP, Pelanggan, Alerts, Tiket, Users, Logs (CRUD), Traffic (charts), Maps (Leaflet), Topologi (Vis editor + save), AI Analytics, Laporan (export CSV), Settings (profile/SMTP/WA)
- ✅ Endpoint baru: `AdminResourceController` (global), `TenantSettingsController`, `TopologyController`
- ✅ Sidebar real URL + active-state; routing fix (API didahulukan dari web catch-all)
- ✅ Divalidasi: semua 26 endpoint HTTP 200 + 7 halaman web 200 + screenshot (Routers CRUD, Topology editor, Semua User)

## Next Tasks
1. User host di Supabase + server PHP (ikuti README).
2. Implement modul Billing gateway nyata (Midtrans/Xendit) & ACS TR-069 sesuai prioritas paket.
