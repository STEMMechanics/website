<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnlineVisitors
{
    private const KEY = 'analytics:online-visitors:v1';

    public function touch(string $token, ?string $userId): void
    {
        $this->update($token, ['seen_at' => now()->timestamp, 'user_id' => $userId]);
    }

    public function forget(string $token): void
    {
        if ($token !== '') {
            $this->update($token, null);
        }
    }

    private function update(string $token, ?array $visitor): void
    {
        try {
            // Do not delay a page request if another visitor is updating the cache.
            Cache::lock(self::KEY.':lock', 5)->get(function () use ($token, $visitor): void {
                $visitors = $this->recent();
                if ($visitor === null) {
                    unset($visitors[$token]);
                } else {
                    $visitors[$token] = $visitor;
                }
                Cache::put(self::KEY, $visitors, now()->addMinutes(6));
            });
        } catch (\Throwable) {
            // Presence is best effort and must never interrupt the public site.
        }
    }

    private function recent(): array
    {
        return array_filter(Cache::get(self::KEY, []), fn ($visitor) => $visitor['seen_at'] >= now()->subMinutes(5)->timestamp);
    }

    public function ensureDisruptionConfirmed(bool $confirmed): void
    {
        if ($confirmed) {
            return;
        }
        $count = $this->count();
        if ($count === null || $count > 0) {
            throw ValidationException::withMessages([
                'online_visitors_confirmed' => $count === null
                    ? 'The visitor count is unavailable. Confirm before running maintenance or an update.'
                    : $count.' online visitor(s) may be interrupted. Please confirm and try again.',
            ]);
        }
    }

    public function count(): ?int
    {
        if (! config('analytics.enabled', true)) {
            return null;
        }
        try {
            $admins = DB::table('user_groups')->where('slug', 'admin')->pluck('user_id')->all();

            return collect($this->recent())
                ->reject(fn ($visitor) => $visitor['user_id'] !== null && in_array($visitor['user_id'], $admins, true))
                ->map(fn ($visitor, $token) => $visitor['user_id'] ? 'user:'.$visitor['user_id'] : 'session:'.$token)
                ->unique()->count();
        } catch (\Throwable) {
            return null;
        }
    }
}
