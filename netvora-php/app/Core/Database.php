<?php

declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;

/**
 * PDO wrapper for Supabase PostgreSQL with a tiny query helper layer.
 * Single shared connection per request (PHP-FPM friendly).
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function connection(): PDO
    {
        if (self::$pdo instanceof PDO) {
            return self::$pdo;
        }

        $host    = (string) Env::get('DB_HOST');
        $port    = (string) Env::get('DB_PORT', '5432');
        $db      = (string) Env::get('DB_NAME', 'postgres');
        $user    = (string) Env::get('DB_USER', 'postgres');
        $pass    = (string) Env::get('DB_PASSWORD', '');
        $sslmode = (string) Env::get('DB_SSLMODE', 'require');

        $dsn = sprintf(
            'pgsql:host=%s;port=%s;dbname=%s;sslmode=%s',
            $host,
            $port,
            $db,
            $sslmode
        );

        try {
            self::$pdo = new PDO($dsn, $user, $pass, [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_PERSISTENT         => false,
            ]);
            self::$pdo->exec("SET TIME ZONE 'UTC'");
        } catch (PDOException $e) {
            throw new \RuntimeException('Database connection failed: ' . $e->getMessage(), 500, $e);
        }

        return self::$pdo;
    }

    public static function select(string $sql, array $params = []): array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll();
    }

    public static function selectOne(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function execute(string $sql, array $params = []): int
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->rowCount();
    }

    /** INSERT ... RETURNING * helper. */
    public static function insertReturning(string $sql, array $params = []): ?array
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        $row = $stmt->fetch();
        return $row ?: null;
    }

    public static function scalar(string $sql, array $params = []): mixed
    {
        $stmt = self::connection()->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchColumn();
    }

    public static function transaction(callable $fn): mixed
    {
        $pdo = self::connection();
        $pdo->beginTransaction();
        try {
            $result = $fn($pdo);
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /**
     * Set the current tenant context for Row Level Security policies.
     * Postgres reads this via current_setting('app.current_tenant').
     */
    public static function setTenantContext(?string $tenantId, ?string $role = null): void
    {
        $pdo = self::connection();
        $pdo->prepare("SELECT set_config('app.current_tenant', :t, false)")
            ->execute([':t' => $tenantId ?? '']);
        if ($role !== null) {
            $pdo->prepare("SELECT set_config('app.current_role', :r, false)")
                ->execute([':r' => $role]);
        }
    }
}
