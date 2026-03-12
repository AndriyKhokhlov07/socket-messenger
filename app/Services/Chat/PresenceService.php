<?php

namespace App\Services\Chat;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;

class PresenceService
{
    private const CACHE_KEY_PREFIX = 'chat:presence:user:';
    private const TTL_SECONDS = 120;

    public function touch(User $user): void
    {
        Cache::put(
            $this->cacheKey($user->id),
            now()->timestamp,
            now()->addSeconds(self::TTL_SECONDS)
        );
    }

    public function isOnline(int $userId): bool
    {
        return Cache::has($this->cacheKey($userId));
    }

    public function lastSeenAt(int $userId): ?CarbonImmutable
    {
        $timestamp = Cache::get($this->cacheKey($userId));

        if (! is_int($timestamp) && ! ctype_digit((string) $timestamp)) {
            return null;
        }

        return CarbonImmutable::createFromTimestamp((int) $timestamp);
    }

    private function cacheKey(int $userId): string
    {
        return self::CACHE_KEY_PREFIX.$userId;
    }
}
