<?php

declare(strict_types=1);

namespace App\Controllers\Api;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuthService;

final class AuthController extends Controller
{
    public function __construct(private AuthService $auth = new AuthService())
    {
    }

    public function login(Request $request): void
    {
        $data = $this->validate($request, [
            'email'    => 'required|email',
            'password' => 'required|min:6',
        ]);

        try {
            $result = $this->auth->login(
                $data['email'],
                $data['password'],
                $request->input('domain'),
                $request->ip(),
                $request->header('user-agent')
            );
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 401);
        }

        Response::success($result, 'Login berhasil');
    }

    public function refresh(Request $request): void
    {
        $token = $request->input('refresh_token') ?? '';
        if ($token === '') {
            Response::error('refresh_token wajib diisi', 422);
        }
        try {
            $result = $this->auth->refresh($token);
        } catch (\RuntimeException $e) {
            Response::error($e->getMessage(), $e->getCode() ?: 401);
        }
        Response::success($result, 'Token diperbarui');
    }

    public function logout(Request $request): void
    {
        $token = $request->input('refresh_token') ?? '';
        if ($token !== '') {
            $this->auth->logout($token);
        }
        Response::success(null, 'Logout berhasil');
    }

    public function me(Request $request): void
    {
        Response::success([
            'id'        => $request->userId(),
            'name'      => $request->auth['name'] ?? null,
            'email'     => $request->auth['email'] ?? null,
            'role'      => $request->role(),
            'tenant_id' => $request->tenantId(),
            'perms'     => $request->auth['perms'] ?? [],
        ]);
    }
}
