<?php

declare(strict_types=1);

use App\Controllers\Api\AcsController;
use App\Controllers\Api\AdminResourceController;
use App\Controllers\Api\AiController;
use App\Controllers\Api\AuthController;
use App\Controllers\Api\BillingController;
use App\Controllers\Api\DashboardController;
use App\Controllers\Api\HealthController;
use App\Controllers\Api\ResourceController;
use App\Controllers\Api\TenantController;
use App\Controllers\Api\TenantSettingsController;
use App\Controllers\Api\TopologyController;
use App\Core\Router;

return static function (Router $router): void {
    // ---- Public ----
    $router->get('/api/health', [HealthController::class, 'check']);
    $router->post('/api/auth/login', [AuthController::class, 'login'], ['ratelimit']);
    $router->post('/api/auth/refresh', [AuthController::class, 'refresh'], ['ratelimit']);
    $router->post('/api/auth/logout', [AuthController::class, 'logout']);

    // PUBLIC payment gateway webhooks (Midtrans / Xendit)
    $router->post('/api/webhooks/payment/{provider}', [BillingController::class, 'webhook']);

    // ---- Authenticated ----
    $router->group('/api', ['auth'], function (Router $r): void {
        $r->get('/auth/me', [AuthController::class, 'me']);

        // ===== Super Admin scope =====
        $r->get('/superadmin/dashboard', [DashboardController::class, 'superadmin'], ['role:super_admin']);
        $r->get('/tenants', [TenantController::class, 'index'], ['role:super_admin']);
        $r->post('/tenants', [TenantController::class, 'store'], ['role:super_admin']);
        $r->get('/tenants/{id}', [TenantController::class, 'show'], ['role:super_admin']);
        $r->put('/tenants/{id}', [TenantController::class, 'update'], ['role:super_admin']);
        $r->patch('/tenants/{id}/status', [TenantController::class, 'setStatus'], ['role:super_admin']);
        $r->delete('/tenants/{id}', [TenantController::class, 'destroy'], ['role:super_admin']);

        // Global CRUD: users, packages, roles, audit_logs, subscriptions, invoices, payments
        $r->get('/admin/{resource}', [AdminResourceController::class, 'index'], ['role:super_admin']);
        $r->post('/admin/{resource}', [AdminResourceController::class, 'store'], ['role:super_admin']);
        $r->put('/admin/{resource}/{id}', [AdminResourceController::class, 'update'], ['role:super_admin']);
        $r->delete('/admin/{resource}/{id}', [AdminResourceController::class, 'destroy'], ['role:super_admin']);

        // ===== Tenant NOC scope (RLS enforced) =====
        $r->get('/dashboard', [DashboardController::class, 'tenant'], ['tenant']);
        $r->get('/tenant/profile', [TenantSettingsController::class, 'show'], ['tenant']);
        $r->put('/tenant/profile', [TenantSettingsController::class, 'update'], ['tenant']);
        $r->get('/topology', [TopologyController::class, 'index'], ['tenant']);
        $r->post('/topology', [TopologyController::class, 'save'], ['tenant']);
        $r->post('/ai/analyze', [AiController::class, 'analyze'], ['tenant', 'perm:ai_analytics']);

        // Billing
        $r->post('/billing/invoices', [BillingController::class, 'createInvoice'], ['tenant']);
        $r->post('/billing/generate', [BillingController::class, 'generate'], ['tenant']);
        $r->post('/billing/pay', [BillingController::class, 'payManual'], ['tenant']);
        $r->post('/billing/payment-link', [BillingController::class, 'paymentLink'], ['tenant']);
        $r->post('/billing/auto-suspend', [BillingController::class, 'autoSuspend'], ['tenant']);

        // ACS (TR-069) management
        $r->get('/acs/devices', [AcsController::class, 'index'], ['tenant']);
        $r->get('/acs/devices/{id}', [AcsController::class, 'show'], ['tenant']);
        $r->post('/acs/devices/{id}/task', [AcsController::class, 'task'], ['tenant']);

        // Generic tenant resources (register LAST to avoid shadowing specific paths above)
        $r->get('/{resource}', [ResourceController::class, 'index'], ['tenant']);
        $r->post('/{resource}', [ResourceController::class, 'store'], ['tenant']);
        $r->get('/{resource}/{id}', [ResourceController::class, 'show'], ['tenant']);
        $r->put('/{resource}/{id}', [ResourceController::class, 'update'], ['tenant']);
        $r->delete('/{resource}/{id}', [ResourceController::class, 'destroy'], ['tenant']);
    });
};
