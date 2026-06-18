<?php

declare(strict_types=1);

use App\Controllers\Api\AiController;
use App\Controllers\Api\AuthController;
use App\Controllers\Api\DashboardController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\ResourceController;
use App\Controllers\Api\TenantController;
use App\Core\Router;

return static function (Router $router): void {
    // ---- Public ----
    $router->get('/api/health', [HealthController::class, 'check']);
    $router->post('/api/auth/login', [AuthController::class, 'login'], ['ratelimit']);
    $router->post('/api/auth/refresh', [AuthController::class, 'refresh'], ['ratelimit']);
    $router->post('/api/auth/logout', [AuthController::class, 'logout']);

    // ---- Authenticated ----
    $router->group('/api', ['auth'], function (Router $r): void {
        $r->get('/auth/me', [AuthController::class, 'me']);

        // Super Admin scope
        $r->get('/superadmin/dashboard', [DashboardController::class, 'superadmin'], ['role:super_admin']);
        $r->get('/tenants', [TenantController::class, 'index'], ['role:super_admin']);
        $r->post('/tenants', [TenantController::class, 'store'], ['role:super_admin']);
        $r->get('/tenants/{id}', [TenantController::class, 'show'], ['role:super_admin']);
        $r->put('/tenants/{id}', [TenantController::class, 'update'], ['role:super_admin']);
        $r->patch('/tenants/{id}/status', [TenantController::class, 'setStatus'], ['role:super_admin']);
        $r->delete('/tenants/{id}', [TenantController::class, 'destroy'], ['role:super_admin']);

        // Tenant NOC scope (RLS enforced)
        $r->get('/dashboard', [DashboardController::class, 'tenant'], ['tenant']);
        $r->post('/ai/analyze', [AiController::class, 'analyze'], ['tenant', 'perm:ai_analytics']);

        // Generic tenant resources: routers, olts, onus, customers, alerts, tickets
        $r->get('/{resource}', [ResourceController::class, 'index'], ['tenant']);
        $r->post('/{resource}', [ResourceController::class, 'store'], ['tenant']);
        $r->get('/{resource}/{id}', [ResourceController::class, 'show'], ['tenant']);
        $r->put('/{resource}/{id}', [ResourceController::class, 'update'], ['tenant']);
        $r->delete('/{resource}/{id}', [ResourceController::class, 'destroy'], ['tenant']);
    });
};
