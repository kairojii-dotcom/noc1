<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class AuditRepository extends BaseRepository
{
    protected string $table = 'audit_logs';

    public function log(?string $tenantId, ?string $userId, string $action, ?string $entity = null, ?string $entityId = null, array $meta = [], ?string $ip = null): void
    {
        Database::execute(
            "INSERT INTO audit_logs (tenant_id, user_id, action, entity, entity_id, meta, ip_address)
             VALUES (:t, :u, :a, :e, :eid, :m, :ip)",
            [
                ':t'   => $tenantId,
                ':u'   => $userId,
                ':a'   => $action,
                ':e'   => $entity,
                ':eid' => $entityId,
                ':m'   => json_encode($meta),
                ':ip'  => $ip,
            ]
        );
    }
}
