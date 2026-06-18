<?php

declare(strict_types=1);

/**
 * Global helper functions (autoloaded via composer "files").
 * Declared in the GLOBAL namespace so they resolve everywhere.
 */

use App\Core\Env;
use App\Core\View;

if (!function_exists('config')) {
    function config(string $key, mixed $default = null): mixed
    {
        return Env::get($key, $default);
    }
}

if (!function_exists('view')) {
    function view(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        return View::render($template, $data, $layout);
    }
}

if (!function_exists('e')) {
    function e(mixed $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}

if (!function_exists('now')) {
    function now(): string
    {
        return (new DateTimeImmutable('now', new DateTimeZone('UTC')))->format('Y-m-d H:i:sP');
    }
}

if (!function_exists('uuid')) {
    function uuid(): string
    {
        $d = random_bytes(16);
        $d[6] = chr((ord($d[6]) & 0x0f) | 0x40);
        $d[8] = chr((ord($d[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($d), 4));
    }
}

if (!function_exists('rupiah')) {
    function rupiah(int|float $n): string
    {
        return 'Rp ' . number_format((float) $n, 0, ',', '.');
    }
}
