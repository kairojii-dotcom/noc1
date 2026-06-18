<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Tiny PSR-4 router with named path params and middleware pipeline.
 *
 *   $router->get('/api/tenants/{id}', [TenantController::class, 'show'], ['auth', 'role:super_admin']);
 */
final class Router
{
    /** @var array<int, array{method:string,regex:string,vars:array,handler:array,middleware:array}> */
    private array $routes = [];
    private string $prefix = '';
    private array $groupMiddleware = [];

    public function get(string $path, array $handler, array $mw = []): void    { $this->add('GET', $path, $handler, $mw); }
    public function post(string $path, array $handler, array $mw = []): void   { $this->add('POST', $path, $handler, $mw); }
    public function put(string $path, array $handler, array $mw = []): void    { $this->add('PUT', $path, $handler, $mw); }
    public function patch(string $path, array $handler, array $mw = []): void  { $this->add('PATCH', $path, $handler, $mw); }
    public function delete(string $path, array $handler, array $mw = []): void { $this->add('DELETE', $path, $handler, $mw); }

    public function group(string $prefix, array $middleware, callable $fn): void
    {
        $prevPrefix = $this->prefix;
        $prevMw     = $this->groupMiddleware;
        $this->prefix          = $prevPrefix . $prefix;
        $this->groupMiddleware = array_merge($prevMw, $middleware);
        $fn($this);
        $this->prefix          = $prevPrefix;
        $this->groupMiddleware = $prevMw;
    }

    private function add(string $method, string $path, array $handler, array $mw): void
    {
        $full = $this->prefix . $path;
        $vars = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', function ($m) use (&$vars) {
            $vars[] = $m[1];
            return '([^/]+)';
        }, $full);
        $regex = '#^' . rtrim($regex, '/') . '/?$#';

        $this->routes[] = [
            'method'     => $method,
            'regex'      => $regex,
            'vars'       => $vars,
            'handler'    => $handler,
            'middleware' => array_merge($this->groupMiddleware, $mw),
        ];
    }

    public function dispatch(Request $request): void
    {
        $path = $request->path;
        $allowed = [];

        foreach ($this->routes as $route) {
            if (!preg_match($route['regex'], $path, $matches)) {
                continue;
            }
            if ($route['method'] !== $request->method) {
                $allowed[] = $route['method'];
                continue;
            }

            array_shift($matches);
            foreach ($route['vars'] as $i => $name) {
                $request->params[$name] = $matches[$i] ?? null;
            }

            // Middleware pipeline
            foreach ($route['middleware'] as $mw) {
                Middleware::run($mw, $request);
            }

            [$class, $action] = $route['handler'];
            $controller = new $class();
            $controller->$action($request);
            return;
        }

        if ($allowed) {
            Response::error('Method Not Allowed', 405, ['allowed' => array_unique($allowed)]);
        }

        // Unknown route: API => JSON 404, else HTML 404
        if (str_starts_with($path, '/api')) {
            Response::error('Endpoint not found', 404);
        }
        Response::html('<h1>404 — Not Found</h1>', 404);
    }
}
