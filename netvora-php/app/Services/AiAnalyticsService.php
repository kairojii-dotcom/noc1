<?php

declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\Env;

/**
 * AI Analytics — Predictive Alarm & Root Cause Analysis (Enterprise feature).
 * Uses OpenAI Chat Completions over the tenant's recent telemetry & alerts.
 */
final class AiAnalyticsService
{
    public function analyze(string $tenantId, string $mode = 'rca'): array
    {
        if (!Env::bool('AI_ENABLED', false)) {
            throw new \RuntimeException('AI Analytics dinonaktifkan', 400);
        }

        $context = $this->buildContext($tenantId);

        $system = match ($mode) {
            'predictive' => 'You are a senior ISP NOC engineer. Predict likely failures in the next 24h from the telemetry. Reply in Indonesian. Return JSON with keys: risk_level, predictions[], recommendations[].',
            'capacity'   => 'You are a network capacity planner. Assess capacity headroom and growth. Reply in Indonesian. Return JSON with keys: utilization, bottlenecks[], recommendations[].',
            default      => 'You are a network root-cause analysis expert. Given alerts and metrics, identify the most probable root cause. Reply in Indonesian. Return JSON with keys: root_cause, confidence, evidence[], remediation[].',
        };

        $reply = $this->callOpenAI($system, json_encode($context, JSON_UNESCAPED_UNICODE));
        $parsed = json_decode($reply, true);

        return [
            'mode'     => $mode,
            'result'   => is_array($parsed) ? $parsed : ['raw' => $reply],
            'analyzed' => $context['summary'] ?? null,
        ];
    }

    private function buildContext(string $tenantId): array
    {
        $alerts = Database::select(
            "SELECT severity, type, source, message, created_at FROM alerts
             WHERE tenant_id=:t AND is_resolved=false ORDER BY created_at DESC LIMIT 30",
            [':t' => $tenantId]
        );
        $routers = Database::select(
            "SELECT name, status, cpu_load, mem_usage FROM routers WHERE tenant_id=:t LIMIT 50",
            [':t' => $tenantId]
        );
        $onuLoss = Database::scalar(
            "SELECT round(100.0 * count(*) FILTER (WHERE status IN ('offline','los')) / NULLIF(count(*),0), 2)
             FROM onus WHERE tenant_id=:t",
            [':t' => $tenantId]
        );

        return [
            'alerts'        => $alerts,
            'routers'       => $routers,
            'onu_loss_pct'  => $onuLoss,
            'summary'       => sprintf('%d active alerts, %d routers, ONU loss %s%%', count($alerts), count($routers), (string) $onuLoss),
        ];
    }

    private function callOpenAI(string $system, string $user): string
    {
        $key   = (string) Env::get('OPENAI_API_KEY');
        $model = (string) Env::get('OPENAI_MODEL', 'gpt-4o-mini');
        if ($key === '') {
            throw new \RuntimeException('OPENAI_API_KEY belum dikonfigurasi', 500);
        }

        $payload = [
            'model'           => $model,
            'messages'        => [
                ['role' => 'system', 'content' => $system],
                ['role' => 'user', 'content' => $user],
            ],
            'temperature'     => 0.2,
            'response_format' => ['type' => 'json_object'],
        ];

        $ch = curl_init('https://api.openai.com/v1/chat/completions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                "Authorization: Bearer $key",
            ],
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $errc = curl_error($ch);
        curl_close($ch);

        if ($res === false) {
            throw new \RuntimeException('OpenAI request failed: ' . $errc, 502);
        }
        if ($code >= 400) {
            throw new \RuntimeException('OpenAI error: ' . $res, 502);
        }

        $data = json_decode($res, true);
        return $data['choices'][0]['message']['content'] ?? '{}';
    }
}
