<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\RouterRepository;
use App\Services\Monitoring\MikrotikApiService;

final class MikrotikController extends Controller
{
    public function pppoeUsers(Request $request): void
    {
        [$page, $perPage, $offset] = $this->paginationParams($request);
        $tenantId = $request->tenantId();
        $routerId = (string) $request->query('router_id', '');

        $sql = "SELECT * FROM routers WHERE tenant_id = :t";
        $params = [':t' => $tenantId];
        if ($routerId !== '') {
            $sql .= " AND id = :id";
            $params[':id'] = $routerId;
        }
        $sql .= " ORDER BY name ASC";

        $rows = [];
        $errors = [];
        foreach (Database::select($sql, $params) as $router) {
            try {
                $rows = array_merge($rows, $this->collectPppoeRows($router));
            } catch (\Throwable $e) {
                $errors[] = [
                    'router_id' => $router['id'],
                    'router_name' => $router['name'],
                    'message' => $e->getMessage(),
                ];
                Database::execute("UPDATE routers SET status='offline' WHERE id=:id", [':id' => $router['id']]);
            }
        }

        usort($rows, static function (array $a, array $b): int {
            $status = ['online' => 0, 'offline' => 1];
            return [$status[$a['status']] ?? 9, $a['router_name'], $a['user']] <=> [$status[$b['status']] ?? 9, $b['router_name'], $b['user']];
        });

        $total = count($rows);
        Response::json([
            'success' => true,
            'data' => array_slice($rows, $offset, $perPage),
            'meta' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'last_page' => (int) ceil($total / max(1, $perPage)),
                'online' => count(array_filter($rows, fn ($r) => $r['status'] === 'online')),
                'offline' => count(array_filter($rows, fn ($r) => $r['status'] === 'offline')),
                'routers' => count(Database::select($sql, $params)),
            ],
            'errors' => $errors,
        ]);
    }

    public function routerPppoe(Request $request): void
    {
        $router = (new RouterRepository())->find((string) $request->param('id'), $request->tenantId());
        if (!$router) {
            Response::error('Router tidak ditemukan', 404);
        }

        try {
            $rows = $this->collectPppoeRows($router);
        } catch (\Throwable $e) {
            Database::execute("UPDATE routers SET status='offline' WHERE id=:id", [':id' => $router['id']]);
            Response::error('Gagal mengambil PPPoE dari MikroTik: ' . $e->getMessage(), 502);
        }

        Response::success([
            'router' => [
                'id' => $router['id'],
                'name' => $router['name'],
                'ip_address' => (string) $router['ip_address'],
            ],
            'summary' => [
                'total' => count($rows),
                'online' => count(array_filter($rows, fn ($r) => $r['status'] === 'online')),
                'offline' => count(array_filter($rows, fn ($r) => $r['status'] === 'offline')),
            ],
            'users' => $rows,
        ]);
    }

    public function testRouter(Request $request): void
    {
        $router = (new RouterRepository())->find((string) $request->param('id'), $request->tenantId());
        if (!$router) {
            Response::error('Router tidak ditemukan', 404);
        }

        try {
            $api = $this->client($router);
            $api->connect();
            $resource = $api->systemResource();
            $api->close();
            $this->markOnline($router, $resource);
            Response::success(['router' => $router['name'], 'resource' => $resource], 'MikroTik online');
        } catch (\Throwable $e) {
            Database::execute("UPDATE routers SET status='offline' WHERE id=:id", [':id' => $router['id']]);
            Response::error('MikroTik tidak bisa diakses: ' . $e->getMessage(), 502);
        }
    }

    private function collectPppoeRows(array $router): array
    {
        $api = $this->client($router);
        $api->connect();

        try {
            $resource = $api->systemResource();
            $active = $api->pppoeActive();
            $secrets = $api->pppSecrets();
            $profiles = $api->pppProfiles();
        } finally {
            $api->close();
        }

        $this->markOnline($router, $resource ?? []);

        $profileLimits = [];
        foreach ($profiles as $profile) {
            $name = (string) ($profile['name'] ?? '');
            if ($name !== '') {
                $profileLimits[$name] = (string) ($profile['rate-limit'] ?? $profile['only-one'] ?? '');
            }
        }

        $activeByName = [];
        foreach ($active as $session) {
            $name = (string) ($session['name'] ?? $session['user'] ?? '');
            if ($name !== '') {
                $activeByName[$name] = $session;
            }
        }

        $rows = [];
        $seen = [];
        foreach ($secrets as $secret) {
            $user = (string) ($secret['name'] ?? '');
            if ($user === '') {
                continue;
            }
            $session = $activeByName[$user] ?? null;
            $profile = (string) ($secret['profile'] ?? $session['profile'] ?? 'default');
            $rows[] = $this->formatRow($router, $user, $profile, $profileLimits[$profile] ?? '', $secret, $session);
            $seen[$user] = true;
        }

        foreach ($activeByName as $user => $session) {
            if (isset($seen[$user])) {
                continue;
            }
            $profile = (string) ($session['profile'] ?? 'active-only');
            $rows[] = $this->formatRow($router, $user, $profile, $profileLimits[$profile] ?? '', [], $session);
        }

        return $rows;
    }

    private function formatRow(array $router, string $user, string $profile, string $limit, array $secret, ?array $session): array
    {
        $online = $session !== null;
        return [
            'id' => $router['id'] . ':' . $user,
            'router_id' => $router['id'],
            'router_name' => $router['name'],
            'router_ip' => (string) $router['ip_address'],
            'user' => $user,
            'name' => $user,
            'ip_address' => (string) ($session['address'] ?? $secret['remote-address'] ?? ''),
            'profile' => $profile,
            'limit' => $limit !== '' ? $limit : '-',
            'status' => $online ? 'online' : 'offline',
            'caller_id' => (string) ($session['caller-id'] ?? $secret['caller-id'] ?? ''),
            'uptime' => (string) ($session['uptime'] ?? ''),
            'service' => (string) ($session['service'] ?? $secret['service'] ?? 'pppoe'),
            'disabled' => ((string) ($secret['disabled'] ?? 'false')) === 'true',
            'last_seen' => $online ? gmdate('c') : null,
        ];
    }

    private function client(array $router): MikrotikApiService
    {
        $host = (string) ($router['ip_address'] ?? '');
        $user = (string) ($router['username'] ?? '');
        $pass = (string) ($router['password_enc'] ?? '');
        $port = (int) ($router['api_port'] ?? 8728);
        if ($host === '' || $user === '') {
            throw new \RuntimeException('IP/username MikroTik belum lengkap');
        }
        return new MikrotikApiService($host, $user, $pass, $port > 0 ? $port : 8728, 8);
    }

    private function markOnline(array $router, array $resource): void
    {
        $cpu = (int) ($resource['cpu-load'] ?? 0);
        $mem = $this->memPct($resource);
        $model = (string) ($resource['board-name'] ?? $router['model'] ?? '');
        Database::execute(
            "UPDATE routers SET status='online', cpu_load=:cpu, mem_usage=:mem, model=COALESCE(NULLIF(:model, ''), model), last_seen=now() WHERE id=:id",
            [':cpu' => $cpu, ':mem' => $mem, ':model' => $model, ':id' => $router['id']]
        );
    }

    private function memPct(array $resource): int
    {
        $free = (float) ($resource['free-memory'] ?? 0);
        $total = (float) ($resource['total-memory'] ?? 0);
        if ($total <= 0) {
            return 0;
        }
        return (int) round((($total - $free) / $total) * 100);
    }
}
