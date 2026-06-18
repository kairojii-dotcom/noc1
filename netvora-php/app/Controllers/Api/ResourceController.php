<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Repositories\AlertRepository;
use App\Repositories\AuditRepository;
use App\Repositories\BaseRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\InvoiceRepository;
use App\Repositories\OdpRepository;
use App\Repositories\OltRepository;
use App\Repositories\OnuRepository;
use App\Repositories\PaymentRepository;
use App\Repositories\RouterRepository;
use App\Repositories\SubscriptionRepository;
use App\Repositories\TicketRepository;
use App\Repositories\UserRepository;

/**
 * Generic tenant-scoped CRUD controller for network/billing resources.
 * The concrete resource is chosen by the {resource} route param.
 */
final class ResourceController extends Controller
{
    private const MAP = [
        'routers'       => RouterRepository::class,
        'olts'          => OltRepository::class,
        'onus'          => OnuRepository::class,
        'odps'          => OdpRepository::class,
        'customers'     => CustomerRepository::class,
        'alerts'        => AlertRepository::class,
        'tickets'       => TicketRepository::class,
        'subscriptions' => SubscriptionRepository::class,
        'invoices'      => InvoiceRepository::class,
        'payments'      => PaymentRepository::class,
        'users'         => UserRepository::class,
        'audit_logs'    => AuditRepository::class,
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

        if ($request->param('resource') === 'users') {
            foreach ($rows as &$r) {
                unset($r['password_hash']);
            }
        }
        Response::paginated($rows, $total, $page, $perPage);
    }

    public function show(Request $request): void
    {
        $row = $this->repo($request)->find($request->param('id'), $request->tenantId());
        if ($row) {
            unset($row['password_hash']);
        }
        $row ? Response::success($row) : Response::error('Data tidak ditemukan', 404);
    }

    public function store(Request $request): void
    {
        $resource = $request->param('resource');
        $data = $request->all();
        $data['tenant_id'] = $request->tenantId();
        unset($data['id'], $data['created_at'], $data['updated_at']);

        if ($resource === 'users') {
            $data = $this->prepareUser($data, true);
        }

        $row = $this->repo($request)->create($data);
        unset($row['password_hash']);
        Response::success($row, 'Data dibuat', 201);
    }

    public function update(Request $request): void
    {
        $resource = $request->param('resource');
        $data = $request->all();
        unset($data['id'], $data['tenant_id'], $data['created_at'], $data['updated_at']);

        if ($resource === 'users') {
            $data = $this->prepareUser($data, false);
        }

        $row = $this->repo($request)->update($request->param('id'), $data, $request->tenantId());
        if ($row) {
            unset($row['password_hash']);
        }
        $row ? Response::success($row, 'Data diperbarui') : Response::error('Data tidak ditemukan', 404);
    }

    public function destroy(Request $request): void
    {
        $ok = $this->repo($request)->delete($request->param('id'), $request->tenantId());
        $ok ? Response::success(null, 'Data dihapus') : Response::error('Data tidak ditemukan', 404);
    }

    private function prepareUser(array $data, bool $isCreate): array
    {
        if (!empty($data['password'])) {
            $data['password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }
        unset($data['password']);
        if ($isCreate) {
            $data['role_code'] = $data['role_code'] ?? 'cs';
        }
        return $data;
    }
}
