<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;
use App\Core\Jwt;
use App\Repositories\AuditRepository;
use App\Repositories\UserRepository;

final class AuthService
{
    public function __construct(
        private UserRepository $users = new UserRepository(),
        private AuditRepository $audit = new AuditRepository(),
    ) {
    }

    /**
     * Authenticate by email + password. Optional tenant domain narrows scope.
     * @return array{user:array, tokens:array}
     */
    public function login(string $email, string $password, ?string $domain, string $ip, ?string $ua): array
    {
        $user = $domain
            ? $this->users->findByDomain($email, $domain)
            : $this->users->findByEmail($email);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            throw new \RuntimeException('Email atau password salah', 401);
        }
        if (!$user['is_active']) {
            throw new \RuntimeException('Akun dinonaktifkan', 403);
        }

        // Tenant status guard (super admin has no tenant)
        if ($user['tenant_id']) {
            $tenant = Database::selectOne("SELECT status FROM tenants WHERE id=:id", [':id' => $user['tenant_id']]);
            if (($tenant['status'] ?? '') !== 'active') {
                throw new \RuntimeException('Tenant tidak aktif (suspend/expired)', 403);
            }
        }

        $perms = $this->resolvePermissions($user['role_code']);
        $tokens = Jwt::issuePair([
            'sub'       => $user['id'],
            'tenant_id' => $user['tenant_id'],
            'role'      => $user['role_code'],
            'perms'     => $perms,
            'name'      => $user['name'],
            'email'     => $user['email'],
        ]);

        $this->persistRefresh($user['id'], $tokens['refresh_token'], $ip, $ua);
        $this->users->touchLogin($user['id']);
        $this->audit->log($user['tenant_id'], $user['id'], 'auth.login', 'user', $user['id'], [], $ip);

        unset($user['password_hash']);
        return ['user' => $user, 'tokens' => $tokens, 'permissions' => $perms];
    }

    public function refresh(string $refreshToken): array
    {
        [$valid, $payload, $err] = Jwt::decode($refreshToken);
        if (!$valid || ($payload['type'] ?? '') !== 'refresh') {
            throw new \RuntimeException('Refresh token tidak valid: ' . ($err ?? 'wrong_type'), 401);
        }

        $hash = hash('sha256', $refreshToken);
        $row = Database::selectOne(
            "SELECT * FROM refresh_tokens WHERE token_hash=:h AND revoked=false AND expires_at > now()",
            [':h' => $hash]
        );
        if (!$row) {
            throw new \RuntimeException('Refresh token sudah dicabut atau kedaluwarsa', 401);
        }

        $perms = $this->resolvePermissions($payload['role']);
        $tokens = Jwt::issuePair([
            'sub'       => $payload['sub'],
            'tenant_id' => $payload['tenant_id'],
            'role'      => $payload['role'],
            'perms'     => $perms,
            'name'      => $payload['name'] ?? '',
            'email'     => $payload['email'] ?? '',
        ]);

        // rotate: revoke old, store new
        Database::execute("UPDATE refresh_tokens SET revoked=true WHERE id=:id", [':id' => $row['id']]);
        $this->persistRefresh($payload['sub'], $tokens['refresh_token'], null, null);

        return ['tokens' => $tokens];
    }

    public function logout(string $refreshToken): void
    {
        $hash = hash('sha256', $refreshToken);
        Database::execute("UPDATE refresh_tokens SET revoked=true WHERE token_hash=:h", [':h' => $hash]);
    }

    private function resolvePermissions(string $roleCode): array
    {
        $row = Database::selectOne("SELECT permissions FROM roles WHERE code=:c", [':c' => $roleCode]);
        $perms = $row ? json_decode($row['permissions'], true) : [];
        return is_array($perms) ? $perms : [];
    }

    private function persistRefresh(string $userId, string $token, ?string $ip, ?string $ua): void
    {
        Database::execute(
            "INSERT INTO refresh_tokens (user_id, token_hash, expires_at, ip_address, user_agent)
             VALUES (:u, :h, now() + (:ttl || ' seconds')::interval, :ip, :ua)",
            [
                ':u'   => $userId,
                ':h'   => hash('sha256', $token),
                ':ttl' => (string) Env::int('JWT_REFRESH_TTL', 2592000),
                ':ip'  => $ip,
                ':ua'  => $ua,
            ]
        );
    }
}
