<?php

declare(strict_types=1);

namespace App\Services\Acs;

use App\Core\Database;

/**
 * ACS TR-069 server logic: CWMP session handling + task queue + provisioning.
 */
final class AcsService
{
    /**
     * Handle one CWMP HTTP request in a session.
     * @return array{xml:string, status:int, device_id:?string}
     */
    public function handleCwmp(string $tenantId, string $rawXml, ?string $cookieDeviceId): array
    {
        Database::setTenantContext($tenantId, 'super_admin'); // trusted server context
        $method = Tr069::detectMethod($rawXml);

        if ($method === 'Inform') {
            $info = Tr069::parseInform($rawXml);
            $device = $this->upsertDevice($tenantId, $info);
            return ['xml' => Tr069::informResponse(), 'status' => 200, 'device_id' => $device['id']];
        }

        $device = $cookieDeviceId
            ? Database::selectOne("SELECT * FROM acs_devices WHERE id = :id AND tenant_id = :t", [':id' => $cookieDeviceId, ':t' => $tenantId])
            : null;

        // CPE returned an RPC response → close out the sent task
        if ($method && str_ends_with($method, 'Response') && $method !== 'InformResponse' && $device) {
            $this->completeSentTask($device, $method, $rawXml);
        }

        // Send the next queued task, if any
        if ($device) {
            $next = $this->nextTask($device['id']);
            if ($next) {
                $xml = $this->buildTaskRpc($next);
                Database::execute("UPDATE acs_tasks SET status='sent' WHERE id = :id", [':id' => $next['id']]);
                return ['xml' => $xml, 'status' => 200, 'device_id' => $device['id']];
            }
        }

        // Nothing to do → end session
        return ['xml' => '', 'status' => 204, 'device_id' => $device['id'] ?? null];
    }

    private function upsertDevice(string $tenantId, array $info): array
    {
        $serial = $info['serial'] ?: ('UNKNOWN-' . substr(md5(json_encode($info)), 0, 8));
        $vendor = strtolower($info['manufacturer'] ?: '');

        $device = Database::insertReturning(
            "INSERT INTO acs_devices (tenant_id, serial, oui, product_class, manufacturer, vendor, status, last_inform)
             VALUES (:t, :s, :oui, :pc, :mf, :v, 'online', now())
             ON CONFLICT (tenant_id, serial) DO UPDATE
               SET status='online', last_inform=now(), oui=EXCLUDED.oui,
                   product_class=EXCLUDED.product_class, manufacturer=EXCLUDED.manufacturer
             RETURNING *",
            [':t' => $tenantId, ':s' => $serial, ':oui' => $info['oui'], ':pc' => $info['product_class'], ':mf' => $info['manufacturer'], ':v' => $vendor]
        );

        foreach ($info['parameters'] as $name => $value) {
            $this->storeParam($tenantId, $device['id'], $name, $value);
        }

        // Auto-provision on bootstrap (TR-069 "0 BOOTSTRAP")
        if (in_array('0 BOOTSTRAP', $info['events'], true)) {
            $this->enqueueAutoProvision($tenantId, $device);
        }
        return $device;
    }

    private function storeParam(string $tenantId, string $deviceId, string $name, string $value): void
    {
        Database::execute(
            "INSERT INTO acs_parameters (tenant_id, device_id, name, value, updated_at)
             VALUES (:t, :d, :n, :v, now())
             ON CONFLICT (device_id, name) DO UPDATE SET value=EXCLUDED.value, updated_at=now()",
            [':t' => $tenantId, ':d' => $deviceId, ':n' => $name, ':v' => $value]
        );
    }

    private function nextTask(string $deviceId): ?array
    {
        return Database::selectOne(
            "SELECT * FROM acs_tasks WHERE device_id = :d AND status='pending' ORDER BY created_at LIMIT 1",
            [':d' => $deviceId]
        );
    }

