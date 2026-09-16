<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OnlineVisitors
{
    private const KEY = 'analytics:online-visitors:v1';

    public function touch(string $token, ?string $userId, ?string $path = null, array $details = []): void
    {
        $this->update($token, ['seen_at' => now()->timestamp, 'user_id' => $userId, 'path' => $path] + $details);
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
        return $this->visitors()?->count();
    }

    public function visitors(): ?Collection
    {
        if (! config('analytics.enabled', true)) {
            return null;
        }
        try {
            $admins = DB::table('user_groups')->where('slug', 'admin')->pluck('user_id')->all();

            $visitors = collect($this->recent())
                ->reject(fn ($visitor) => $visitor['user_id'] !== null && in_array($visitor['user_id'], $admins, true))
                ->sortByDesc('seen_at')
                ->unique(fn ($visitor, $token) => $visitor['user_id'] ? 'user:'.$visitor['user_id'] : 'session:'.$token);
            $users = User::query()->whereIn('id', $visitors->pluck('user_id')->filter())->get()->keyBy('id');

            return $visitors->map(fn ($visitor, $token) => [
                'name' => $users->get($visitor['user_id'])?->getName() ?? 'Guest '.strtoupper(substr(hash_hmac('sha256', $token, (string) config('app.key')), 0, 8)),
                'signed_in' => $users->has($visitor['user_id']),
                'path' => $visitor['path'] ?? null,
                'seen_at' => $visitor['seen_at'],
                'started_at' => $visitor['started_at'] ?? null,
                'page_views' => $visitor['page_views'] ?? null,
                'ip' => $visitor['ip'] ?? null,
                'browser' => $visitor['browser'] ?? null,
                'user_agent' => $visitor['user_agent'] ?? null,
                'location' => $visitor['location'] ?? null,
            ])->values();
        } catch (\Throwable) {
            return null;
        }
    }
}
