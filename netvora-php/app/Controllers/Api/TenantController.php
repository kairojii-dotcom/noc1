<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\TenantService;

final class TenantController extends Controller
{
    public function __construct(private TenantService $service = new TenantService())
    {
    }

    public function index(Request $request): void
    {
        [$page, $perPage] = $this->paginationParams($request);
        $result = $this->service->list(
            $page,
            $perPage,
            $request->query('search'),
            $request->query('status'),
            $request->query('package')
        );
        Response::paginated($result['rows'], $result['total'], $page, $perPage);
    }

    public function show(Request $request): void
    {
        $tenant = $this->service->find($request->param('id'));
        $tenant ? Response::success($tenant) : Response::error('Tenant tidak ditemukan', 404);
    }

    public function store(Request $request): void
    {
        $data = $this->validate($request, [
            'name'    => 'required|string|max:120',
            'domain'  => 'required|string|max:120',
            'package' => 'required|in:basic,professional,enterprise',
            'email'   => 'email',
        ]);
        $data = array_merge($request->all(), $data);
        $tenant = $this->service->create($data, $request->userId());
        Response::success($tenant, 'Tenant berhasil dibuat', 201);
    }

    public function update(Request $request): void
    {
        $tenant = $this->service->update($request->param('id'), $request->all(), $request->userId());
        $tenant ? Response::success($tenant, 'Tenant diperbarui') : Response::error('Tenant tidak ditemukan', 404);
    }

    public function setStatus(Request $request): void
    {
        $data = $this->validate($request, ['status' => 'required|in:active,suspend,expired']);
        $tenant = $this->service->setStatus($request->param('id'), $data['status'], $request->userId());
        Response::success($tenant, 'Status tenant diperbarui');
    }

    public function destroy(Request $request): void
    {
        $this->service->delete($request->param('id'), $request->userId());
        Response::success(null, 'Tenant dihapus');
    }
}
