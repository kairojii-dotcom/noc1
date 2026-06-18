<?php

declare(strict_types=1);

namespace App\Services\Monitoring;

/**
 * Loads vendor OID templates from storage/oid_templates/*.json.
 * Adding a new vendor = drop a new JSON file. No core change required.
 */
final class OltOidService
{
    private string $dir;

    public function __construct(?string $dir = null)
    {
        $this->dir = $dir ?? (BASE_PATH . '/storage/oid_templates');
    }

    public function vendors(): array
    {
        $files = glob($this->dir . '/*.json') ?: [];
        return array_map(fn ($f) => basename($f, '.json'), $files);
    }

    public function template(string $vendor): array
    {
        $file = $this->dir . '/' . strtolower($vendor) . '.json';
        if (!is_file($file)) {
            throw new \RuntimeException("Template OID untuk vendor '$vendor' tidak ditemukan");
        }
        $data = json_decode((string) file_get_contents($file), true);
        if (!is_array($data)) {
            throw new \RuntimeException("Template OID '$vendor' tidak valid");
        }
        return $data;
    }

    /** Normalise a raw SNMP value using the template's value_maps. */
    public function normalize(array $template, string $metric, mixed $raw): mixed
    {
        $map = $template['value_maps'][$metric] ?? null;
        if (!$map) {
            return $raw;
        }
        if (isset($map['divider']) && is_numeric($raw)) {
            return round(((float) $raw) / (float) $map['divider'], 2);
        }
        if (array_key_exists((string) $raw, $map)) {
            return $map[(string) $raw];
        }
        return $raw;
    }
}
