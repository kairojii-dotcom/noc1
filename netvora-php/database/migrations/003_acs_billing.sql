-- =====================================================================
-- 003 — ACS (TR-069) + Billing enhancements
-- =====================================================================

-- ---------- Billing: link invoice to subscription ----------
ALTER TABLE invoices ADD COLUMN IF NOT EXISTS subscription_id UUID REFERENCES subscriptions(id) ON DELETE SET NULL;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS gateway_payload JSONB NOT NULL DEFAULT '{}'::jsonb;
ALTER TABLE payments ADD COLUMN IF NOT EXISTS external_id TEXT;

-- ---------- ACS: managed CPE devices (TR-069 / CWMP) ----------
CREATE TABLE IF NOT EXISTS acs_devices (
    id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id     UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    serial        TEXT NOT NULL,
    oui           TEXT,
    product_class TEXT,
    manufacturer  TEXT,
    model         TEXT,
    vendor        TEXT,                  -- huawei, zte, fiberhome, nokia, tplink, vsol, raisecom, dasan
    ip_address    INET,
    software_version TEXT,
    hardware_version TEXT,
    connection_request_url TEXT,
    onu_id        UUID REFERENCES onus(id) ON DELETE SET NULL,
    status        TEXT NOT NULL DEFAULT 'offline' CHECK (status IN ('online','offline')),
    last_inform   TIMESTAMPTZ,
    tags          JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    updated_at    TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (tenant_id, serial)
);
CREATE TRIGGER trg_acs_devices_updated BEFORE UPDATE ON acs_devices
    FOR EACH ROW EXECUTE FUNCTION set_updated_at();
CREATE INDEX IF NOT EXISTS idx_acs_devices_tenant ON acs_devices(tenant_id, status);
CREATE INDEX IF NOT EXISTS idx_acs_devices_serial ON acs_devices(serial);

-- ---------- ACS: stored CPE parameters (TR-069 data model) ----------
CREATE TABLE IF NOT EXISTS acs_parameters (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    device_id   UUID NOT NULL REFERENCES acs_devices(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    value       TEXT,
    writable    BOOLEAN NOT NULL DEFAULT false,
    updated_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    UNIQUE (device_id, name)
);
CREATE INDEX IF NOT EXISTS idx_acs_params_device ON acs_parameters(device_id);

-- ---------- ACS: task queue (reboot, set-param, provision, firmware) ----------
CREATE TABLE IF NOT EXISTS acs_tasks (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID NOT NULL REFERENCES tenants(id) ON DELETE CASCADE,
    device_id   UUID NOT NULL REFERENCES acs_devices(id) ON DELETE CASCADE,
    type        TEXT NOT NULL CHECK (type IN ('reboot','factory_reset','set_param','get_param','download','wifi_config','wan_config')),
    payload     JSONB NOT NULL DEFAULT '{}'::jsonb,
    status      TEXT NOT NULL DEFAULT 'pending' CHECK (status IN ('pending','sent','done','failed')),
    result      JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now(),
    executed_at TIMESTAMPTZ
);
CREATE INDEX IF NOT EXISTS idx_acs_tasks_device ON acs_tasks(device_id, status);

-- ---------- RLS for ACS tables ----------
DO $$
DECLARE t TEXT;
BEGIN
  FOREACH t IN ARRAY ARRAY['acs_devices','acs_parameters','acs_tasks'] LOOP
    EXECUTE format('ALTER TABLE %I ENABLE ROW LEVEL SECURITY;', t);
    BEGIN
      EXECUTE format($p$
        CREATE POLICY tenant_isolation ON %I
        USING (
          current_setting('app.current_role', true) = 'super_admin'
          OR tenant_id::text = current_setting('app.current_tenant', true)
        );
      $p$, t);
    EXCEPTION WHEN duplicate_object THEN NULL;
    END;
  END LOOP;
END $$;

-- ---------- Provisioning profile templates per vendor (auto-provision) ----------
CREATE TABLE IF NOT EXISTS acs_provision_profiles (
    id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
    tenant_id   UUID REFERENCES tenants(id) ON DELETE CASCADE,
    name        TEXT NOT NULL,
    vendor      TEXT,
    parameters  JSONB NOT NULL DEFAULT '[]'::jsonb,   -- [{name,value,type}]
    created_at  TIMESTAMPTZ NOT NULL DEFAULT now()
);
