<?php

declare(strict_types=1);

namespace App\Services;

use App\Repositories\DashboardRepository;
use App\Repositories\TenantRepository;

final class DashboardService
{
    public function __construct(
        private DashboardRepository $dash = new DashboardRepository(),
        private TenantRepository $tenants = new TenantRepository(),
    ) {
    }

    public function superadmin(): array
    {
        return [
            'stats'        => $this->dash->superadminStats(),
            'top_tenants'  => $this->tenants->topByCustomers(5),
            'new_tenants'  => $this->tenants->newPerDay(30),
            'packages'     => $this->tenants->packageDistribution(),
            'alerts'       => $this->dash->recentAlerts(null, 5),
        ];
    }

    public function tenant(string $tenantId): array
    {
        return [
            'snapshot'      => $this->dash->tenantSnapshot($tenantId),
            'router_status' => $this->dash->deviceStatusCounts($tenantId, 'routers'),
            'olt_status'    => $this->dash->deviceStatusCounts($tenantId, 'olts'),
            'traffic'       => [
                'download' => $this->dash->metricSeries($tenantId, 'rx_bps', 24),
                'upload'   => $this->dash->metricSeries($tenantId, 'tx_bps', 24),
            ],
            'loss'          => $this->dash->metricSeries($tenantId, 'loss_pct', 24),
            'alerts'        => $this->dash->recentAlerts($tenantId, 5),
        ];
    }
}
