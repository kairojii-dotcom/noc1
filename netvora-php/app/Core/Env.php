<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Minimal .env loader (no external dependency).
 */
final class Env
{
    private static array $vars = [];
    private static bool $loaded = false;

    public static function load(string $path): void
    {
        if (self::$loaded) {
            return;
        }
        self::$loaded = true;

        if (!is_file($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);

            // Strip inline comments (only when not quoted)
            if (!str_starts_with($value, '"') && !str_starts_with($value, "'")) {
                $value = preg_replace('/\s+#.*$/', '', $value) ?? $value;
                $value = trim($value);
            }
            // Strip surrounding quotes
            if (strlen($value) >= 2 && ($value[0] === '"' || $value[0] === "'")) {
                $value = substr($value, 1, -1);
            }

            self::$vars[$key] = $value;
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return self::$vars[$key] ?? ($_ENV[$key] ?? (getenv($key) ?: $default));
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $v = self::get($key);
        if ($v === null) {
            return $default;
        }
        return in_array(strtolower((string) $v), ['1', 'true', 'yes', 'on'], true);
    }

    public static function int(string $key, int $default = 0): int
    {
        $v = self::get($key);
        return $v === null ? $default : (int) $v;
    }
}
