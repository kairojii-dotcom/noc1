<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

use App\Core\Database;

/**
 * Polls every tenant's devices and writes live status, metrics & alerts.
 * Invoked by cron/scheduler.php (e.g. every minute).
 */
final class PollerService
{
    public function __construct(private OltOidService $oids = new OltOidService())
    {
    }

    public function run(): array
    {
        $summary = ['routers' => 0, 'olts' => 0, 'onus' => 0, 'alerts' => 0];

        foreach (Database::select("SELECT id FROM tenants WHERE status='active'") as $t) {
            $tenantId = $t['id'];
            $summary['routers'] += $this->pollRouters($tenantId, $summary);
            $summary['olts']    += $this->pollOlts($tenantId, $summary);
        }
        return $summary;
    }

    private function pollRouters(string $tenantId, array &$summary): int
    {
        $count = 0;
        $routers = Database::select(
            "SELECT * FROM routers WHERE tenant_id=:t", [':t' => $tenantId]
        );
        foreach ($routers as $r) {
            $count++;
            try {
                $api = new MikrotikApiService(
                    (string) $r['ip_address'],
                    (string) $r['username'],
                    (string) ($r['password_enc'] ?? ''),
                    (int) $r['api_port']
                );
                $ok = $api->connect();
                $res = $ok ? $api->systemResource() : [];
                $api->close();

                $cpu = (int) ($res['cpu-load'] ?? 0);
                $mem = $this->memPct($res);
                Database::execute(
                    "UPDATE routers SET status='online', cpu_load=:c, mem_usage=:m, last_seen=now() WHERE id=:id",
                    [':c' => $cpu, ':m' => $mem, ':id' => $r['id']]
                );
                $this->metric($tenantId, 'router', $r['id'], 'cpu', $cpu);
                $this->metric($tenantId, 'router', $r['id'], 'mem', $mem);

                if ($cpu >= 90) {
                    $this->alert($tenantId, 'critical', 'cpu', $r['name'], "CPU Usage di atas 90% ({$cpu}%)");
                    $summary['alerts']++;
                }
            } catch (\Throwable $e) {
                Database::execute("UPDATE routers SET status='offline' WHERE id=:id", [':id' => $r['id']]);
                $this->alert($tenantId, 'critical', 'router_down', $r['name'], 'Router tidak merespon: ' . $e->getMessage());
                $summary['alerts']++;
            }
        }
        return $count;
    }

    private function pollOlts(string $tenantId, array &$summary): int
    {
        $count = 0;
        $olts = Database::select("SELECT * FROM olts WHERE tenant_id=:t", [':t' => $tenantId]);
        foreach ($olts as $o) {
            $count++;
            try {
                $template = $this->oids->template((string) $o['vendor']);
                $snmp = new SnmpService((string) $o['ip_address'], (string) ($o['snmp_community'] ?? 'public'));
                if (!$snmp->reachable()) {
                    throw new \RuntimeException('SNMP timeout');
                }
                Database::execute("UPDATE olts SET status='online', last_seen=now() WHERE id=:id", [':id' => $o['id']]);

                // Sync ONU RX power + status for this OLT
                $rxOid = $template['oids']['onu_rx_power'] ?? null;
                $stOid = $template['oids']['onu_status'] ?? null;
                if ($rxOid) {
                    $rxWalk = $snmp->walk($rxOid);
                    $stWalk = $stOid ? $snmp->walk($stOid) : [];
                    foreach ($rxWalk as $idx => $raw) {
                        $rx = $this->oids->normalize($template, 'rx_power', $raw);
                        $st = isset($stWalk[$idx]) ? $this->oids->normalize($template, 'status', $stWalk[$idx]) : 'unknown';
                        $this->metric($tenantId, 'olt', $o['id'], 'onu_rx', is_numeric($rx) ? $rx : 0);
                        if (is_numeric($rx) && $rx < -27) {
                            $this->alert($tenantId, 'warning', 'loss', $o['name'], "ONU idx $idx redaman tinggi ({$rx} dBm)");
                            $summary['alerts']++;
                        }
                    }
                }
            } catch (\Throwable $e) {
                Database::execute("UPDATE olts SET status='offline' WHERE id=:id", [':id' => $o['id']]);
                $this->alert($tenantId, 'critical', 'olt_down', $o['name'], 'OLT tidak merespon SNMP');
                $summary['alerts']++;
            }
        }
        return $count;
    }

    private function memPct(array $res): int
    {
        $free  = (float) ($res['free-memory'] ?? 0);
        $total = (float) ($res['total-memory'] ?? 0);
        if ($total <= 0) {
            return 0;
        }
        return (int) round((($total - $free) / $total) * 100);
    }

    private function metric(string $tenantId, string $type, string $deviceId, string $metric, float $value): void
    {
        Database::execute(
            "INSERT INTO device_metrics (tenant_id, device_type, device_id, metric, value)
             VALUES (:t, :dt, :d, :m, :v)",
            [':t' => $tenantId, ':dt' => $type, ':d' => $deviceId, ':m' => $metric, ':v' => $value]
        );
    }

    private function alert(string $tenantId, string $severity, string $type, ?string $source, string $msg): void
    {
        // de-dupe: skip if same unresolved alert exists in last 10 minutes
        $exists = Database::scalar(
            "SELECT 1 FROM alerts WHERE tenant_id=:t AND type=:ty AND source=:s
             AND is_resolved=false AND created_at > now() - interval '10 minutes' LIMIT 1",
            [':t' => $tenantId, ':ty' => $type, ':s' => $source]
        );
        if ($exists) {
            return;
        }
        Database::execute(
            "INSERT INTO alerts (tenant_id, severity, type, source, message)
             VALUES (:t, :sev, :ty, :s, :m)",
            [':t' => $tenantId, ':sev' => $severity, ':ty' => $type, ':s' => $source, ':m' => $msg]
        );
    }
}
