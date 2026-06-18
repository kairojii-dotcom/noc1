<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class UserRepository extends BaseRepository
{
    protected string $table = 'users';
    protected bool $tenantScoped = true;

    public function findByEmail(string $email, ?string $tenantId = null): ?array
    {
        if ($tenantId === null) {
            // super admin lookup (tenant_id NULL) OR global email
            return Database::selectOne(
                "SELECT * FROM users WHERE email = :e ORDER BY tenant_id NULLS FIRST LIMIT 1",
                [':e' => $email]
            );
        }
        return Database::selectOne(
            "SELECT * FROM users WHERE email = :e AND tenant_id = :t",
            [':e' => $email, ':t' => $tenantId]
        );
    }

    public function findByDomain(string $email, string $domain): ?array
    {
        return Database::selectOne(
            "SELECT u.* FROM users u
             JOIN tenants t ON t.id = u.tenant_id
             WHERE u.email = :e AND t.domain = :d",
            [':e' => $email, ':d' => $domain]
        );
    }

    public function touchLogin(string $id): void
    {
        Database::execute("UPDATE users SET last_login_at = now() WHERE id = :id", [':id' => $id]);
    }

    public function withRole(string $id): ?array
    {
        return Database::selectOne(
            "SELECT u.*, r.permissions, r.name AS role_name
             FROM users u JOIN roles r ON r.code = u.role_code
             WHERE u.id = :id",
            [':id' => $id]
        );
    }
}
