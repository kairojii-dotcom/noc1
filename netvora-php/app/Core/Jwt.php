<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Stateless JWT (HS256) — access + refresh tokens.
 */
final class Jwt
{
    public static function encode(array $payload, int $ttl): string
    {
        $secret = (string) Env::get('JWT_SECRET');
        $now = time();

        $payload = array_merge([
            'iss' => Env::get('JWT_ISSUER', 'netvora-noc'),
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => bin2hex(random_bytes(16)),
        ], $payload);

        $header = ['alg' => 'HS256', 'typ' => 'JWT'];

        $segments = [
            self::b64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            self::b64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];
        $signingInput = implode('.', $segments);
        $signature = hash_hmac('sha256', $signingInput, $secret, true);
        $segments[] = self::b64UrlEncode($signature);

        return implode('.', $segments);
    }

    /** @return array{0:bool,1:?array,2:?string} [valid, payload, error] */
    public static function decode(string $jwt): array
    {
        $secret = (string) Env::get('JWT_SECRET');
        $parts = explode('.', $jwt);
        if (count($parts) !== 3) {
            return [false, null, 'malformed_token'];
        }
        [$h, $p, $s] = $parts;

        $expected = self::b64UrlEncode(hash_hmac('sha256', "$h.$p", $secret, true));
        if (!hash_equals($expected, $s)) {
            return [false, null, 'invalid_signature'];
        }

        $payload = json_decode(self::b64UrlDecode($p), true);
        if (!is_array($payload)) {
            return [false, null, 'invalid_payload'];
        }
        if (isset($payload['exp']) && time() >= (int) $payload['exp']) {
            return [false, null, 'token_expired'];
        }

        return [true, $payload, null];
    }

    public static function issuePair(array $claims): array
    {
        return [
            'access_token'  => self::encode(array_merge($claims, ['type' => 'access']), Env::int('JWT_ACCESS_TTL', 900)),
            'refresh_token' => self::encode(array_merge($claims, ['type' => 'refresh']), Env::int('JWT_REFRESH_TTL', 2592000)),
            'token_type'    => 'Bearer',
            'expires_in'    => Env::int('JWT_ACCESS_TTL', 900),
        ];
    }

    private static function b64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private static function b64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/')) ?: '';
    }
}
