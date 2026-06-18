<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Global CRUD controller for Super Admin (cross-tenant, no RLS scoping).
 * Whitelisted tables only.
 */
final class AdminResourceController extends Controller
{
    /** table => [searchable columns] */
    private const TABLES = [
        'users'         => ['name', 'email'],
        'packages'      => ['code', 'name'],
        'roles'         => ['code', 'name'],
        'audit_logs'    => ['action', 'entity'],
        'subscriptions' => ['package_name'],
        'invoices'      => ['number', 'status'],
        'payments'      => ['method', 'reference'],
    ];

    private function table(Request $request): string
    {
        $t = $request->param('resource');
        if (!isset(self::TABLES[$t])) {
            Response::error("Resource '$t' tidak dikenal", 404);
        }
        return $t;
    }

    public function index(Request $request): void
    {
        $table = $this->table($request);
        [$page, $perPage, $offset] = $this->paginationParams($request);
        $search = trim((string) $request->query('search', ''));

        $where = '';
        $params = [];
        if ($search !== '' && self::TABLES[$table]) {
            $clauses = [];
            foreach (self::TABLES[$table] as $i => $col) {
                $clauses[] = "$col ILIKE :s";
            }
            $where = 'WHERE ' . implode(' OR ', $clauses);
            $params[':s'] = "%$search%";
        }

        $total = (int) Database::scalar("SELECT count(*) FROM $table $where", $params);

        $select = '*';
        $join = '';
        if (in_array('tenant_id', $this->columns($table), true)) {
            $select = "$table.*, t.name AS tenant_name";
            $join = "LEFT JOIN tenants t ON t.id = $table.tenant_id";
            if ($where) {
                $where = str_replace('WHERE ', "WHERE ", $where);
            }
        }

        $rows = Database::select(
            "SELECT $select FROM $table $join $where ORDER BY {$table}.created_at DESC LIMIT :lim OFFSET :off",
            array_merge($params, [':lim' => $perPage, ':off' => $offset])
        );
        foreach ($rows as &$r) {
            unset($r['password_hash']);
        }
        Response::paginated($rows, $total, $page, $perPage);
    }

    public function store(Request $request): void
    {
        $table = $this->table($request);
        $data = $request->all();
        unset($data['id'], $data['created_at'], $data['updated_at'], $data['tenant_name']);
        if ($table === 'users' && !empty($data['password'])) {
            $data['password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
        }
        unset($data['password']);
        if ($table === 'roles' && isset($data['permissions']) && is_string($data['permissions'])) {
            // accept comma list or JSON
            $perms = json_decode($data['permissions'], true);
            $data['permissions'] = json_encode(is_array($perms) ? $perms : array_map('trim', explode(',', $data['permissions'])));
        }
        $row = $this->insert($table, $data);
        unset($row['password_hash']);
        Response::success($row, 'Data dibuat', 201);
    }

    public function update(Request $request): void
    {
        $table = $this->table($request);
        $data = $request->all();
        unset($data['id'], $data['created_at'], $data['updated_at'], $data['tenant_name']);
        if ($table === 'users') {
            if (!empty($data['password'])) {
                $data['password_hash'] = password_hash((string) $data['password'], PASSWORD_BCRYPT);
            }
            unset($data['password']);
        }
        if (!$data) {
            Response::error('Tidak ada data untuk diperbarui', 422);
        }
        $sets = [];
        $params = [':id' => $request->param('id')];
        foreach ($data as $k => $v) {
            $sets[] = "$k = :$k";
            $params[":$k"] = $v;
        }
        $row = Database::insertReturning(
            "UPDATE $table SET " . implode(', ', $sets) . " WHERE id = :id RETURNING *",
            $params
        );
        if ($row) {
            unset($row['password_hash']);
        }
        $row ? Response::success($row, 'Data diperbarui') : Response::error('Data tidak ditemukan', 404);
    }

    public function destroy(Request $request): void
    {
        $table = $this->table($request);
        $ok = Database::execute("DELETE FROM $table WHERE id = :id", [':id' => $request->param('id')]) > 0;
        $ok ? Response::success(null, 'Data dihapus') : Response::error('Data tidak ditemukan', 404);
    }

    private function insert(string $table, array $data): array
    {
        $cols = array_keys($data);
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) RETURNING *",
            $table,
            implode(', ', $cols),
            implode(', ', array_map(fn ($c) => ":$c", $cols))
        );
        $params = [];
        foreach ($data as $k => $v) {
            $params[":$k"] = $v;
        }
        return Database::insertReturning($sql, $params) ?? [];
    }

    private function columns(string $table): array
    {
        return array_column(
            Database::select(
                "SELECT column_name FROM information_schema.columns WHERE table_name = :t",
                [':t' => $table]
            ),
            'column_name'
        );
    }
}
