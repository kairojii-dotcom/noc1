<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class DashboardRepository
{
    public function superadminStats(): array
    {
        return Database::selectOne("SELECT * FROM v_superadmin_stats") ?? [];
    }

    public function tenantSnapshot(string $tenantId): array
    {
        $json = Database::scalar("SELECT tenant_dashboard(:t)", [':t' => $tenantId]);
        return is_string($json) ? (json_decode($json, true) ?: []) : [];
    }

    public function recentAlerts(?string $tenantId = null, int $limit = 5): array
    {
        if ($tenantId) {
            return Database::select(
                "SELECT * FROM alerts WHERE tenant_id=:t ORDER BY created_at DESC LIMIT :l",
                [':t' => $tenantId, ':l' => $limit]
            );
        }
        return Database::select(
            "SELECT a.*, t.name AS tenant_name FROM alerts a
             JOIN tenants t ON t.id=a.tenant_id
             ORDER BY a.created_at DESC LIMIT :l",
            [':l' => $limit]
        );
    }

    public function metricSeries(string $tenantId, string $metric, int $hours = 24): array
    {
        return Database::select(
            "SELECT to_char(date_trunc('hour', ts),'HH24:00') AS bucket, round(avg(value),2) AS value
             FROM device_metrics
             WHERE tenant_id=:t AND metric=:m AND ts >= now() - (:h || ' hours')::interval
             GROUP BY 1 ORDER BY 1",
            [':t' => $tenantId, ':m' => $metric, ':h' => $hours]
        );
    }

    /** Router / OLT status donut counts for a tenant. */
    public function deviceStatusCounts(string $tenantId, string $table): array
    {
        return Database::selectOne(
            "SELECT count(*) AS total,
                    count(*) FILTER (WHERE status='online') AS online,
                    count(*) FILTER (WHERE status='offline') AS offline
             FROM {$table} WHERE tenant_id=:t",
            [':t' => $tenantId]
        ) ?? ['total' => 0, 'online' => 0, 'offline' => 0];
    }
}
