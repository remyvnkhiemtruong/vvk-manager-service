<?php

namespace App\Support\Auth;

use App\Models\RefreshToken;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RefreshTokenService
{
    public function create(User $user, ?Request $request = null): array
    {
        $plainToken = $this->newPlainToken();

        $refreshToken = RefreshToken::create([
            'user_id' => $user->id,
            'token_hash' => $this->hash($plainToken),
            'expires_at' => now()->addMinutes((int) config('auth.jwt.refresh_ttl', 43200)),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
        ]);

        return [$plainToken, $refreshToken];
    }

    public function rotate(string $plainToken, ?Request $request = null): ?array
    {
        return DB::transaction(function () use ($plainToken, $request): ?array {
            $current = RefreshToken::query()
                ->with('user.roles.permissions')
                ->where('token_hash', $this->hash($plainToken))
                ->lockForUpdate()
                ->first();

            if (! $current?->isUsable() || $current->user?->status !== 'active') {
                return null;
            }

            [$replacementPlainToken, $replacement] = $this->create($current->user, $request);

            $current->forceFill([
                'revoked_at' => now(),
                'replaced_by_id' => $replacement->id,
                'revoked_reason' => 'rotated',
                'last_used_at' => now(),
            ])->save();

            return [$replacementPlainToken, $replacement, $current->user];
        });
    }

    public function revokeForUser(User $user, ?string $plainToken = null, string $reason = 'logout'): void
    {
        $query = RefreshToken::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at');

        if ($plainToken !== null) {
            $query->where('token_hash', $this->hash($plainToken));
        }

        $query->update([
            'revoked_at' => now(),
            'revoked_reason' => $reason,
            'last_used_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function hash(string $plainToken): string
    {
        return hash('sha256', $plainToken);
    }

    private function newPlainToken(): string
    {
        return rtrim(strtr(base64_encode(random_bytes(64)), '+/', '-_'), '=');
    }
}
