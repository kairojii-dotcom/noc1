<?php

declare(strict_types=1);

use App\Controllers\Web\PageController;
use App\Core\Response;
use App\Core\Router;

return static function (Router $router): void {
    $router->get('/', [PageController::class, 'login']);
    $router->get('/login', [PageController::class, 'login']);
    $router->get('/superadmin', [PageController::class, 'superadmin']);
    $router->get('/dashboard', [PageController::class, 'tenant']);
    $router->get('/tv', [PageController::class, 'tvMode']);
};
