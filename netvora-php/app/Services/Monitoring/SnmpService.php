<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

/**
 * Thin wrapper over PHP's net-snmp extension.
 * Requires ext-snmp (apt: php8.3-snmp). Falls back gracefully if unavailable.
 */
final class SnmpService
{
    public function __construct(
        private string $host,
        private string $community = 'public',
        private int $timeoutUs = 1_000_000,
        private int $retries = 2,
    ) {
    }

    public function available(): bool
    {
        return function_exists('snmp2_get');
    }

    /** Single OID GET. Returns null on failure. */
    public function get(string $oid): ?string
    {
        if (!$this->available()) {
            return null;
        }
        $prev = error_reporting(0);
        $val = snmp2_get($this->host, $this->community, $oid, $this->timeoutUs, $this->retries);
        error_reporting($prev);
        return $val === false ? null : $this->clean((string) $val);
    }

    /** WALK a subtree. Returns [index => value]. */
    public function walk(string $oid): array
    {
        if (!$this->available()) {
            return [];
        }
        $prev = error_reporting(0);
        $raw = snmp2_real_walk($this->host, $this->community, $oid, $this->timeoutUs, $this->retries);
        error_reporting($prev);
        if (!is_array($raw)) {
            return [];
        }
        $out = [];
        foreach ($raw as $key => $value) {
            $index = substr($key, strrpos($key, '.') + 1);
            $out[$index] = $this->clean((string) $value);
        }
        return $out;
    }

    public function reachable(): bool
    {
        return $this->get('.1.3.6.1.2.1.1.3.0') !== null; // sysUptime
    }

    private function clean(string $v): string
    {
        // Strip SNMP type prefixes like "STRING: ", "INTEGER: ", quotes
        $v = preg_replace('/^\w+:\s*/', '', $v) ?? $v;
        return trim($v, " \"\t\n\r");
    }
}
