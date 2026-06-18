<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

final class HealthController
{
    public function check(Request $request): void
    {
        $db = 'down';
        try {
            Database::scalar('SELECT 1');
            $db = 'up';
        } catch (\Throwable) {
            $db = 'down';
        }

        Response::json([
            'status'  => $db === 'up' ? 'healthy' : 'degraded',
            'service' => 'NETVORA NOC API',
            'db'      => $db,
            'time'    => now(),
        ], $db === 'up' ? 200 : 503);
    }
}
