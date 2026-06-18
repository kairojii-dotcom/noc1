<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AlertRepository;
use App\Repositories\BaseRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\OltRepository;
use App\Repositories\OnuRepository;
use App\Repositories\RouterRepository;
use App\Repositories\TicketRepository;

/**
 * Generic tenant-scoped CRUD controller for network/billing resources.
 * The concrete resource is chosen by the {resource} route param.
 */
final class ResourceController extends Controller
{
    private const MAP = [
        'routers'   => RouterRepository::class,
        'olts'      => OltRepository::class,
        'onus'      => OnuRepository::class,
        'customers' => CustomerRepository::class,
        'alerts'    => AlertRepository::class,
        'tickets'   => TicketRepository::class,
    ];

    private function repo(Request $request): BaseRepository
    {
        $resource = $request->param('resource');
        $class = self::MAP[$resource] ?? null;
        if (!$class) {
            Response::error("Resource '$resource' tidak dikenal", 404);
        }
        return new $class();
    }

    public function index(Request $request): void
    {
        [$page, $perPage, $offset] = $this->paginationParams($request);
        $repo = $this->repo($request);
        $tenantId = $request->tenantId();
        $rows = $repo->all($tenantId, $perPage, $offset);
        $total = $repo->count($tenantId);
        Response::paginated($rows, $total, $page, $perPage);
    }

    public function show(Request $request): void
    {
        $row = $this->repo($request)->find($request->param('id'), $request->tenantId());
        $row ? Response::success($row) : Response::error('Data tidak ditemukan', 404);
    }

    public function store(Request $request): void
    {
        $data = $request->all();
        $data['tenant_id'] = $request->tenantId();
        unset($data['id'], $data['created_at'], $data['updated_at']);
        $row = $this->repo($request)->create($data);
        Response::success($row, 'Data dibuat', 201);
    }

    public function update(Request $request): void
    {
        $data = $request->all();
        unset($data['id'], $data['tenant_id'], $data['created_at'], $data['updated_at']);
        $row = $this->repo($request)->update($request->param('id'), $data, $request->tenantId());
        $row ? Response::success($row, 'Data diperbarui') : Response::error('Data tidak ditemukan', 404);
    }

    public function destroy(Request $request): void
    {
        $ok = $this->repo($request)->delete($request->param('id'), $request->tenantId());
        $ok ? Response::success(null, 'Data dihapus') : Response::error('Data tidak ditemukan', 404);
    }
}