    private function buildTaskRpc(array $task): string
    {
        $p = json_decode($task['payload'], true) ?: [];
        return match ($task['type']) {
            'reboot'        => Tr069::reboot(),
            'factory_reset' => Tr069::factoryReset(),
            'get_param'     => Tr069::getParameterValues($p['names'] ?? ['InternetGatewayDevice.DeviceInfo.SoftwareVersion']),
            'download'      => Tr069::download($p['url'] ?? '', $p['file_type'] ?? '1 Firmware Upgrade Image'),
            'wifi_config'   => Tr069::setParameterValues($this->wifiParams($p)),
            'wan_config'    => Tr069::setParameterValues($this->wanParams($p)),
            default         => Tr069::setParameterValues($this->genericParams($p)),
        };
    }

    private function completeSentTask(array $device, string $method, string $xml): void
    {
        $sent = Database::selectOne(
            "SELECT * FROM acs_tasks WHERE device_id = :d AND status='sent' ORDER BY created_at LIMIT 1",
            [':d' => $device['id']]
        );
        if (!$sent) {
            return;
        }
        $result = [];
        if ($method === 'GetParameterValuesResponse') {
            $result = Tr069::parseParameterValues($xml);
            foreach ($result as $n => $v) {
                $this->storeParam($device['tenant_id'], $device['id'], $n, (string) $v);
            }
        }
        Database::execute(
            "UPDATE acs_tasks SET status='done', executed_at=now(), result=:r WHERE id = :id",
            [':r' => json_encode($result ?: ['ok' => true]), ':id' => $sent['id']]
        );
    }

    private function enqueueAutoProvision(string $tenantId, array $device): void
    {
        $profile = Database::selectOne(
            "SELECT * FROM acs_provision_profiles WHERE tenant_id = :t AND (vendor = :v OR vendor IS NULL) ORDER BY vendor NULLS LAST LIMIT 1",
            [':t' => $tenantId, ':v' => $device['vendor']]
        );
        if (!$profile) {
            return;
        }
        $params = json_decode($profile['parameters'], true) ?: [];
        $this->createTask($tenantId, $device['id'], 'set_param', ['params' => $params]);
    }

    // ---- parameter mappers (TR-098 / TR-181 common paths) ----
    private function wifiParams(array $p): array
    {
        $out = [];
        if (isset($p['ssid']))     $out[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.SSID', $p['ssid'], 'xsd:string'];
        if (isset($p['password'])) $out[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.PreSharedKey.1.PreSharedKey', $p['password'], 'xsd:string'];
        if (isset($p['channel']))  $out[] = ['InternetGatewayDevice.LANDevice.1.WLANConfiguration.1.Channel', (string) $p['channel'], 'xsd:unsignedInt'];
        return $out;
    }

    private function wanParams(array $p): array
    {
        $out = [];
        $base = 'InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANPPPConnection.1';
        if (isset($p['pppoe_user'])) $out[] = ["$base.Username", $p['pppoe_user'], 'xsd:string'];
        if (isset($p['pppoe_pass'])) $out[] = ["$base.Password", $p['pppoe_pass'], 'xsd:string'];
        if (isset($p['vlan']))       $out[] = ['InternetGatewayDevice.WANDevice.1.WANConnectionDevice.1.WANIPConnection.1.X_VLAN', (string) $p['vlan'], 'xsd:unsignedInt'];
        return $out;
    }

    private function genericParams(array $p): array
    {
        $out = [];
        foreach (($p['params'] ?? []) as $item) {
            $out[] = [$item['name'], (string) $item['value'], $item['type'] ?? 'xsd:string'];
        }
        return $out;
    }

    // ---- REST helpers (called by AcsController) ----
    public function createTask(string $tenantId, string $deviceId, string $type, array $payload): array
    {
        return Database::insertReturning(
            "INSERT INTO acs_tasks (tenant_id, device_id, type, payload, status)
             VALUES (:t, :d, :ty, :p, 'pending') RETURNING *",
            [':t' => $tenantId, ':d' => $deviceId, ':ty' => $type, ':p' => json_encode($payload)]
        );
    }
}
