<?php

namespace App\Support;

use App\Models\Token;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Carbon;

class RememberedDeviceManager
{
    public const DEVICE_TOKEN_TYPE = 'remember-device';
    public const DEVICE_COOKIE = 'sm_remember_device';
    public const EMAIL_COOKIE = 'sm_last_login_email';

    // Long-lived browser cookie. Server-side token has no expiry for trusted devices.
    private const DEVICE_COOKIE_TTL_MINUTES = 60 * 24 * 3650;
    private const EMAIL_TTL_MINUTES = 60 * 24 * 365;

    public function getRememberedEmail(Request $request): ?string
    {
        $email = trim((string) $request->cookie(self::EMAIL_COOKIE, ''));

        return $email !== '' ? $email : null;
    }

    public function queueRememberedEmail(?string $email): void
    {
        $value = trim((string) ($email ?? ''));

        if ($value === '') {
            cookie()->queue(cookie()->forget(self::EMAIL_COOKIE, $this->cookiePath(), $this->cookieDomain()));
            return;
        }

        cookie()->queue(cookie(
            self::EMAIL_COOKIE,
            $value,
            self::EMAIL_TTL_MINUTES,
            $this->cookiePath(),
            $this->cookieDomain(),
            $this->cookieSecure(),
            true,
            false,
            $this->cookieSameSite()
        ));
    }

    public function rememberUserOnCurrentDevice(Request $request, User $user): Token
    {
        $existingToken = $this->currentTokenForUser($request, $user);
        $token = $existingToken ?? $user->tokens()->make([
            'type' => self::DEVICE_TOKEN_TYPE,
        ]);

        $data = is_array($token->data) ? $token->data : [];
        $data['user_agent'] = substr((string) ($request->userAgent() ?? ''), 0, 500);
        $data['ip_address'] = substr((string) ($request->ip() ?? ''), 0, 64);
        $data['last_used_at'] = now()->toIso8601String();
        $deviceHint = $this->normalizedDeviceHint($request->input('remembered_device_hint'));
        if ($deviceHint !== null) {
            $data['device_hint'] = $deviceHint;
        }
        $maxTouchPoints = $this->normalizedMaxTouchPoints($request->input('remembered_device_touch_points'));
        if ($maxTouchPoints !== null) {
            $data['max_touch_points'] = $maxTouchPoints;
        }

        $token->data = $data;
        $token->expires_at = null;
        $token->save();

        $this->queueDeviceCookie($token->id);

        return $token;
    }

    public function refreshCurrentDeviceForUser(Request $request, User $user): ?Token
    {
        $token = $this->currentTokenForUser($request, $user);
        if (! $token) {
            return null;
        }

        return $this->rememberUserOnCurrentDevice($request, $user);
    }

    public function resolveRememberedUser(Request $request): ?User
    {
        $tokenId = $this->currentTokenId($request);
        if ($tokenId === null) {
            return null;
        }

        /** @var Token|null $token */
        $token = Token::query()
            ->where('id', $tokenId)
            ->where('type', self::DEVICE_TOKEN_TYPE)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $token || ! $token->user || $token->user->isAnonymized()) {
            $this->forgetCurrentDevice($request, null);
            return null;
        }

        $this->rememberUserOnCurrentDevice($request, $token->user);

