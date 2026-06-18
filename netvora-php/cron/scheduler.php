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
        // Auto-generate monthly invoices for due subscriptions
        $rows = Database::execute(
            "INSERT INTO invoices (tenant_id, customer_id, number, amount, total, status, due_date)
             SELECT s.tenant_id, s.customer_id,
                    'INV-' || to_char(now(),'YYYYMM') || '-' || substr(md5(random()::text),1,6),
                    s.amount, s.amount, 'unpaid', s.next_due
             FROM subscriptions s
             WHERE s.status='active' AND s.next_due <= current_date
               AND NOT EXISTS (
                 SELECT 1 FROM invoices i WHERE i.customer_id=s.customer_id
                 AND date_trunc('month', i.created_at)=date_trunc('month', now()))"
        );
        echo "  generated $rows invoice(s)\n";
        // Auto suspend overdue customers
        $sus = Database::execute(
            "UPDATE customers SET status='isolir'
             WHERE id IN (SELECT customer_id FROM invoices WHERE status='overdue')"
        );
        echo "  suspended $sus customer(s)\n";
        // Mark overdue
        Database::execute("UPDATE invoices SET status='overdue' WHERE status='unpaid' AND due_date < current_date");
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
