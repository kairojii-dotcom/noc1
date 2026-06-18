<?php

declare(strict_types=1);

namespace App\Services\Billing;

use App\Core\Database;
use App\Repositories\AuditRepository;

/**
 * Billing orchestration: invoice generation, payment, auto suspend/unsuspend.
 */
final class BillingService
{
    public function __construct(private AuditRepository $audit = new AuditRepository())
    {
    }

    /** Generate a single invoice for a customer (manual or from subscription). */
    public function createInvoice(string $tenantId, array $data, ?string $actorId): array
    {
        $amount   = (float) ($data['amount'] ?? 0);
        $tax      = (float) ($data['tax'] ?? 0);
        $discount = (float) ($data['discount'] ?? 0);
        $total    = $amount + $tax - $discount;
        $number   = $data['number'] ?? ('INV-' . date('Ym') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6)));

        $invoice = Database::insertReturning(
            "INSERT INTO invoices (tenant_id, customer_id, subscription_id, number, amount, tax, discount, total, status, due_date)
             VALUES (:t, :c, :s, :n, :a, :tax, :d, :total, 'unpaid', :due) RETURNING *",
            [
                ':t' => $tenantId, ':c' => $data['customer_id'] ?? null, ':s' => $data['subscription_id'] ?? null,
                ':n' => $number, ':a' => $amount, ':tax' => $tax, ':d' => $discount, ':total' => $total,
                ':due' => $data['due_date'] ?? date('Y-m-d', strtotime('+7 days')),
            ]
        );
        $this->audit->log($tenantId, $actorId, 'invoice.create', 'invoice', $invoice['id'], ['number' => $number]);
        return $invoice;
    }

    /** Auto-generate invoices for all subscriptions due today (cron). */
    public function generateDueInvoices(string $tenantId): int
    {
        $due = Database::select(
            "SELECT s.* FROM subscriptions s
             WHERE s.tenant_id = :t AND s.status='active' AND s.next_due <= current_date
               AND NOT EXISTS (
                 SELECT 1 FROM invoices i WHERE i.subscription_id = s.id
                 AND date_trunc('month', i.created_at) = date_trunc('month', now()))",
            [':t' => $tenantId]
        );
        $count = 0;
        foreach ($due as $s) {
            $this->createInvoice($tenantId, [
                'customer_id'     => $s['customer_id'],
                'subscription_id' => $s['id'],
                'amount'          => $s['amount'],
            ], null);
            // advance next_due by one cycle
            $interval = ($s['cycle'] === 'yearly') ? '1 year' : '1 month';
            Database::execute(
                "UPDATE subscriptions SET next_due = next_due + interval '$interval' WHERE id = :id",
                [':id' => $s['id']]
            );
            $count++;
        }
        return $count;
    }

    /** Record a payment and, if it settles the invoice, mark paid + unsuspend customer. */
    public function recordPayment(string $tenantId, string $invoiceId, float $amount, string $method, ?string $reference, ?string $actorId, array $gatewayPayload = []): array
    {
        return Database::transaction(function () use ($tenantId, $invoiceId, $amount, $method, $reference, $actorId, $gatewayPayload) {
            $payment = Database::insertReturning(
                "INSERT INTO payments (tenant_id, invoice_id, amount, method, reference, status, paid_at, gateway_payload)
                 VALUES (:t, :i, :a, :m, :r, 'success', now(), :gp) RETURNING *",
                [':t' => $tenantId, ':i' => $invoiceId, ':a' => $amount, ':m' => $method, ':r' => $reference, ':gp' => json_encode($gatewayPayload)]
            );

            $invoice = Database::insertReturning(
                "UPDATE invoices SET status='paid', paid_at=now() WHERE id = :id AND tenant_id = :t RETURNING *",
                [':id' => $invoiceId, ':t' => $tenantId]
            );

            // Auto-unsuspend the customer when their invoice is paid
            if ($invoice && $invoice['customer_id']) {
                Database::execute(
                    "UPDATE customers SET status='active'
                     WHERE id = :c AND tenant_id = :t AND status IN ('suspend','isolir','expired')",
                    [':c' => $invoice['customer_id'], ':t' => $tenantId]
                );
            }
            $this->audit->log($tenantId, $actorId, 'payment.success', 'invoice', $invoiceId, ['amount' => $amount, 'method' => $method]);
            return ['payment' => $payment, 'invoice' => $invoice];
        });
    }

    /** Suspend customers whose invoices are overdue (cron). */
    public function autoSuspendOverdue(string $tenantId): int
    {
        // mark overdue first
        Database::execute(
            "UPDATE invoices SET status='overdue' WHERE tenant_id=:t AND status='unpaid' AND due_date < current_date",
            [':t' => $tenantId]
        );
        return Database::execute(
            "UPDATE customers SET status='isolir'
             WHERE tenant_id=:t AND status='active' AND id IN (
                 SELECT customer_id FROM invoices WHERE tenant_id=:t AND status='overdue' AND customer_id IS NOT NULL)",
            [':t' => $tenantId]
        );
    }

    /** Resolve a webhook to mark the matching invoice paid. */
    public function handleWebhook(string $provider, array $body): array
    {
        $parsed = PaymentGatewayService::parseWebhook($provider, $body);
        if (!$parsed['external_id']) {
            throw new \RuntimeException('external_id tidak ditemukan di webhook', 422);
        }
        $invoice = Database::selectOne("SELECT * FROM invoices WHERE number = :n", [':n' => $parsed['external_id']]);
        if (!$invoice) {
            throw new \RuntimeException('Invoice tidak ditemukan: ' . $parsed['external_id'], 404);
        }
        if (!$parsed['paid']) {
            return ['status' => 'ignored', 'reason' => 'belum lunas'];
        }
        $this->recordPayment(
            $invoice['tenant_id'], $invoice['id'],
            $parsed['amount'] ?: (float) $invoice['total'],
            $provider, $parsed['external_id'], null, $body
        );
        return ['status' => 'paid', 'invoice' => $invoice['number']];
    }
}
