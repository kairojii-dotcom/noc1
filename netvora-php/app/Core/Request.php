<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Immutable view of the incoming HTTP request.
 */
final class Request
{
    public readonly string $method;
    public readonly string $path;
    private array $query;
    private array $body;
    private array $headers;
    /** Filled by the router after pattern matching. */
    public array $params = [];
    /** Authenticated user claims (set by AuthMiddleware). */
    public ?array $auth = null;

    public function __construct()
    {
        $this->method  = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
        $uri           = $_SERVER['REQUEST_URI'] ?? '/';
        $this->path    = rtrim(parse_url($uri, PHP_URL_PATH) ?: '/', '/') ?: '/';
        $this->query   = $_GET ?? [];
        $this->headers = self::collectHeaders();
        $this->body    = self::parseBody();
    }

    private static function collectHeaders(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (str_starts_with($key, 'HTTP_')) {
                $name = strtolower(str_replace('_', '-', substr($key, 5)));
                $headers[$name] = $value;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = $_SERVER['CONTENT_TYPE'];
        }
        return $headers;
    }

    private static function parseBody(): array
    {
        $raw = file_get_contents('php://input') ?: '';
        $ct  = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json') && $raw !== '') {
            $decoded = json_decode($raw, true);
            return is_array($decoded) ? $decoded : [];
        }
        return $_POST ?? [];
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->query[$key] ?? $default;
    }

    public function param(string $key, mixed $default = null): mixed
    {
        return $this->params[$key] ?? $default;
    }

    public function header(string $key, ?string $default = null): ?string
    {
        return $this->headers[strtolower($key)] ?? $default;
    }

    public function bearerToken(): ?string
    {
        $auth = $this->header('authorization');
        if ($auth && preg_match('/Bearer\s+(.+)/i', $auth, $m)) {
            return trim($m[1]);
        }
        return null;
    }

    public function ip(): string
    {
        return $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function tenantId(): ?string
    {
        return $this->auth['tenant_id'] ?? null;
    }

    public function role(): ?string
    {
        return $this->auth['role'] ?? null;
    }

    public function userId(): ?string
    {
        return $this->auth['sub'] ?? null;
    }
}
