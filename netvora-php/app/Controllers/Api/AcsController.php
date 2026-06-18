<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Acs\AcsService;

final class AcsController
{
    public function __construct(private AcsService $acs = new AcsService())
    {
    }

    /**
     * PUBLIC TR-069 / CWMP endpoint. CPEs are configured with ACS URL:
     *   https://<host>/acs/<tenant_id>
     */
    public function cwmp(Request $request): void
    {
        $tenantId = $request->param('tenant');
        $cookie = $_COOKIE['nvacs'] ?? null;

        $result = $this->acs->handleCwmp($tenantId, $request->rawInput(), $cookie);

        if ($result['device_id']) {
            setcookie('nvacs', $result['device_id'], ['path' => '/acs', 'httponly' => true, 'samesite' => 'Lax']);
        }
        http_response_code($result['status']);
        if ($result['status'] === 204 || $result['xml'] === '') {
            header('Content-Length: 0');
            exit;
        }
        header('Content-Type: text/xml; charset=utf-8');
        header('SOAPServer: NETVORA-ACS');
        echo $result['xml'];
        exit;
    }

    /** REST: list ACS devices for the tenant. */
    public function index(Request $request): void
    {
        $page    = max(1, (int) $request->query('page', 1));
        $perPage = min(100, max(1, (int) $request->query('per_page', 15)));
        $offset  = ($page - 1) * $perPage;
        $tenantId = $request->tenantId();

        $total = (int) Database::scalar("SELECT count(*) FROM acs_devices WHERE tenant_id=:t", [':t' => $tenantId]);
        $rows = Database::select(
            "SELECT * FROM acs_devices WHERE tenant_id=:t ORDER BY last_inform DESC NULLS LAST LIMIT :l OFFSET :o",
            [':t' => $tenantId, ':l' => $perPage, ':o' => $offset]
        );
        Response::paginated($rows, $total, $page, $perPage);
    }

    /** REST: device detail with parameters + recent tasks. */
    public function show(Request $request): void
    {
        $tenantId = $request->tenantId();
        $id = $request->param('id');
        $device = Database::selectOne("SELECT * FROM acs_devices WHERE id=:id AND tenant_id=:t", [':id' => $id, ':t' => $tenantId]);
        if (!$device) {
            Response::error('Device tidak ditemukan', 404);
        }
        $device['parameters'] = Database::select("SELECT name, value FROM acs_parameters WHERE device_id=:d ORDER BY name", [':d' => $id]);
        $device['tasks'] = Database::select("SELECT * FROM acs_tasks WHERE device_id=:d ORDER BY created_at DESC LIMIT 20", [':d' => $id]);
        Response::success($device);
    }

    /**
     * REST: queue a remote task on a device.
     * type: reboot | factory_reset | get_param | wifi_config | wan_config | download | set_param
     */
    public function task(Request $request): void
    {
        $tenantId = $request->tenantId();
        $id = $request->param('id');
        $device = Database::selectOne("SELECT id FROM acs_devices WHERE id=:id AND tenant_id=:t", [':id' => $id, ':t' => $tenantId]);
        if (!$device) {
            Response::error('Device tidak ditemukan', 404);
        }
        $type = (string) $request->input('type', '');
        $allowed = ['reboot','factory_reset','get_param','wifi_config','wan_config','download','set_param'];
        if (!in_array($type, $allowed, true)) {
            Response::error('Tipe task tidak valid', 422, ['allowed' => $allowed]);
        }
        $payload = $request->input('payload', []);
        $task = $this->acs->createTask($tenantId, $id, $type, is_array($payload) ? $payload : []);
        Response::success($task, 'Task dijadwalkan — akan dieksekusi saat CPE inform berikutnya', 201);
    }
}
