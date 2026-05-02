<?php

namespace App\Support\Auth;

use App\Models\User;
use Illuminate\Support\Str;
use RuntimeException;
use UnexpectedValueException;

class JwtTokenService
{
    public function issue(User $user): array
    {
        $now = time();
        $ttl = (int) config('auth.jwt.access_ttl', 900);
        $expiresAt = $now + $ttl;
        $jti = (string) Str::uuid();

        $claims = [
            'iss' => config('app.url'),
            'sub' => (string) $user->getKey(),
            'iat' => $now,
            'nbf' => $now,
            'exp' => $expiresAt,
            'jti' => $jti,
            'roles' => $user->roles()->pluck('slug')->values()->all(),
        ];

        return [
            'access_token' => $this->encode($claims),
            'token_type' => 'Bearer',
            'expires_in' => $ttl,
            'expires_at' => date(DATE_ATOM, $expiresAt),
            'jti' => $jti,
        ];
    }

    public function decode(string $token): array
    {
        $parts = explode('.', $token);

        if (count($parts) !== 3) {
            throw new UnexpectedValueException('Malformed token.');
        }

        [$encodedHeader, $encodedPayload, $encodedSignature] = $parts;
        $header = $this->jsonDecode($this->base64UrlDecode($encodedHeader));
        $payload = $this->jsonDecode($this->base64UrlDecode($encodedPayload));

        if (($header['alg'] ?? null) !== 'HS256') {
            throw new UnexpectedValueException('Unsupported token algorithm.');
        }

        $expectedSignature = $this->base64UrlEncode(
            hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret(), true)
        );

        if (! hash_equals($expectedSignature, $encodedSignature)) {
            throw new UnexpectedValueException('Invalid token signature.');
        }

        $now = time();

        if (! isset($payload['sub'], $payload['exp']) || (int) $payload['exp'] <= $now) {
            throw new UnexpectedValueException('Expired token.');
        }

        if (isset($payload['nbf']) && (int) $payload['nbf'] > $now) {
            throw new UnexpectedValueException('Token is not active yet.');
        }

        return $payload;
    }

    private function encode(array $claims): string
    {
        $header = ['typ' => 'JWT', 'alg' => 'HS256'];
        $encodedHeader = $this->base64UrlEncode(json_encode($header, JSON_THROW_ON_ERROR));
        $encodedPayload = $this->base64UrlEncode(json_encode($claims, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $encodedHeader.'.'.$encodedPayload, $this->secret(), true);

        return $encodedHeader.'.'.$encodedPayload.'.'.$this->base64UrlEncode($signature);
    }

    private function secret(): string
    {
        $secret = (string) config('auth.jwt.secret', config('app.key'));

        if (str_starts_with($secret, 'base64:')) {
            $decoded = base64_decode(substr($secret, 7), true);
            $secret = $decoded === false ? '' : $decoded;
        }

        if ($secret === '') {
            throw new RuntimeException('JWT secret is not configured.');
        }

        return $secret;
    }

    private function jsonDecode(string $json): array
    {
        $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($decoded)) {
            throw new UnexpectedValueException('Invalid token JSON.');
        }

        return $decoded;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        $decoded = base64_decode(strtr($value, '-_', '+/'), true);

        if ($decoded === false) {
            throw new UnexpectedValueException('Invalid token encoding.');
        }

        return $decoded;
    }
}
