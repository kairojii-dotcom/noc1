<?php

declare(strict_types=1);

/**
 * Bootstrap: env, autoloading, error handling, timezone.
 * Use composer autoloader if available, else fall back to a minimal PSR-4 loader.
 */

define('BASE_PATH', __DIR__);

require BASE_PATH . '/app/Core/Env.php';
\App\Core\Env::load(BASE_PATH . '/.env');

if (is_file(BASE_PATH . '/vendor/autoload.php')) {
    require BASE_PATH . '/vendor/autoload.php';
} else {
    // Minimal PSR-4 autoloader for the "App\" namespace.
    spl_autoload_register(static function (string $class): void {
        $prefix = 'App\\';
        if (!str_starts_with($class, $prefix)) {
            return;
        }
        $relative = substr($class, strlen($prefix));
        $file = BASE_PATH . '/app/' . str_replace('\\', '/', $relative) . '.php';
        if (is_file($file)) {
            require $file;
        }
    });
    require BASE_PATH . '/app/Core/helpers.php';
}

date_default_timezone_set((string) \App\Core\Env::get('APP_TIMEZONE', 'Asia/Jakarta'));

$debug = \App\Core\Env::bool('APP_DEBUG', false);
error_reporting($debug ? E_ALL : E_ALL & ~E_DEPRECATED & ~E_NOTICE);
ini_set('display_errors', $debug ? '1' : '0');

set_exception_handler(static function (\Throwable $e) use ($debug): void {
    $isApi = str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api');
    $payload = [
        'success' => false,
        'message' => $debug ? $e->getMessage() : 'Internal Server Error',
    ];
    if ($debug) {
        $payload['trace'] = $e->getTraceAsString();
    }
    http_response_code(500);
    if ($isApi) {
        header('Content-Type: application/json');
        echo json_encode($payload);
    } else {
        echo '<h1>500 — Server Error</h1>';
        if ($debug) {
            echo '<pre>' . htmlspecialchars($e->getMessage() . "\n" . $e->getTraceAsString()) . '</pre>';
        }
    }
    exit;
});
