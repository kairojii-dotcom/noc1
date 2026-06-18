<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Repositories\AuditRepository;
use App\Repositories\TenantRepository;

final class TenantService
{
    public function __construct(
        private TenantRepository $tenants = new TenantRepository(),
        private AuditRepository $audit = new AuditRepository(),
    ) {
    }

    public function list(int $page, int $perPage, ?string $search, ?string $status, ?string $package): array
    {
        $offset = ($page - 1) * $perPage;
        [$rows, $total] = $this->tenants->paginate($perPage, $offset, $search, $status, $package);
        return ['rows' => $rows, 'total' => $total];
    }

    /**
     * Create a tenant + its initial Owner user in one transaction.
     */
    public function create(array $data, ?string $actorId): array
    {
        return Database::transaction(function () use ($data, $actorId) {
            $packageId = Database::scalar(
                "SELECT id FROM packages WHERE code=:c",
                [':c' => $data['package'] ?? 'basic']
            );

            $tenant = $this->tenants->create([
                'name'       => $data['name'],
                'domain'     => $data['domain'],
                'isp_name'   => $data['isp_name'] ?? $data['name'],
                'email'      => $data['email'] ?? null,
                'phone_wa'   => $data['phone_wa'] ?? null,
                'address'    => $data['address'] ?? null,
                'package_id' => $packageId,
                'status'     => $data['status'] ?? 'active',
                'expired_at' => $data['expired_at'] ?? null,
                'timezone'   => $data['timezone'] ?? 'Asia/Jakarta',
                'logo_url'   => $data['logo_url'] ?? null,
            ]);

            // Owner account for the new tenant
            $ownerEmail = $data['admin_email'] ?? ('admin@' . $data['domain']);
            $ownerPass  = $data['admin_password'] ?? bin2hex(random_bytes(5));
            Database::execute(
                "INSERT INTO users (tenant_id, role_code, name, email, password_hash, is_active)
                 VALUES (:t, 'owner', :n, :e, :p, true)",
                [
                    ':t' => $tenant['id'],
                    ':n' => $data['admin_name'] ?? 'Owner',
                    ':e' => $ownerEmail,
                    ':p' => password_hash($ownerPass, PASSWORD_BCRYPT),
                ]
            );

            $this->audit->log(null, $actorId, 'tenant.create', 'tenant', $tenant['id'], ['domain' => $tenant['domain']]);

            $tenant['owner_email']    = $ownerEmail;
            $tenant['owner_password'] = $ownerPass; // returned once for handoff
            return $tenant;
        });
    }

    public function update(string $id, array $data, ?string $actorId): ?array
    {
        if (isset($data['package'])) {
            $data['package_id'] = Database::scalar("SELECT id FROM packages WHERE code=:c", [':c' => $data['package']]);
            unset($data['package']);
        }
        $tenant = $this->tenants->update($id, $data);
        $this->audit->log(null, $actorId, 'tenant.update', 'tenant', $id, $data);
        return $tenant;
    }

    public function setStatus(string $id, string $status, ?string $actorId): ?array
    {
        $tenant = $this->tenants->update($id, ['status' => $status]);
        $this->audit->log(null, $actorId, "tenant.$status", 'tenant', $id);
        return $tenant;
    }

    public function delete(string $id, ?string $actorId): bool
    {
        $ok = $this->tenants->delete($id);
        $this->audit->log(null, $actorId, 'tenant.delete', 'tenant', $id);
        return $ok;
    }

    public function find(string $id): ?array
    {
        return $this->tenants->find($id);
    }
}
