<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;
use App\Services\Billing\BillingService;
use App\Services\Billing\PaymentGatewayService;

final class BillingController extends Controller
{
    public function __construct(private BillingService $billing = new BillingService())
    {
    }

    /** Create an invoice manually (tenant). */
    public function createInvoice(Request $request): void
    {
        $data = $this->validate($request, ['amount' => 'required|numeric|min:0']);
        $data = array_merge($request->all(), $data);
        $invoice = $this->billing->createInvoice($request->tenantId(), $data, $request->userId());
        Response::success($invoice, 'Invoice dibuat', 201);
    }

    /** Auto-generate invoices for all due subscriptions (tenant). */
    public function generate(Request $request): void
    {
        $count = $this->billing->generateDueInvoices($request->tenantId());
        Response::success(['generated' => $count], "$count invoice dibuat");
    }

    /** Record a manual payment for an invoice. */
    public function payManual(Request $request): void
    {
        $data = $this->validate($request, [
            'invoice_id' => 'required',
            'amount'     => 'required|numeric|min:0',
        ]);
        $res = $this->billing->recordPayment(
            $request->tenantId(), $data['invoice_id'], (float) $data['amount'],
            $request->input('method', 'manual'), $request->input('reference'), $request->userId()
        );
        Response::success($res, 'Pembayaran dicatat & pelanggan diaktifkan');
    }

    /** Create a hosted payment link (Midtrans / Xendit). */
    public function paymentLink(Request $request): void
    {
        $invoiceId = $request->input('invoice_id');
        $invoice = Database::selectOne(
            "SELECT * FROM invoices WHERE id = :id AND tenant_id = :t",
            [':id' => $invoiceId, ':t' => $request->tenantId()]
        );
        if (!$invoice) {
            Response::error('Invoice tidak ditemukan', 404);
        }
        $customer = $invoice['customer_id']
            ? Database::selectOne("SELECT * FROM customers WHERE id = :c", [':c' => $invoice['customer_id']])
            : [];

        $gw = new PaymentGatewayService($request->tenantId());
        try {
            $link = $gw->createPaymentLink($invoice, $customer ?? []);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
        Database::execute(
            "UPDATE invoices SET status='unpaid' WHERE id=:id", [':id' => $invoiceId]
        );
        Response::success($link, 'Link pembayaran dibuat');
    }

    /** Auto-suspend overdue customers (tenant trigger / cron). */
    public function autoSuspend(Request $request): void
    {
        $n = $this->billing->autoSuspendOverdue($request->tenantId());
        Response::success(['suspended' => $n], "$n pelanggan diisolir");
    }

    /** PUBLIC webhook from payment gateway. provider = midtrans|xendit */
    public function webhook(Request $request): void
    {
        $provider = $request->param('provider');
        try {
            $result = $this->billing->handleWebhook($provider, $request->all());
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 400);
        }
        Response::success($result, 'Webhook diproses');
    }
}
