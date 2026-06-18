<?php

declare(strict_types=1);

namespace App\Controllers\Web;

use App\Core\Request;
use App\Core\Response;
use App\Core\View;

/**
 * Server-rendered shell pages. Data is hydrated client-side via the REST API
 * using the JWT stored in localStorage (Bootstrap 5 + AlpineJS + ApexCharts).
 */
final class PageController
{
    public function login(Request $request): void
    {
        Response::html(View::render('auth/login', ['title' => 'Masuk'], null));
    }

    public function superadmin(Request $request): void
    {
        Response::html(View::render('superadmin/dashboard', [
            'title' => 'Super Admin Dashboard',
            'scope' => 'superadmin',
        ]));
    }

    public function tenant(Request $request): void
    {
        Response::html(View::render('tenant/dashboard', [
            'title' => 'Dashboard Monitoring',
            'scope' => 'tenant',
        ]));
    }

    public function tvMode(Request $request): void
    {
        Response::html(View::render('tenant/tv', ['title' => 'NOC TV Mode'], null));
    }

    /** Generic module page for any sidebar menu (CRUD or custom). */
    public function module(Request $request): void
    {
        $rawScope = (string) $request->param('scope');
        // Never let the catch-all serve API paths
        if ($rawScope === 'api') {
            Response::error('Endpoint not found', 404);
        }
        $scope  = $rawScope === 'superadmin' ? 'superadmin' : 'tenant';
        $module = preg_replace('/[^a-z0-9_-]/', '', strtolower((string) $request->param('module')));

        Response::html(View::render('module', [
            'title'  => ucfirst(str_replace('-', ' ', $module)),
            'scope'  => $scope,
            'module' => $module,
        ]));
    }
}
