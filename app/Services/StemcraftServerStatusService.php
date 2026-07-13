<?php

namespace App\Services;

use App\Models\SiteOption;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class StemcraftServerStatusService
{
    private const CACHE_KEY_CURRENT = 'stemcraft.server-status.current';

    private const CACHE_KEY_LAST_GOOD = 'stemcraft.server-status.last-good';

    /**
     * @return array{status: string, players_online: int|null, max_players: int|null, version: string|null, server_address: string, message: string|null, checked_at: string|null, stale: bool}
     */
    public function publicStatus(): array
    {
        $serverAddress = $this->serverAddress();

        if (! SiteOption::booleanValue('stemcraft.server-status.enabled', false)) {
            return $this->offlineStatus($serverAddress);
        }

        $manualMaintenanceMessage = $this->manualMaintenanceMessage();
        if ($manualMaintenanceMessage !== '' && $this->endpointUrl() === '') {
            return $this->maintenanceStatus($serverAddress, $manualMaintenanceMessage);
        }

        $cached = Cache::get(self::CACHE_KEY_CURRENT);
        if (is_array($cached)) {
            return $this->withConfiguredFields($cached, $serverAddress, $manualMaintenanceMessage, false);
        }

        $fresh = $this->requestFreshStatus();
        if ($fresh !== null) {
            $normalized = $this->withConfiguredFields($fresh, $serverAddress, $manualMaintenanceMessage, false);
            Cache::put(self::CACHE_KEY_CURRENT, $normalized, $this->cacheSeconds());
            Cache::forever(self::CACHE_KEY_LAST_GOOD, $normalized);

            return $normalized;
        }

        if ($manualMaintenanceMessage !== '') {
            return $this->maintenanceStatus($serverAddress, $manualMaintenanceMessage);
        }

        return $this->offlineStatus($serverAddress);
    }

    private function endpointUrl(): string
    {
        return trim((string) SiteOption::value('stemcraft.server-status.endpoint-url', SiteOption::defaultValue('stemcraft.server-status.endpoint-url') ?? ''));
    }

    private function apiKey(): string
    {
        return trim((string) SiteOption::secretValue('stemcraft.server-status.api-key', SiteOption::defaultValue('stemcraft.server-status.api-key') ?? ''));
    }

    private function serverAddress(): string
    {
        return trim((string) SiteOption::value('stemcraft.server-status.server-address', SiteOption::defaultValue('stemcraft.server-status.server-address') ?? 'play.stemcraft.com.au'));
    }

    private function manualMaintenanceMessage(): string
    {
        return trim((string) SiteOption::value('stemcraft.server-status.maintenance-message', ''));
    }

    private function cacheSeconds(): int
    {
        return max(1, min(3600, SiteOption::intValue('stemcraft.server-status.cache-seconds', 60)));
    }

    private function timeoutSeconds(): int
    {
        return max(1, min(30, SiteOption::intValue('stemcraft.server-status.timeout-seconds', 3)));
    }

    /**
     * @return array{status: string, players_online: int|null, max_players: int|null, version: string|null, server_address: string, message: string|null, checked_at: string|null, stale: bool}|null
     */
    private function requestFreshStatus(): ?array
    {
        $endpointUrl = $this->endpointUrl();
        if ($endpointUrl === '') {
            return null;
        }

        try {
            $request = Http::acceptJson()->timeout($this->timeoutSeconds());
            $apiKey = $this->apiKey();
            if ($apiKey !== '') {
                $request = $request->withToken($apiKey);
            }

            $response = $request->get($endpointUrl);
            if (! $response->successful()) {
                return null;
            }

            $payload = $response->json();
            if (! is_array($payload)) {
                return null;
            }

            return $this->normalizePayload($payload);
        } catch (Throwable) {
            return null;
        }
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array{status: string, players_online: int|null, max_players: int|null, version: string|null, server_address: string, message: string|null, checked_at: string|null, stale: bool}
     */
    private function normalizePayload(array $payload): array
    {
        $maintenance = filter_var($payload['maintenance'] ?? false, FILTER_VALIDATE_BOOLEAN);
        $online = filter_var($payload['online'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $status = 'offline';
        if ($maintenance) {
            $status = 'maintenance';
        } elseif ($online) {
            $status = 'online';
        }

        return [
            'status' => $status,
            'players_online' => $this->nullableInt($payload['players_online'] ?? null),
            'max_players' => $this->nullableInt($payload['max_players'] ?? null),
            'version' => $this->nullableString($payload['version'] ?? null),
            'server_address' => $this->serverAddress(),
            'message' => $this->nullableString($payload['message'] ?? null),
            'checked_at' => $this->checkedAt($payload['checked_at'] ?? null),
            'stale' => false,
        ];
    }

    /**
     * @param  array<string, mixed>  $status
     * @return array{status: string, players_online: int|null, max_players: int|null, version: string|null, server_address: string, message: string|null, checked_at: string|null, stale: bool}
     */
    private function withConfiguredFields(array $status, string $serverAddress, string $manualMaintenanceMessage, bool $stale): array
    {
        $result = [
            'status' => (string) ($status['status'] ?? 'offline'),
            'players_online' => $this->nullableInt($status['players_online'] ?? null),
            'max_players' => $this->nullableInt($status['max_players'] ?? null),
            'version' => $this->nullableString($status['version'] ?? null),
            'server_address' => $serverAddress,
            'message' => $this->nullableString($status['message'] ?? null),
            'checked_at' => $this->nullableString($status['checked_at'] ?? null),
            'stale' => $stale,
        ];

        if ($manualMaintenanceMessage !== '') {
            $result['status'] = 'maintenance';
            $result['message'] = $manualMaintenanceMessage;
        }

        if (! in_array($result['status'], ['online', 'offline', 'maintenance'], true)) {
            $result['status'] = 'offline';
        }

        return $result;
    }

    /**
     * @return array{status: string, players_online: int|null, max_players: int|null, version: string|null, server_address: string, message: string|null, checked_at: string|null, stale: bool}
     */
    private function maintenanceStatus(string $serverAddress, string $message): array
    {
        return [
            'status' => 'maintenance',
            'players_online' => null,
            'max_players' => null,
            'version' => null,
            'server_address' => $serverAddress,
            'message' => $message,
            'checked_at' => Carbon::now()->toIso8601String(),
            'stale' => false,
        ];
    }

    /**
     * @return array{status: string, players_online: int|null, max_players: int|null, version: string|null, server_address: string, message: string|null, checked_at: string|null, stale: bool}
     */
    private function offlineStatus(string $serverAddress): array
    {
        return [
            'status' => 'offline',
            'players_online' => null,
            'max_players' => null,
            'version' => null,
            'server_address' => $serverAddress,
            'message' => null,
            'checked_at' => null,
            'stale' => false,
        ];
    }

    private function nullableInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_numeric($value) ? max(0, (int) $value) : null;
    }

    private function nullableString(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private function checkedAt(mixed $value): string
    {
        $value = $this->nullableString($value);
        if ($value === null) {
            return Carbon::now()->toIso8601String();
        }

        try {
            return Carbon::parse($value)->toIso8601String();
        } catch (Throwable) {
            return Carbon::now()->toIso8601String();
        }
    }
}
