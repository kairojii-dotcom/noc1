-- =====================================================================
-- 002 SEED — Roles (RBAC) & Packages (Basic/Professional/Enterprise)
-- Idempotent: safe to run multiple times.
-- =====================================================================

INSERT INTO roles (code, name, permissions, is_system) VALUES
 ('super_admin','Super Admin','["*"]', true),
 ('owner','Owner','["*"]', true),
 ('admin','Admin','["dashboard.view","router.*","olt.*","onu.*","odp.*","customer.*","ticket.*","alert.*","user.view","setting.*"]', true),
 ('noc','NOC','["dashboard.view","router.view","olt.view","onu.view","odp.view","alert.*","ticket.*","topology.*","maps.view"]', true),
 ('teknisi','Teknisi','["dashboard.view","onu.view","odp.view","ticket.view","ticket.update","customer.view","maps.view"]', true),
 ('cs','Customer Service','["dashboard.view","customer.*","ticket.*"]', true),
 ('finance','Finance','["dashboard.view","billing.*","invoice.*","payment.*","customer.view","report.view"]', true),
 ('marketing','Marketing','["dashboard.view","crm.*","customer.view","report.view"]', true),
 ('readonly','Read Only','["dashboard.view","router.view","olt.view","onu.view","customer.view","report.view"]', true)
ON CONFLICT (code) DO UPDATE SET name=EXCLUDED.name, permissions=EXCLUDED.permissions;

INSERT INTO packages (code, name, price, max_routers, max_customers, features) VALUES
 ('basic','Basic',150000,0,0,
   '["dashboard","router","olt","onu","odp","traffic","customer","maps","alert","ticket","logs","users","setting"]'),
 ('professional','Professional',500000,0,0,
   '["dashboard","router","olt","onu","odp","traffic","customer","maps","alert","ticket","logs","users","setting","billing","invoice","payment","subscription","pppoe","hotspot","crm","whatsapp","smtp","inventory","backup","api","webhook","customer_portal","radius","monitoring_history"]'),
 ('enterprise','Enterprise',1500000,0,0,
   '["dashboard","router","olt","onu","odp","traffic","customer","maps","alert","ticket","logs","users","setting","billing","invoice","payment","subscription","pppoe","hotspot","crm","whatsapp","smtp","inventory","backup","api","webhook","customer_portal","radius","monitoring_history","acs","auto_provision","ztp","topology_editor","gis_maps","multi_pop","ha","cluster_monitoring","syslog","netflow","snmp_trap","grafana","prometheus","sso","audit_full","ai_analytics","capacity_planning","predictive_alarm","ai_rca"]')
ON CONFLICT (code) DO UPDATE SET name=EXCLUDED.name, price=EXCLUDED.price, features=EXCLUDED.features;
