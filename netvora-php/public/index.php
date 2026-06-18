<?php

declare(strict_types=1);

use App\Core\Request;
use App\Core\Response;
use App\Core\Router;

require dirname(__DIR__) . '/bootstrap.php';

// --- CORS (API consumers / Supabase Realtime clients) ---
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Tenant-Id');
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'OPTIONS') {
    http_response_code(204);
    exit;
}

$router  = new Router();
$request = new Request();

// Route definitions
(require BASE_PATH . '/routes/web.php')($router);
(require BASE_PATH . '/routes/api.php')($router);

$router->dispatch($request);