        return $token->user;
    }

    public function listRememberedDevices(User $user, Request $request): Collection
    {
        $currentTokenId = $this->currentTokenId($request);

        return $user->tokens()
            ->where('type', self::DEVICE_TOKEN_TYPE)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->orderByDesc('created_at')
            ->get()
            ->map(function (Token $token) use ($currentTokenId): array {
                $data = is_array($token->data) ? $token->data : [];
                $createdAt = null;
                if (! empty($token->created_at)) {
                    try {
                        $createdAt = Carbon::parse((string) $token->created_at);
                    } catch (\Throwable $e) {
                        $createdAt = null;
                    }
                }
                $lastUsedAt = null;
                if (isset($data['last_used_at'])) {
                    try {
                        $lastUsedAt = Carbon::parse((string) $data['last_used_at']);
                    } catch (\Throwable $e) {
                        $lastUsedAt = null;
                    }
                }

                return [
                    'id' => (string) $token->id,
                    'is_current' => (string) $token->id === (string) $currentTokenId,
                    'user_agent' => (string) ($data['user_agent'] ?? ''),
                    'ip_address' => (string) ($data['ip_address'] ?? ''),
                    'nickname' => $this->normalizedNickname((string) ($data['nickname'] ?? '')),
                    'browser' => $this->browserName((string) ($data['user_agent'] ?? '')),
                    'created_at' => $createdAt,
                    'created_label' => $createdAt?->diffForHumans() ?? '-',
                    'last_used_at' => $lastUsedAt,
                    'last_used_label' => $lastUsedAt?->diffForHumans() ?? '-',
                    'default_title' => $this->deviceTitle($data),
                    'title' => $this->normalizedNickname((string) ($data['nickname'] ?? '')) ?: $this->deviceTitle($data),
                ];
            })
            ->values();
    }

    public function forgetCurrentDevice(Request $request, ?User $user): void
    {
        $tokenId = $this->currentTokenId($request);

        if ($tokenId !== null) {
            $query = Token::query()
                ->where('id', $tokenId)
                ->where('type', self::DEVICE_TOKEN_TYPE);

            if ($user) {
                $query->where('user_id', $user->id);
            }

            $query->delete();
        }

        cookie()->queue(cookie()->forget(self::DEVICE_COOKIE, $this->cookiePath(), $this->cookieDomain()));
    }

    public function forgetDeviceById(User $user, string $tokenId, Request $request): bool
    {
        $deleted = Token::query()
            ->where('id', $tokenId)
            ->where('type', self::DEVICE_TOKEN_TYPE)
            ->where('user_id', $user->id)
            ->delete();

        if ((string) $tokenId === (string) ($this->currentTokenId($request) ?? '')) {
            cookie()->queue(cookie()->forget(self::DEVICE_COOKIE, $this->cookiePath(), $this->cookieDomain()));
        }

        return $deleted > 0;
    }

    public function setDeviceNickname(User $user, string $tokenId, string $nickname): bool
    {
        /** @var Token|null $token */
        $token = Token::query()
            ->where('id', $tokenId)
            ->where('type', self::DEVICE_TOKEN_TYPE)
            ->where('user_id', $user->id)
            ->first();

        if (! $token) {
            return false;
        }

        $data = is_array($token->data) ? $token->data : [];
        $normalizedNickname = $this->normalizedNickname($nickname);

        if ($normalizedNickname === '') {
            unset($data['nickname']);
        } else {
            $data['nickname'] = $normalizedNickname;
        }

        $token->data = $data;
        $token->save();

        return true;
    }

    public function currentTokenId(Request $request): ?string
    {
        $tokenId = trim((string) $request->cookie(self::DEVICE_COOKIE, ''));

        return $tokenId !== '' ? $tokenId : null;
    }

    private function currentTokenForUser(Request $request, User $user): ?Token
    {
        $tokenId = $this->currentTokenId($request);

        if ($tokenId === null) {
            return null;
        }

        /** @var Token|null $token */
        $token = Token::query()
            ->where('id', $tokenId)
            ->where('type', self::DEVICE_TOKEN_TYPE)
            ->where('user_id', $user->id)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            })
            ->first();

        return $token;
    }

    private function queueDeviceCookie(string $tokenId): void
    {
        cookie()->queue(cookie(
            self::DEVICE_COOKIE,
            $tokenId,
            self::DEVICE_COOKIE_TTL_MINUTES,
            $this->cookiePath(),
            $this->cookieDomain(),
            $this->cookieSecure(),
            true,
            false,
            $this->cookieSameSite()
        ));
    }

    private function cookiePath(): string
    {
        return (string) config('session.path', '/');
    }

    private function cookieDomain(): ?string
    {
        $domain = config('session.domain');
        return is_string($domain) && trim($domain) !== '' ? $domain : null;
    }

    private function cookieSecure(): bool
    {
        return (bool) config('session.secure', false);
    }

    private function cookieSameSite(): ?string
    {
        $sameSite = config('session.same_site');
        return is_string($sameSite) && trim($sameSite) !== '' ? $sameSite : null;
    }

    private function deviceTitle(array $data): string
    {
        $deviceHint = $this->normalizedDeviceHint($data['device_hint'] ?? null);
        if ($deviceHint === 'ipad') {
            return 'iPad';
        }
        if ($deviceHint === 'iphone') {
            return 'iPhone';
        }
        if ($deviceHint === 'android') {
            return 'Android Device';
        }
        if ($deviceHint === 'mac') {
            return 'Mac';
        }
        if ($deviceHint === 'windows') {
            return 'Windows PC';
        }

        $userAgent = (string) ($data['user_agent'] ?? '');
        $ua = strtolower($userAgent);
        $maxTouchPoints = $this->normalizedMaxTouchPoints($data['max_touch_points'] ?? null) ?? 0;

        if (str_contains($ua, 'ipad')) {
            return 'iPad';
        }
        if (str_contains($ua, 'iphone')) {
            return 'iPhone';
        }
        if (str_contains($ua, 'android')) {
            return 'Android Device';
        }
        if (str_contains($ua, 'macintosh') && $maxTouchPoints > 1) {
            return 'iPad';
        }
        if (str_contains($ua, 'macintosh') || str_contains($ua, 'mac os')) {
            return 'Mac';
        }
        if (str_contains($ua, 'windows')) {
            return 'Windows PC';
        }

        return 'Browser Device';
    }

    private function browserName(string $userAgent): ?string
    {
        $ua = strtolower($userAgent);
        if ($ua === '') {
            return null;
        }

        if (str_contains($ua, 'edg/')) {
            return 'Edge';
        }
        if (str_contains($ua, 'opr/') || str_contains($ua, 'opera')) {
            return 'Opera';
        }
        if (str_contains($ua, 'firefox/')) {
            return 'Firefox';
        }
        if (str_contains($ua, 'chrome/') || str_contains($ua, 'crios/')) {
            return 'Chrome';
        }
        if (str_contains($ua, 'safari/')) {
            return 'Safari';
        }
        if (str_contains($ua, 'trident/') || str_contains($ua, 'msie ')) {
            return 'Internet Explorer';
        }

        return null;
    }

    private function normalizedDeviceHint(mixed $hint): ?string
    {
        if (! is_string($hint)) {
            return null;
        }

        $normalized = strtolower(trim($hint));
        if ($normalized === '') {
            return null;
        }

        $allowed = ['ipad', 'iphone', 'android', 'mac', 'windows', 'other'];

        return in_array($normalized, $allowed, true) ? $normalized : null;
    }

    private function normalizedMaxTouchPoints(mixed $touchPoints): ?int
    {
        if (is_string($touchPoints)) {
            $touchPoints = trim($touchPoints);
        }

        if ($touchPoints === '' || $touchPoints === null || ! is_numeric($touchPoints)) {
            return null;
        }

        $normalized = (int) $touchPoints;

        if ($normalized < 0) {
            return 0;
        }

        return min($normalized, 20);
    }

    private function normalizedNickname(string $nickname): string
    {
        return substr(trim($nickname), 0, 60);
    }
}
