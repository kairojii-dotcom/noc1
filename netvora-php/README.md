# NETVORA NOC &amp; ISP Management System

Sistem **NOC + ISP Management** multi-tenant, dark mode dominan hijau, realtime — dibangun dengan **PHP 8.3 Native (MVC, Clean Architecture)** + **Supabase PostgreSQL**.

Setara dengan Splynx / UISP / Sonar dalam satu platform modular: tambah vendor OLT, perangkat ACS, dan payment gateway **tanpa mengubah core**.

> ⚙️ **Stack**: PHP 8.3 Native MVC · Supabase PostgreSQL · JWT + Refresh Token + RBAC · Bootstrap 5 · AlpineJS · ApexCharts · Leaflet · Vis Network · DataTables · SNMP · RouterOS API · Nginx · Docker/Coolify · Cron.

---

## ✨ Arsitektur (Clean Architecture)

```
public/index.php           → Front controller (semua request masuk sini)
bootstrap.php              → Env, autoload (PSR-4), error handler

app/
├── Core/                  → Framework mini (Router, Database/PDO, Jwt, Request,
│                            Response, Middleware, Controller, View, Env, helpers)
├── Repositories/          → Repository Pattern (akses data, tenant-scoped)
├── Services/              → Service Layer (logika bisnis)
│   └── Monitoring/        → SnmpService, MikrotikApiService, OltOidService, PollerService
├── Controllers/
│   ├── Api/               → REST API controllers (JSON)
│   └── Web/               → SSR page controllers (HTML)
└── Views/                 → Template UI (Bootstrap + Alpine + ApexCharts)

routes/                    → api.php (REST) & web.php (halaman)
database/                  → migrations/*.sql + migrate.php + seed.php
storage/oid_templates/     → Template OID per-vendor OLT (JSON) — tambah vendor = tambah file
cron/scheduler.php         → Queue/worker (poll, billing, cleanup)
docker/ + Dockerfile + docker-compose.yml  → Deployment Coolify/Nginx
```

**Alur**: `Request → Router → Middleware (auth/RBAC/tenant/ratelimit) → Controller → Service → Repository → Database`.

---

## 🚀 Cara Menjalankan

### 1. Prasyarat
- PHP 8.3 dengan ekstensi: `pdo_pgsql`, `pgsql`, `snmp`, `mbstring`, `curl`, `openssl`
- Akun **Supabase** (atau PostgreSQL apa pun)
- (Opsional) Docker + Coolify untuk produksi

### 2. Konfigurasi
```bash
cp .env.example .env
# Edit .env → isi DB_HOST/DB_PASSWORD dari Supabase, JWT_SECRET, OPENAI_API_KEY
```
Ambil koneksi Supabase di: **Supabase Dashboard → Project Settings → Database → Connection string**.

### 3. Migrasi &amp; Seed
```bash
composer install            # (opsional — ada fallback autoloader bawaan)
php database/migrate.php     # buat tabel, FK, trigger, view, function, RLS
php database/seed.php        # buat akun Super Admin
```

### 4. Jalankan
```bash
# Development
php -S 0.0.0.0:8000 -t public

# Produksi → pakai Nginx + PHP-FPM (lihat docker-compose.yml)
```

Buka `http://localhost:8000` → login.

### 🔑 Kredensial awal (ganti setelah login!)
```
Super Admin → email: superadmin@netvora.com  password: Netvora#2026
```
Owner tenant dibuat otomatis saat Super Admin menambah tenant (email &amp; password ditampilkan sekali).

---

## 🏢 Multi-Tenant
- Super Admin membuat tenant **tanpa batas** (Domain, Logo, ISP, Paket, Status, Expired, Timezone, Branding, SMTP, WA Gateway, API Mikrotik, API ACS, Billing, Invoice Template).
- **Isolasi data total** via 2 lapis: scoping di Repository + **Row Level Security (RLS)** PostgreSQL (`app.current_tenant`).

## 👥 Role (RBAC)
`super_admin · owner · admin · noc · teknisi · cs · finance · marketing · readonly` — tiap menu punya permission CRUD (kolom `permissions` JSONB di tabel `roles`).

## 📦 Paket
**Basic · Professional · Enterprise** — fitur diatur lewat flag `features` (JSONB) di tabel `packages`. Frontend menyembunyikan/menampilkan menu sesuai paket tenant.

## 📡 Monitoring (real)
- **MikroTik**: RouterOS API native (`MikrotikApiService`) — CPU, memory, PPPoE active, interface, export/backup.
- **OLT**: SNMP via `SnmpService` + **template OID JSON** (`storage/oid_templates/`). Tambah vendor OLT baru cukup buat file `vendor.json` — **tanpa ubah core**.
- **Cron poller** (`cron/scheduler.php poll`) update status, simpan `device_metrics`, generate `alerts` (dedupe).

## 🤖 AI Analytics (Enterprise)
`POST /api/ai/analyze` (mode: `rca` | `predictive` | `capacity`) — Root Cause Analysis / Predictive Alarm / Capacity Planning via OpenAI atas telemetry tenant.

---

## 🔌 REST API (ringkas)

| Method | Endpoint | Keterangan |
|---|---|---|
| POST | `/api/auth/login` | Login → access + refresh token |
| POST | `/api/auth/refresh` | Rotasi refresh token |
| POST | `/api/auth/logout` | Cabut refresh token |
| GET  | `/api/auth/me` | Profil + permission |
| GET  | `/api/health` | Health check (DB) |
| GET  | `/api/superadmin/dashboard` | Statistik semua tenant *(super_admin)* |
| GET/POST/PUT/PATCH/DELETE | `/api/tenants[/{id}]` | CRUD tenant *(super_admin)* |
| GET  | `/api/dashboard` | Snapshot NOC tenant *(RLS)* |
| GET/POST/PUT/DELETE | `/api/{resource}[/{id}]` | CRUD `routers·olts·onus·customers·alerts·tickets` |
| POST | `/api/ai/analyze` | AI RCA / Predictive *(Enterprise)* |

Auth: header `Authorization: Bearer <access_token>`.

---

## 🐳 Deploy (Coolify / Docker)
```bash
docker compose up -d --build
```
- `app` (PHP-FPM) + `web` (Nginx) + `cron` (poll/billing/cleanup).
- Health check & auto-restart sudah dikonfigurasi. SSL diatur otomatis oleh Coolify.
- Database = Supabase eksternal (via `.env`).

---

## 🗄️ Database
Migrasi PostgreSQL lengkap dengan **Foreign Key, Trigger (`set_updated_at`), View (`v_superadmin_stats`, `v_tenant_top`), Function (`tenant_dashboard`), Row Level Security**, dan tabel timeseries `device_metrics`.

---

## 🧩 Cara menambah Vendor OLT baru (tanpa ubah core)
1. Buat `storage/oid_templates/namavendor.json` (lihat `huawei.json` sebagai contoh).
2. Selesai — vendor langsung tersedia di poller &amp; menu OLT.

---

© NETVORA NOC — PHP 8.3 · Supabase · Clean Architecture · Multi-Tenant.
