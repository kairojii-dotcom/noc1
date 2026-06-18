<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

final class TenantRepository extends BaseRepository
{
    protected string $table = 'tenants';
    protected bool $tenantScoped = false;

    public function paginate(int $limit, int $offset, ?string $search, ?string $status, ?string $package): array
    {
        $conds = [];
        $params = [];
        if ($search) {
            $conds[] = "(t.name ILIKE :s OR t.domain ILIKE :s OR t.email ILIKE :s)";
            $params[':s'] = "%$search%";
        }
        if ($status) {
            $conds[] = "t.status = :st";
            $params[':st'] = $status;
        }
        if ($package) {
            $conds[] = "p.code = :pk";
            $params[':pk'] = $package;
        }
        $where = $conds ? ('WHERE ' . implode(' AND ', $conds)) : '';

        $total = (int) Database::scalar(
            "SELECT count(*) FROM tenants t LEFT JOIN packages p ON p.id=t.package_id $where",
            $params
        );

        $rows = Database::select(
            "SELECT t.*, p.code AS package_code, p.name AS package_name,
                    (SELECT count(*) FROM customers c WHERE c.tenant_id=t.id) AS customer_count,
                    (SELECT email FROM users u WHERE u.tenant_id=t.id AND u.role_code='owner' LIMIT 1) AS admin_email
             FROM tenants t LEFT JOIN packages p ON p.id=t.package_id
             $where ORDER BY t.created_at DESC LIMIT :lim OFFSET :off",
            array_merge($params, [':lim' => $limit, ':off' => $offset])
        );

        return [$rows, $total];
    }

    public function findByDomain(string $domain): ?array
    {
        return Database::selectOne("SELECT * FROM tenants WHERE domain = :d", [':d' => $domain]);
    }

    public function topByCustomers(int $limit = 5): array
    {
        return Database::select("SELECT * FROM v_tenant_top LIMIT :l", [':l' => $limit]);
    }

    public function newPerDay(int $days = 30): array
    {
        return Database::select(
            "SELECT to_char(date_trunc('day', created_at),'YYYY-MM-DD') AS day, count(*) AS total
             FROM tenants
             WHERE created_at >= now() - (:d || ' days')::interval
             GROUP BY 1 ORDER BY 1",
            [':d' => $days]
        );
    }

    public function packageDistribution(): array
    {
        return Database::select(
            "SELECT p.code, p.name, count(t.id) AS total
             FROM packages p LEFT JOIN tenants t ON t.package_id=p.id
             GROUP BY p.code, p.name, p.price ORDER BY p.price"
        );
    }
}
