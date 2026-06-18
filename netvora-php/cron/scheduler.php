<?php

declare(strict_types=1);

/**
 * NETVORA NOC — Cron scheduler / queue worker.
 *
 * Suggested crontab:
 *   * * * * *  php /var/www/cron/scheduler.php poll          >> /var/log/netvora-poll.log 2>&1
 *   0 2 * * *  php /var/www/cron/scheduler.php billing        >> /var/log/netvora-billing.log 2>&1
 *   0 * * * *  php /var/www/cron/scheduler.php cleanup        >> /var/log/netvora-clean.log 2>&1
 */

require __DIR__ . '/../bootstrap.php';

use App\Core\Database;
use App\Services\Monitoring\PollerService;

$task = $argv[1] ?? 'poll';
$start = microtime(true);
echo '[' . now() . "] task=$task starting\n";

switch ($task) {
    case 'poll':
        $summary = (new PollerService())->run();
        echo '  polled ' . json_encode($summary) . "\n";
        break;

    case 'billing':
        // Per-tenant: generate due invoices + auto-suspend overdue customers
        $billing = new \App\Services\Billing\BillingService();
        $genTotal = 0; $susTotal = 0;
        foreach (Database::select("SELECT id FROM tenants WHERE status='active'") as $t) {
            $genTotal += $billing->generateDueInvoices($t['id']);
            $susTotal += $billing->autoSuspendOverdue($t['id']);
        }
        echo "  generated $genTotal invoice(s), suspended $susTotal customer(s)\n";
        break;

    case 'cleanup':
        $del = Database::execute("DELETE FROM device_metrics WHERE ts < now() - interval '30 days'");
        Database::execute("DELETE FROM refresh_tokens WHERE expires_at < now() OR revoked=true");
        echo "  removed $del old metric rows\n";
        break;

    default:
        echo "  unknown task: $task\n";
        exit(1);
}

printf("[%s] task=%s done in %.2fs\n", now(), $task, microtime(true) - $start);
