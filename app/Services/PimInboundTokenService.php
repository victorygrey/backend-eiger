<?php

namespace App\Services;

use App\Models\PimApiToken;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

class PimInboundTokenService
{
    /** @return array{token: string, expires_at: string, name: string} */
    public function issue(string $name, int $ttlMinutes = 120): array
    {
        $ttlMinutes = max(1, min($ttlMinutes, 10080));

        return $this->issueUntil($name, now()->addMinutes($ttlMinutes));
    }

    /** @return array{token: string, expires_at: string, name: string} */
    public function issueUntil(string $name, CarbonInterface $expiresAt): array
    {
        if ($expiresAt->lte(now()) || $expiresAt->gt(now()->addDays(7))) {
            throw new \InvalidArgumentException('Masa berlaku token harus antara sekarang dan tujuh hari ke depan.');
        }

        $plain = 'pim_'.Str::random(64);

        PimApiToken::query()->where(function ($query) {
            $query->where('expires_at', '<=', now())->orWhereNotNull('revoked_at');
        })->delete();
        PimApiToken::query()->where('name', $name)->whereNull('revoked_at')
            ->update(['revoked_at' => now(), 'updated_at' => now()]);

        PimApiToken::create([
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
            'expires_at' => $expiresAt,
        ]);

        return ['token' => $plain, 'expires_at' => $expiresAt->toIso8601String(), 'name' => $name];
    }

    public function authenticate(?string $plain): bool
    {
        if (! is_string($plain) || $plain === '') {
            return false;
        }

        if (str_starts_with($plain, 'pim_')) {
            $token = PimApiToken::query()
                ->where('token_hash', hash('sha256', $plain))
                ->whereNull('revoked_at')
                ->where('expires_at', '>', now())
                ->first();
            if (! $token) {
                return false;
            }
            $token->forceFill(['last_used_at' => now()])->saveQuietly();

            return true;
        }

        $legacy = config('pim.inbound_token');

        return (bool) config('pim.allow_static_inbound_token')
            && is_string($legacy) && $legacy !== '' && hash_equals($legacy, $plain);
    }
}
