<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AiAnalyticsService;

final class AiController extends Controller
{
    public function __construct(private AiAnalyticsService $ai = new AiAnalyticsService())
    {
    }

    public function analyze(Request $request): void
    {
        $tenantId = $request->tenantId() ?? $request->query('tenant_id');
        if (!$tenantId) {
            Response::error('tenant_id diperlukan', 422);
        }
        $mode = (string) ($request->input('mode') ?? 'rca'); // rca | predictive | capacity
        try {
            $result = $this->ai->analyze($tenantId, $mode);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 500);
        }
        Response::success($result, 'Analisis AI selesai');
    }
}
