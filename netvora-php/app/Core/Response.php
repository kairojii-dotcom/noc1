<?php

declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function json(mixed $data, int $status = 200, array $headers = []): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($headers as $k => $v) {
            header("$k: $v");
        }
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(mixed $data = null, string $message = 'OK', int $status = 200): never
    {
        self::json(['success' => true, 'message' => $message, 'data' => $data], $status);
    }

    public static function error(string $message, int $status = 400, mixed $errors = null): never
    {
        self::json(['success' => false, 'message' => $message, 'errors' => $errors], $status);
    }

    public static function paginated(array $items, int $total, int $page, int $perPage): never
    {
        self::json([
            'success' => true,
            'data'    => $items,
            'meta'    => [
                'total'     => $total,
                'page'      => $page,
                'per_page'  => $perPage,
                'last_page' => (int) ceil($total / max(1, $perPage)),
            ],
        ]);
    }

    public static function html(string $content, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        exit;
    }

    public static function redirect(string $to, int $status = 302): never
    {
        header("Location: $to", true, $status);
        exit;
    }
}
