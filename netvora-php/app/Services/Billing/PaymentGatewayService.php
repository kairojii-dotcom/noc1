<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Core\Database;
use App\Core\Env;

/**
 * Payment gateway integration — Midtrans (Snap) & Xendit (Invoice).
 * Server keys are read per-tenant from tenants.billing_config (fallback .env).
 * Delivered as source: user supplies their own keys.
 */
final class PaymentGatewayService
{
    public function __construct(private string $tenantId)
    {
    }

    private function config(): array
    {
        $row = Database::selectOne("SELECT billing_config FROM tenants WHERE id = :id", [':id' => $this->tenantId]);
        $cfg = $row ? json_decode($row['billing_config'], true) : [];
        return is_array($cfg) ? $cfg : [];
    }

    /**
     * Create a hosted payment link for an invoice.
     * @return array{provider:string, url:string, external_id:string}
     */
    public function createPaymentLink(array $invoice, array $customer): array
    {
        $cfg = $this->config();
        $provider = $cfg['provider'] ?? 'midtrans';

        return match ($provider) {
            'xendit' => $this->xenditInvoice($invoice, $customer, $cfg),
            default  => $this->midtransSnap($invoice, $customer, $cfg),
        };
    }

    private function midtransSnap(array $invoice, array $customer, array $cfg): array
    {
        $serverKey = $cfg['midtrans_server_key'] ?? Env::get('MIDTRANS_SERVER_KEY');
        if (!$serverKey) {
            throw new \RuntimeException('Midtrans server key belum dikonfigurasi (Settings → Billing)', 400);
        }
        $isProd = (bool) ($cfg['production'] ?? false);
        $base = $isProd ? 'https://app.midtrans.com' : 'https://app.sandbox.midtrans.com';

        $payload = [
            'transaction_details' => [
                'order_id'     => $invoice['number'],
                'gross_amount' => (int) round((float) $invoice['total']),
            ],
            'customer_details' => [
                'first_name' => $customer['name'] ?? 'Customer',
                'email'      => $customer['email'] ?? null,
                'phone'      => $customer['phone'] ?? null,
            ],
        ];

        $res = $this->httpJson("$base/snap/v1/transactions", $payload, [
            'Authorization: Basic ' . base64_encode($serverKey . ':'),
        ]);

        return [
            'provider'    => 'midtrans',
            'url'         => $res['redirect_url'] ?? '',
            'external_id' => $invoice['number'],
        ];
    }

    private function xenditInvoice(array $invoice, array $customer, array $cfg): array
    {
        $secret = $cfg['xendit_secret_key'] ?? Env::get('XENDIT_SECRET_KEY');
        if (!$secret) {
            throw new \RuntimeException('Xendit secret key belum dikonfigurasi (Settings → Billing)', 400);
        }
        $payload = [
            'external_id'      => $invoice['number'],
            'amount'           => (int) round((float) $invoice['total']),
            'payer_email'      => $customer['email'] ?? 'noreply@netvora.id',
            'description'      => 'Invoice ' . $invoice['number'],
        ];
        $res = $this->httpJson('https://api.xendit.co/v2/invoices', $payload, [
            'Authorization: Basic ' . base64_encode($secret . ':'),
        ]);

        return [
            'provider'    => 'xendit',
            'url'         => $res['invoice_url'] ?? '',
            'external_id' => $res['id'] ?? $invoice['number'],
        ];
    }

    /**
     * Normalise a webhook body into [external_id, paid(bool), amount].
     */
    public static function parseWebhook(string $provider, array $body): array
    {
        if ($provider === 'xendit') {
            return [
                'external_id' => $body['external_id'] ?? null,
                'paid'        => ($body['status'] ?? '') === 'PAID',
                'amount'      => (float) ($body['amount'] ?? 0),
            ];
        }
        // midtrans
        $status = $body['transaction_status'] ?? '';
        return [
            'external_id' => $body['order_id'] ?? null,
            'paid'        => in_array($status, ['settlement', 'capture'], true),
            'amount'      => (float) ($body['gross_amount'] ?? 0),
        ];
    }

    private function httpJson(string $url, array $payload, array $headers): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
            CURLOPT_POSTFIELDS     => json_encode($payload),
        ]);
        $res  = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err  = curl_error($ch);
        curl_close($ch);

        if ($res === false) {
            throw new \RuntimeException("Gateway request failed: $err", 502);
        }
        $data = json_decode($res, true);
        if ($code >= 400) {
            $msg = $data['error_messages'][0] ?? ($data['message'] ?? $res);
            throw new \RuntimeException("Gateway error: $msg", 502);
        }
        return is_array($data) ? $data : [];
    }
}
