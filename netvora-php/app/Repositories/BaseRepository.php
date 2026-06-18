<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;

/**
 * Generic table repository implementing common CRUD with optional tenant scoping.
 */
abstract class BaseRepository
{
    protected string $table;
    protected bool $tenantScoped = true;

    public function find(string $id, ?string $tenantId = null): ?array
    {
        $sql = "SELECT * FROM {$this->table} WHERE id = :id";
        $params = [':id' => $id];
        if ($this->tenantScoped && $tenantId !== null) {
            $sql .= " AND tenant_id = :t";
            $params[':t'] = $tenantId;
        }
        return Database::selectOne($sql, $params);
    }

    public function all(?string $tenantId = null, int $limit = 100, int $offset = 0): array
    {
        $sql = "SELECT * FROM {$this->table}";
        $params = [];
        if ($this->tenantScoped && $tenantId !== null) {
            $sql .= " WHERE tenant_id = :t";
            $params[':t'] = $tenantId;
        }
        $sql .= " ORDER BY created_at DESC LIMIT :lim OFFSET :off";
        $params[':lim'] = $limit;
        $params[':off'] = $offset;
        return Database::select($sql, $params);
    }

    public function count(?string $tenantId = null, array $where = []): int
    {
        $sql = "SELECT count(*) FROM {$this->table}";
        $params = [];
        $conds = [];
        if ($this->tenantScoped && $tenantId !== null) {
            $conds[] = "tenant_id = :t";
            $params[':t'] = $tenantId;
        }
        foreach ($where as $col => $val) {
            $key = ':w_' . $col;
            $conds[] = "$col = $key";
            $params[$key] = $val;
        }
        if ($conds) {
            $sql .= ' WHERE ' . implode(' AND ', $conds);
        }
        return (int) Database::scalar($sql, $params);
    }

    public function create(array $data): array
    {
        $cols = array_keys($data);
        $placeholders = array_map(fn ($c) => ":$c", $cols);
        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s) RETURNING *",
            $this->table,
            implode(', ', $cols),
            implode(', ', $placeholders)
        );
        $params = [];
        foreach ($data as $k => $v) {
            $params[":$k"] = $v;
        }
        return Database::insertReturning($sql, $params) ?? [];
    }

    public function update(string $id, array $data, ?string $tenantId = null): ?array
    {
        if (!$data) {
            return $this->find($id, $tenantId);
        }
        $sets = [];
        $params = [':id' => $id];
        foreach ($data as $k => $v) {
            $sets[] = "$k = :$k";
            $params[":$k"] = $v;
        }
        $sql = sprintf("UPDATE %s SET %s WHERE id = :id", $this->table, implode(', ', $sets));
        if ($this->tenantScoped && $tenantId !== null) {
            $sql .= " AND tenant_id = :t";
            $params[':t'] = $tenantId;
        }
        $sql .= " RETURNING *";
        return Database::insertReturning($sql, $params);
    }

    public function delete(string $id, ?string $tenantId = null): bool
    {
        $sql = "DELETE FROM {$this->table} WHERE id = :id";
        $params = [':id' => $id];
        if ($this->tenantScoped && $tenantId !== null) {
            $sql .= " AND tenant_id = :t";
            $params[':t'] = $tenantId;
        }
        return Database::execute($sql, $params) > 0;
    }
}
