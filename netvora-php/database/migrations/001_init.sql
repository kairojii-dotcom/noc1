-- =====================================================================
-- NETVORA NOC & ISP — 001 INIT SCHEMA (Supabase PostgreSQL)
-- Includes: tables, foreign keys, triggers, views, functions, RLS
-- =====================================================================

CREATE EXTENSION IF NOT EXISTS "pgcrypto";   -- gen_random_uuid()

-- ---------------------------------------------------------------------
-- Helper: auto-update updated_at
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION set_updated_at()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = now();
    RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- ---------------------------------------------------------------------
-- PACKAGES (Basic / Professional / Enterprise feature flags)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS packages (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code        TEXT UNIQUE NOT NULL CHECK (code IN ('basic','professional','enterprise')),
    name        TEXT NOT NULL,
    price       NUMERIC(14,2) NOT NULL DEFAULT 0,
    features    JSONB NOT NULL DEFAULT '[]'::jsonb,
    max_routers INT NOT NULL DEFAULT 0,    -- 0 = unlimited
    max_customers INT NOT NULL DEFAULT 0,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- TENANTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tenants (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    name          TEXT NOT NULL,
    domain        TEXT UNIQUE NOT NULL,
    logo_url      TEXT,
    isp_name      TEXT,
    address       TEXT,
    phone_wa      TEXT,
    email         TEXT,
    package_id    UUID REFERENCES packages(id) ON DELETE SET NULL,
    status        TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','suspend','expired')),
    expired_at    DATE,
    timezone      TEXT NOT NULL DEFAULT 'Asia/Jakarta',
    branding      JSONB NOT NULL DEFAULT '{}'::jsonb,  -- colors, theme
    smtp_config   JSONB NOT NULL DEFAULT '{}'::jsonb,
    wa_config     JSONB NOT NULL DEFAULT '{}'::jsonb,
    mikrotik_api  JSONB NOT NULL DEFAULT '{}'::jsonb,
    acs_api       JSONB NOT NULL DEFAULT '{}'::jsonb,
    billing_config JSONB NOT NULL DEFAULT '{}'::jsonb,
    invoice_template TEXT,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TRIGGER trg_tenants_updated BEFORE UPDATE ON tenants
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE INDEX IF NOT EXISTS idx_tenants_status ON tenants(status);

-- ---------------------------------------------------------------------
-- ROLES (RBAC) — global definitions + permission matrix
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS roles (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    code        TEXT UNIQUE NOT NULL,  -- super_admin, owner, admin, noc, teknisi, cs, finance, marketing, readonly
    name        TEXT NOT NULL,
    permissions JSONB NOT NULL DEFAULT '[]'::jsonb,
    is_system   BOOLEAN NOT NULL DEFAULT true,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- USERS  (super_admin => tenant_id NULL)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id     UUID REFERENCES tenants(id) ON DELETE CASCADE,
    role_code     TEXT NOT NULL REFERENCES roles(code),
    name          TEXT NOT NULL,
    email         TEXT NOT NULL,
    password_hash TEXT NOT NULL,
    avatar_url    TEXT,
    phone         TEXT,
    is_active     BOOLEAN NOT NULL DEFAULT true,
    last_login_at TIMESTAMPTZ,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (tenant_id, email)
);
CREATE TRIGGER trg_users_updated BEFORE UPDATE ON users
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE INDEX IF NOT EXISTS idx_users_tenant ON users(tenant_id);

-- ---------------------------------------------------------------------
-- REFRESH TOKENS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS refresh_tokens (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    user_id     UUID NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    token_hash  TEXT NOT NULL,
    expires_at  TIMESTAMPTZ NOT NULL,
    revoked     BOOLEAN NOT NULL DEFAULT false,
    user_agent  TEXT,
    ip_address  TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_refresh_user ON refresh_tokens(user_id);

-- ---------------------------------------------------------------------
-- AUDIT LOG
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS audit_logs (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID REFERENCES tenants(id) ON DELETE CASCADE,
    user_id     UUID REFERENCES users(id) ON DELETE SET NULL,
    action      TEXT NOT NULL,
    entity      TEXT,
    entity_id   TEXT,
    meta        JSONB NOT NULL DEFAULT '{}'::jsonb,
    ip_address  TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_audit_tenant ON audit_logs(tenant_id, created_at DESC);

-- ---------------------------------------------------------------------
-- NETWORK: ROUTERS (Mikrotik), OLTs, ONUs, ODPs
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS routers (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    ip_address  INET NOT NULL,
    api_port    INT NOT NULL DEFAULT 8728,
    ssh_port    INT NOT NULL DEFAULT 22,
    snmp_community TEXT DEFAULT 'public',
    username    TEXT,
    password_enc TEXT,
    model       TEXT,
    location    TEXT,
    latitude    NUMERIC(10,7),
    longitude   NUMERIC(10,7),
    status      TEXT NOT NULL DEFAULT 'unknown' CHECK (status IN ('online','offline','unknown')),
    cpu_load    INT DEFAULT 0,
    mem_usage   INT DEFAULT 0,
    uptime_sec  BIGINT DEFAULT 0,
    last_seen   TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TRIGGER trg_routers_updated BEFORE UPDATE ON routers
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE INDEX IF NOT EXISTS idx_routers_tenant ON routers(tenant_id, status);

CREATE TABLE IF NOT EXISTS olts (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    vendor      TEXT NOT NULL,  -- huawei, zte, vsol, fiberhome, bdcom, cdata, raisecom, nokia
    ip_address  INET NOT NULL,
    snmp_community TEXT DEFAULT 'public',
    telnet_port INT DEFAULT 23,
    ssh_port    INT DEFAULT 22,
    location    TEXT,
    latitude    NUMERIC(10,7),
    longitude   NUMERIC(10,7),
    status      TEXT NOT NULL DEFAULT 'unknown' CHECK (status IN ('online','offline','unknown')),
    temperature NUMERIC(5,2),
    pon_ports   INT DEFAULT 0,
    last_seen   TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TRIGGER trg_olts_updated BEFORE UPDATE ON olts
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE INDEX IF NOT EXISTS idx_olts_tenant ON olts(tenant_id, status);

CREATE TABLE IF NOT EXISTS odps (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    olt_id      UUID REFERENCES olts(id) ON DELETE SET NULL,
    name        TEXT NOT NULL,
    pon         TEXT,
    latitude    NUMERIC(10,7),
    longitude   NUMERIC(10,7),
    photo_url   TEXT,
    capacity    INT NOT NULL DEFAULT 8,
    used_port   INT NOT NULL DEFAULT 0,
    splitter    TEXT,
    status      TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','full','maintenance','down')),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TRIGGER trg_odps_updated BEFORE UPDATE ON odps
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE INDEX IF NOT EXISTS idx_odps_tenant ON odps(tenant_id);

CREATE TABLE IF NOT EXISTS onus (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    olt_id      UUID REFERENCES olts(id) ON DELETE SET NULL,
    odp_id      UUID REFERENCES odps(id) ON DELETE SET NULL,
    serial      TEXT NOT NULL,
    mac         TEXT,
    name        TEXT,
    pon_port    TEXT,
    rx_power    NUMERIC(6,2),
    tx_power    NUMERIC(6,2),
    distance_m  INT,
    temperature NUMERIC(5,2),
    firmware    TEXT,
    status      TEXT NOT NULL DEFAULT 'unknown' CHECK (status IN ('online','offline','los','unknown')),
    uptime_sec  BIGINT DEFAULT 0,
    last_seen   TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TRIGGER trg_onus_updated BEFORE UPDATE ON onus
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE INDEX IF NOT EXISTS idx_onus_tenant ON onus(tenant_id, status);

-- ---------------------------------------------------------------------
-- CUSTOMERS (PPPoE / ONU / Hybrid)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS customers (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    code        TEXT,
    name        TEXT NOT NULL,
    phone       TEXT,
    email       TEXT,
    address     TEXT,
    latitude    NUMERIC(10,7),
    longitude   NUMERIC(10,7),
    conn_type   TEXT NOT NULL DEFAULT 'pppoe' CHECK (conn_type IN ('pppoe','onu','hybrid')),
    pppoe_user  TEXT,
    onu_id      UUID REFERENCES onus(id) ON DELETE SET NULL,
    router_id   UUID REFERENCES routers(id) ON DELETE SET NULL,
    package_name TEXT,
    monthly_fee NUMERIC(14,2) DEFAULT 0,
    status      TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','suspend','expired','isolir')),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TRIGGER trg_customers_updated BEFORE UPDATE ON customers
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE INDEX IF NOT EXISTS idx_customers_tenant ON customers(tenant_id, status);

-- ---------------------------------------------------------------------
-- TICKETS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS tickets (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    customer_id UUID REFERENCES customers(id) ON DELETE SET NULL,
    assigned_to UUID REFERENCES users(id) ON DELETE SET NULL,
    subject     TEXT NOT NULL,
    category    TEXT,
    priority    TEXT NOT NULL DEFAULT 'medium' CHECK (priority IN ('low','medium','high','critical')),
    status      TEXT NOT NULL DEFAULT 'open' CHECK (status IN ('open','in_progress','resolved','closed')),
    description TEXT,
    meta        JSONB NOT NULL DEFAULT '{}'::jsonb,  -- photos, signature, location
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TRIGGER trg_tickets_updated BEFORE UPDATE ON tickets
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE INDEX IF NOT EXISTS idx_tickets_tenant ON tickets(tenant_id, status);

-- ---------------------------------------------------------------------
-- ALERTS
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS alerts (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    severity    TEXT NOT NULL DEFAULT 'warning' CHECK (severity IN ('info','warning','critical')),
    type        TEXT NOT NULL,   -- cpu, ram, disk, loss, temperature, onu_offline, router_down, ...
    source      TEXT,            -- device name
    message     TEXT NOT NULL,
    is_resolved BOOLEAN NOT NULL DEFAULT false,
    resolved_at TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_alerts_tenant ON alerts(tenant_id, created_at DESC);

-- ---------------------------------------------------------------------
-- BILLING: subscriptions, invoices, payments
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS subscriptions (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    customer_id UUID NOT NULL REFERENCES customers(id) ON DELETE CASCADE,
    package_name TEXT,
    amount      NUMERIC(14,2) NOT NULL DEFAULT 0,
    cycle       TEXT NOT NULL DEFAULT 'monthly',
    next_due    DATE,
    status      TEXT NOT NULL DEFAULT 'active' CHECK (status IN ('active','paused','cancelled')),
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_subs_tenant ON subscriptions(tenant_id);

CREATE TABLE IF NOT EXISTS invoices (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    customer_id UUID REFERENCES customers(id) ON DELETE SET NULL,
    number      TEXT NOT NULL,
    amount      NUMERIC(14,2) NOT NULL DEFAULT 0,
    tax         NUMERIC(14,2) NOT NULL DEFAULT 0,
    discount    NUMERIC(14,2) NOT NULL DEFAULT 0,
    total       NUMERIC(14,2) NOT NULL DEFAULT 0,
    status      TEXT NOT NULL DEFAULT 'unpaid' CHECK (status IN ('unpaid','paid','overdue','void')),
    due_date    DATE,
    paid_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_invoices_tenant ON invoices(tenant_id, status);

CREATE TABLE IF NOT EXISTS payments (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    invoice_id  UUID REFERENCES invoices(id) ON DELETE SET NULL,
    amount      NUMERIC(14,2) NOT NULL DEFAULT 0,
    method      TEXT,   -- midtrans, xendit, tripay, manual, bca, bni, mandiri
    reference   TEXT,
    status      TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','success','failed')),
    paid_at     TIMESTAMPTZ,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_payments_tenant ON payments(tenant_id, status);

-- ---------------------------------------------------------------------
-- DEVICE METRICS (timeseries — traffic, loss, cpu, mem)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS device_metrics (
    id          BIGSERIAL PRIMARY KEY,
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    device_type TEXT NOT NULL,    -- router, olt, onu
    device_id   UUID NOT NULL,
    metric      TEXT NOT NULL,    -- rx_bps, tx_bps, loss_pct, cpu, mem
    value       NUMERIC(18,4) NOT NULL,
    ts          TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS idx_metrics_lookup ON device_metrics(tenant_id, device_type, metric, ts DESC);

-- ---------------------------------------------------------------------
-- TOPOLOGY (Vis Network — nodes & edges)
-- ---------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS topology_nodes (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    label       TEXT NOT NULL,
    node_type   TEXT NOT NULL,  -- router, switch, olt, odp, splitter, onu, server, internet, cloud
    icon        TEXT,
    x           NUMERIC,
    y           NUMERIC,
    meta        JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
CREATE TABLE IF NOT EXISTS topology_edges (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    from_node   UUID NOT NULL REFERENCES topology_nodes(id) ON DELETE CASCADE,
    to_node     UUID NOT NULL REFERENCES topology_nodes(id) ON DELETE CASCADE,
    color       TEXT DEFAULT '#10b981',
    label       TEXT,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);

-- ---------------------------------------------------------------------
-- VIEWS — Super Admin & Tenant dashboard aggregates
-- ---------------------------------------------------------------------
CREATE OR REPLACE VIEW v_superadmin_stats AS
SELECT
    (SELECT count(*) FROM tenants)                              AS total_tenants,
    (SELECT count(*) FROM tenants WHERE status='active')        AS active_tenants,
    (SELECT count(*) FROM tenants WHERE status='suspend')       AS suspend_tenants,
    (SELECT count(*) FROM tenants WHERE status='expired')       AS expired_tenants,
    (SELECT count(*) FROM users)                                AS total_users,
    (SELECT count(*) FROM routers)                              AS total_routers,
    (SELECT count(*) FROM olts)                                 AS total_olts,
    (SELECT count(*) FROM onus)                                 AS total_onus,
    (SELECT count(*) FROM odps)                                 AS total_odps,
    (SELECT count(*) FROM customers)                            AS total_customers,
    (SELECT count(*) FROM tickets WHERE status IN ('open','in_progress')) AS open_tickets,
    (SELECT count(*) FROM invoices WHERE status='unpaid')       AS pending_payments,
    (SELECT COALESCE(sum(amount),0) FROM subscriptions WHERE status='active') AS mrr,
    (SELECT COALESCE(sum(total),0) FROM invoices
       WHERE status='paid' AND date_trunc('month',paid_at)=date_trunc('month',now())) AS revenue_month,
    (SELECT COALESCE(sum(total),0) FROM invoices
       WHERE status='paid' AND date_trunc('year',paid_at)=date_trunc('year',now()))  AS revenue_year;

CREATE OR REPLACE VIEW v_tenant_top AS
SELECT t.id, t.name, t.domain, p.code AS package, t.status, t.expired_at,
       (SELECT count(*) FROM customers c WHERE c.tenant_id=t.id) AS customer_count
FROM tenants t
LEFT JOIN packages p ON p.id=t.package_id
ORDER BY customer_count DESC;

-- ---------------------------------------------------------------------
-- FUNCTION — per-tenant NOC dashboard snapshot
-- ---------------------------------------------------------------------
CREATE OR REPLACE FUNCTION tenant_dashboard(p_tenant UUID)
RETURNS JSONB AS $$
DECLARE result JSONB;
BEGIN
    SELECT jsonb_build_object(
        'routers',        (SELECT count(*) FROM routers WHERE tenant_id=p_tenant),
        'routers_online', (SELECT count(*) FROM routers WHERE tenant_id=p_tenant AND status='online'),
        'olts',           (SELECT count(*) FROM olts WHERE tenant_id=p_tenant),
        'olts_online',    (SELECT count(*) FROM olts WHERE tenant_id=p_tenant AND status='online'),
        'onus',           (SELECT count(*) FROM onus WHERE tenant_id=p_tenant),
        'onus_online',    (SELECT count(*) FROM onus WHERE tenant_id=p_tenant AND status='online'),
        'odps',           (SELECT count(*) FROM odps WHERE tenant_id=p_tenant),
        'customers',      (SELECT count(*) FROM customers WHERE tenant_id=p_tenant),
        'customers_active',(SELECT count(*) FROM customers WHERE tenant_id=p_tenant AND status='active'),
        'tickets_open',   (SELECT count(*) FROM tickets WHERE tenant_id=p_tenant AND status IN ('open','in_progress')),
        'alerts',         (SELECT count(*) FROM alerts WHERE tenant_id=p_tenant AND is_resolved=false)
    ) INTO result;
    RETURN result;
END;
$$ LANGUAGE plpgsql STABLE;

-- ---------------------------------------------------------------------
-- ROW LEVEL SECURITY — strict tenant isolation
-- app.current_tenant / app.current_role set by the application per request
-- ---------------------------------------------------------------------
DO $$
DECLARE t TEXT;
BEGIN
  FOREACH t IN ARRAY ARRAY[
    'routers','olts','onus','odps','customers','tickets','alerts',
    'subscriptions','invoices','payments','device_metrics',
    'topology_nodes','topology_edges','audit_logs'
  ] LOOP
    EXECUTE format('ALTER TABLE %I ENABLE ROW LEVEL SECURITY;', t);
    EXECUTE format($p$
      CREATE POLICY tenant_isolation ON %I
      USING (
        current_setting('app.current_role', true) = 'super_admin'
        OR tenant_id::text = current_setting('app.current_tenant', true)
      );
    $p$, t);
  END LOOP;
END $$;
