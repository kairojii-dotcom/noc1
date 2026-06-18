<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\DashboardService;

final class DashboardController extends Controller
{
    public function __construct(private DashboardService $service = new DashboardService())
    {
    }

    public function superadmin(Request $request): void
    {
        Response::success($this->service->superadmin());
    }

    public function tenant(Request $request): void
    {
        // super_admin may inspect any tenant via ?tenant_id=...
        $tenantId = $request->tenantId() ?? $request->query('tenant_id');
        if (!$tenantId) {
            Response::error('tenant_id diperlukan', 422);
        }
        Response::success($this->service->tenant($tenantId));
    }
}
