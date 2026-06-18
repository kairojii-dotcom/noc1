<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Ultra-light PHP template renderer for the web (SSR) views.
 */
final class View
{
    private static string $basePath = __DIR__ . '/../Views';

    public static function render(string $template, array $data = [], ?string $layout = 'layouts/app'): string
    {
        $content = self::partial($template, $data);
        if ($layout === null) {
            return $content;
        }
        return self::partial($layout, array_merge($data, ['content' => $content]));
    }

    public static function partial(string $template, array $data = []): string
    {
        $file = self::$basePath . '/' . str_replace('.', '/', $template) . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException("View not found: $template");
        }
        extract($data, EXTR_SKIP);
        ob_start();
        include $file;
        return (string) ob_get_clean();
    }
}

/** Escape helper available in templates. */
function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
}
