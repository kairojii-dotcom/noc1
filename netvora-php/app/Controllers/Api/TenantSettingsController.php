<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Database;
use App\Core\Request;
use App\Core\Response;

/**
 * Tenant self-service profile & integration settings (SMTP, WhatsApp, Branding).
 */
final class TenantSettingsController extends Controller
{
    private const JSON_FIELDS = ['branding', 'smtp_config', 'wa_config', 'mikrotik_api', 'acs_api', 'billing_config'];
    private const TEXT_FIELDS = ['name', 'isp_name', 'address', 'phone_wa', 'email', 'logo_url', 'timezone', 'invoice_template'];

    public function show(Request $request): void
    {
        $tenantId = $request->tenantId();
        if (!$tenantId) {
            Response::error('Hanya untuk user tenant', 403);
        }
        $row = Database::selectOne("SELECT * FROM tenants WHERE id = :id", [':id' => $tenantId]);
        $row ? Response::success($row) : Response::error('Tenant tidak ditemukan', 404);
    }

    public function update(Request $request): void
    {
        $tenantId = $request->tenantId();
        if (!$tenantId) {
            Response::error('Hanya untuk user tenant', 403);
        }
        $data = $request->all();
        $sets = [];
        $params = [':id' => $tenantId];

        foreach (self::TEXT_FIELDS as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "$f = :$f";
                $params[":$f"] = $data[$f];
            }
        }
        foreach (self::JSON_FIELDS as $f) {
            if (array_key_exists($f, $data)) {
                $sets[] = "$f = :$f";
                $params[":$f"] = is_string($data[$f]) ? $data[$f] : json_encode($data[$f]);
            }
        }
        if (!$sets) {
            Response::error('Tidak ada field yang valid', 422);
        }
        $row = Database::insertReturning(
            "UPDATE tenants SET " . implode(', ', $sets) . " WHERE id = :id RETURNING *",
            $params
        );
        Response::success($row, 'Pengaturan disimpan');
    }
}
