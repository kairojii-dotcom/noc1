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
            'title'    => 'Super Admin Dashboard',
            'scope'    => 'superadmin',
        ]));
    }

    public function tenant(Request $request): void
    {
        Response::html(View::render('tenant/dashboard', [
            'title'    => 'Dashboard Monitoring',
            'scope'    => 'tenant',
        ]));
    }

    public function tvMode(Request $request): void
    {
        Response::html(View::render('tenant/tv', [
            'title' => 'NOC TV Mode',
        ], null));
    }
}
