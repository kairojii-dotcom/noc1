<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Topology editor persistence (Vis Network). Tenant-scoped.
 */
final class TopologyController
{
    public function index(Request $request): void
    {
        $tenantId = $request->tenantId();
        $nodes = Database::select("SELECT * FROM topology_nodes WHERE tenant_id = :t", [':t' => $tenantId]);
        $edges = Database::select("SELECT * FROM topology_edges WHERE tenant_id = :t", [':t' => $tenantId]);
        Response::success(['nodes' => $nodes, 'edges' => $edges]);
    }

    /** Replace the whole topology for the tenant (atomic save). */
    public function save(Request $request): void
    {
        $tenantId = $request->tenantId();
        $nodes = $request->input('nodes', []);
        $edges = $request->input('edges', []);

        Database::transaction(function () use ($tenantId, $nodes, $edges) {
            Database::execute("DELETE FROM topology_edges WHERE tenant_id = :t", [':t' => $tenantId]);
            Database::execute("DELETE FROM topology_nodes WHERE tenant_id = :t", [':t' => $tenantId]);

            $idMap = [];
            foreach ($nodes as $n) {
                $row = Database::insertReturning(
                    "INSERT INTO topology_nodes (tenant_id, label, node_type, icon, x, y, meta)
                     VALUES (:t, :l, :nt, :ic, :x, :y, :m) RETURNING id",
                    [
                        ':t'  => $tenantId,
                        ':l'  => $n['label'] ?? 'Node',
                        ':nt' => $n['node_type'] ?? 'router',
                        ':ic' => $n['icon'] ?? null,
                        ':x'  => $n['x'] ?? null,
                        ':y'  => $n['y'] ?? null,
                        ':m'  => json_encode($n['meta'] ?? []),
                    ]
                );
                $idMap[(string) ($n['id'] ?? $n['label'])] = $row['id'];
            }
            foreach ($edges as $e) {
                $from = $idMap[(string) ($e['from'] ?? '')] ?? null;
                $to   = $idMap[(string) ($e['to'] ?? '')] ?? null;
                if (!$from || !$to) {
                    continue;
                }
                Database::execute(
                    "INSERT INTO topology_edges (tenant_id, from_node, to_node, color, label)
                     VALUES (:t, :f, :to, :c, :l)",
                    [':t' => $tenantId, ':f' => $from, ':to' => $to, ':c' => $e['color'] ?? '#10b981', ':l' => $e['label'] ?? null]
                );
            }
        });

        Response::success(null, 'Topologi disimpan');
    }
}
