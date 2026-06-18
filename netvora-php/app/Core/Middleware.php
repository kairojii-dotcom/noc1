<?php

declare(strict_types=1);

namespace App\Core;

use App\Repositories\UserRepository;

/**
 * Middleware resolver. Supported tokens:
 *   - "auth"                  : require a valid access token
 *   - "role:super_admin|owner": require one of the listed roles
 *   - "perm:tenant.create"    : require a specific RBAC permission
 *   - "tenant"                : require + bind tenant context (RLS)
 *   - "ratelimit"             : per-IP rate limiting
 */
final class Middleware
{
    public static function run(string $token, Request $request): void
    {
        [$name, $arg] = array_pad(explode(':', $token, 2), 2, null);

        match ($name) {
            'auth'      => self::auth($request),
            'role'      => self::role($request, $arg ?? ''),
            'perm'      => self::permission($request, $arg ?? ''),
            'tenant'    => self::tenant($request),
            'ratelimit' => self::rateLimit($request),
            default     => null,
        };
    }

    private static function auth(Request $request): void
    {
        if ($request->auth !== null) {
            return; // already authenticated by a previous middleware
        }
        $token = $request->bearerToken();
        if (!$token) {
            Response::error('Unauthorized: missing token', 401);
        }
        [$valid, $payload, $err] = Jwt::decode($token);
        if (!$valid) {
            Response::error('Unauthorized: ' . $err, 401);
        }
        if (($payload['type'] ?? '') !== 'access') {
            Response::error('Unauthorized: invalid token type', 401);
        }
        $request->auth = $payload;
    }

    private static function role(Request $request, string $arg): void
    {
        self::auth($request);
        $allowed = array_map('trim', explode('|', $arg));
        if (!in_array($request->role(), $allowed, true)) {
            Response::error('Forbidden: insufficient role', 403);
        }
    }

    private static function permission(Request $request, string $permission): void
    {
        self::auth($request);
        // super_admin bypasses all permission checks
        if ($request->role() === 'super_admin') {
            return;
        }
        $perms = $request->auth['perms'] ?? [];
        if (!in_array($permission, $perms, true) && !in_array('*', $perms, true)) {
            Response::error("Forbidden: missing permission '$permission'", 403);
        }
    }

    private static function tenant(Request $request): void
    {
        self::auth($request);
        $tenantId = $request->tenantId();
        // super_admin can operate cross-tenant; others must be scoped
        if ($request->role() !== 'super_admin' && !$tenantId) {
            Response::error('Forbidden: tenant scope required', 403);
        }
        Database::setTenantContext($tenantId, $request->role());
    }

    private static function rateLimit(Request $request): void
    {
        $limit  = Env::int('RATE_LIMIT_PER_MIN', 120);
        $bucket = sys_get_temp_dir() . '/nv_rl_' . md5($request->ip() . date('YmdHi'));
        $count  = is_file($bucket) ? (int) file_get_contents($bucket) : 0;
        if ($count >= $limit) {
            Response::error('Too Many Requests', 429, ['retry_after' => 60]);
        }
        file_put_contents($bucket, (string) ($count + 1), LOCK_EX);
    }
}
