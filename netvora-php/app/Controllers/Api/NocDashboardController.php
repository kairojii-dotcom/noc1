<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class NocDashboardController extends Controller
{
    public function summary(Request $request): void
    {
        $tenantId = $this->tenant($request);
        Response::success([
            'routers' => $this->statusCount('routers', $tenantId),
            'customers' => $this->customerCount($tenantId),
            'olts' => $this->statusCount('olts', $tenantId),
            'onus' => $this->onuCount($tenantId),
            'loss' => $this->lossCount($tenantId),
            'tickets' => $this->ticketCount($tenantId),
            'billing' => $this->billingCount($tenantId),
        ]);
    }

    public function routerStatus(Request $request): void
    {
        Response::success($this->statusCount('routers', $this->tenant($request)));
    }

    public function oltStatus(Request $request): void
    {
        Response::success($this->statusCount('olts', $this->tenant($request)));
    }

    public function onuStatus(Request $request): void
    {
        Response::success($this->onuCount($this->tenant($request)));
    }

    public function customerStatus(Request $request): void
    {
        Response::success($this->customerCount($this->tenant($request)));
    }

    public function traffic24h(Request $request): void
    {
        $tenantId = $this->tenant($request);
        Response::success([
            'download' => $this->series($tenantId, ['rx_bps', 'download', 'download_bps']),
            'upload' => $this->series($tenantId, ['tx_bps', 'upload', 'upload_bps']),
        ]);
    }

    public function loss24h(Request $request): void
    {
        $tenantId = $this->tenant($request);
        Response::success([
            'loss' => $this->series($tenantId, ['loss_pct', 'loss', 'onu_rx'], true),
            'critical' => (int) Database::scalar(
                "SELECT count(*) FROM onus WHERE tenant_id=:t AND (status IN ('los','offline') OR rx_power < -27)",
                [':t' => $tenantId]
            ),
        ]);
    }

    public function alerts(Request $request): void
    {
        $tenantId = $this->tenant($request);
        $limit = min(50, max(1, (int) $request->query('limit', 15)));
        Response::success(Database::select(
            "SELECT id, severity, type, source, message, is_resolved, created_at
             FROM alerts WHERE tenant_id=:t ORDER BY created_at DESC LIMIT :lim",
            [':t' => $tenantId, ':lim' => $limit]
        ));
    }

    public function criticalDevices(Request $request): void
    {
        $tenantId = $this->tenant($request);
        $routers = Database::select(
            "SELECT id, name, 'router' AS type, location, status, cpu_load AS cpu, mem_usage AS memory, uptime_sec, last_seen
             FROM routers WHERE tenant_id=:t AND (status!='online' OR cpu_load >= 80 OR mem_usage >= 80)
             ORDER BY last_seen DESC NULLS LAST LIMIT 20",
            [':t' => $tenantId]
        );
        $olts = Database::select(
            "SELECT id, name, 'olt' AS type, location, status, 0 AS cpu, 0 AS memory, 0 AS uptime_sec, last_seen
             FROM olts WHERE tenant_id=:t AND status!='online'
             ORDER BY last_seen DESC NULLS LAST LIMIT 20",
            [':t' => $tenantId]
        );
        $onus = Database::select(
            "SELECT id, COALESCE(name, serial) AS name, 'onu' AS type, pon_port AS location, status, 0 AS cpu, 0 AS memory, uptime_sec, last_seen
             FROM onus WHERE tenant_id=:t AND (status!='online' OR rx_power < -27)
             ORDER BY last_seen DESC NULLS LAST LIMIT 20",
            [':t' => $tenantId]
        );
        Response::success(array_slice(array_merge($routers, $olts, $onus), 0, 30));
    }

    public function topology(Request $request): void
    {
        $tenantId = $this->tenant($request);
        $nodes = Database::select(
            "SELECT id, label, node_type, icon, x, y, meta, created_at FROM topology_nodes WHERE tenant_id=:t ORDER BY created_at ASC",
            [':t' => $tenantId]
        );
        $edges = Database::select(
            "SELECT id, from_node, to_node, color, label, created_at FROM topology_edges WHERE tenant_id=:t ORDER BY created_at ASC",
            [':t' => $tenantId]
        );
        Response::success(['nodes' => $nodes, 'edges' => $edges]);
    }

    public function map(Request $request): void
    {
        $tenantId = $this->tenant($request);
        $items = [];
        $queries = [
            "SELECT id, name, 'router' AS type, status, latitude, longitude, location, NULL::text AS address FROM routers WHERE tenant_id=:t AND latitude IS NOT NULL AND longitude IS NOT NULL",
            "SELECT id, name, 'olt' AS type, status, latitude, longitude, location, NULL::text AS address FROM olts WHERE tenant_id=:t AND latitude IS NOT NULL AND longitude IS NOT NULL",
            "SELECT id, name, 'odp' AS type, status, latitude, longitude, NULL::text AS location, NULL::text AS address FROM odps WHERE tenant_id=:t AND latitude IS NOT NULL AND longitude IS NOT NULL",
            "SELECT id, name, 'customer' AS type, status, latitude, longitude, NULL::text AS location, address FROM customers WHERE tenant_id=:t AND latitude IS NOT NULL AND longitude IS NOT NULL",
        ];
        foreach ($queries as $sql) {
            $items = array_merge($items, Database::select($sql, [':t' => $tenantId]));
        }
        Response::success($items);
    }

    private function tenant(Request $request): string
    {
        $tenantId = $request->tenantId();
        if (!$tenantId && $request->role() === 'super_admin') {
            $tenantId = (string) $request->query('tenant_id', '');
        }
        if (!$tenantId) {
            Response::error('tenant_id wajib dipilih', 422);
        }
        return $tenantId;
    }

    private function statusCount(string $table, string $tenantId): array
    {
        $rows = Database::select("SELECT status, count(*)::int AS total FROM {$table} WHERE tenant_id=:t GROUP BY status", [':t' => $tenantId]);
        $out = ['total' => 0, 'online' => 0, 'offline' => 0, 'unknown' => 0];
        foreach ($rows as $row) {
            $status = (string) ($row['status'] ?? 'unknown');
            $count = (int) $row['total'];
            $out[$status] = $count;
            $out['total'] += $count;
        }
        return $out;
    }

    private function onuCount(string $tenantId): array
    {
        $base = $this->statusCount('onus', $tenantId);
        $base['loss_high'] = (int) Database::scalar("SELECT count(*) FROM onus WHERE tenant_id=:t AND rx_power < -27", [':t' => $tenantId]);
        return $base;
    }

    private function customerCount(string $tenantId): array
    {
        $rows = Database::select("SELECT status, count(*)::int AS total FROM customers WHERE tenant_id=:t GROUP BY status", [':t' => $tenantId]);
        $out = ['total' => 0, 'active' => 0, 'inactive' => 0, 'isolir' => 0, 'suspend' => 0, 'expired' => 0, 'unpaid' => 0];
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $count = (int) $row['total'];
            $out[$status] = $count;
            $out['total'] += $count;
            if (in_array($status, ['suspend', 'expired'], true)) {
                $out['inactive'] += $count;
            }
        }
        $out['unpaid'] = (int) Database::scalar(
            "SELECT count(DISTINCT customer_id) FROM invoices WHERE tenant_id=:t AND status IN ('unpaid','overdue') AND customer_id IS NOT NULL",
            [':t' => $tenantId]
        );
        return $out;
    }

    private function lossCount(string $tenantId): array
    {
        $total = (int) Database::scalar("SELECT count(*) FROM onus WHERE tenant_id=:t", [':t' => $tenantId]);
        $critical = (int) Database::scalar("SELECT count(*) FROM onus WHERE tenant_id=:t AND (rx_power < -27 OR status IN ('los','offline'))", [':t' => $tenantId]);
        return ['total' => $total, 'normal' => max(0, $total - $critical), 'critical' => $critical, 'percent' => $total > 0 ? round(($critical / $total) * 100, 2) : 0];
    }

    private function ticketCount(string $tenantId): array
    {
        $rows = Database::select("SELECT status, count(*)::int AS total FROM tickets WHERE tenant_id=:t GROUP BY status", [':t' => $tenantId]);
        $out = ['total' => 0, 'open' => 0, 'in_progress' => 0, 'resolved' => 0, 'closed' => 0];
        foreach ($rows as $row) {
            $status = (string) $row['status'];
            $count = (int) $row['total'];
            $out[$status] = $count;
            $out['total'] += $count;
        }
        return $out;
    }

    private function billingCount(string $tenantId): array
    {
        return [
            'unpaid_invoices' => (int) Database::scalar("SELECT count(*) FROM invoices WHERE tenant_id=:t AND status IN ('unpaid','overdue')", [':t' => $tenantId]),
            'paid_month' => (float) Database::scalar("SELECT COALESCE(sum(total),0) FROM invoices WHERE tenant_id=:t AND status='paid' AND date_trunc('month', paid_at)=date_trunc('month', now())", [':t' => $tenantId]),
        ];
    }

    private function series(string $tenantId, array $metrics, bool $absolute = false): array
    {
        $placeholders = [];
        $params = [':t' => $tenantId];
        foreach ($metrics as $i => $metric) {
            $key = ':m' . $i;
            $placeholders[] = $key;
            $params[$key] = $metric;
        }
        $sql = "SELECT to_char(date_trunc('hour', ts), 'HH24:00') AS bucket, avg(value) AS value FROM device_metrics WHERE tenant_id=:t AND metric IN (" . implode(',', $placeholders) . ") AND ts >= now() - interval '24 hours' GROUP BY date_trunc('hour', ts) ORDER BY date_trunc('hour', ts) ASC";
        $rows = Database::select($sql, $params);
        return array_map(static fn ($r) => ['bucket' => $r['bucket'], 'value' => round($absolute ? abs((float) $r['value']) : (float) $r['value'], 2)], $rows);
    }
}
