<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Closure;
use RuntimeException;

class ExpenseImportBatchService
{
    private const ROOT = 'expense-imports';

    /**
     * @param  list<UploadedFile>  $files
     * @return array{id: string, items: list<array<string, mixed>>}
     */
    public function create(string $userId, array $files): array
    {
        $batchId = (string) Str::uuid();
        $items = [];
        $disk = Storage::disk('local');

        try {
            foreach (array_values($files) as $index => $file) {
                $itemId = (string) Str::uuid();
                $extension = $this->extensionFor($file);
                $path = self::ROOT.'/'.$batchId.'/'.$itemId.'.'.$extension;
                $storedPath = $file->storeAs(dirname($path), basename($path), 'local');

                if (! is_string($storedPath) || $storedPath === '') {
                    throw new RuntimeException('The receipt could not be staged for import.');
                }

                $items[] = [
                    'id' => $itemId,
                    'position' => $index + 1,
                    'name' => Str::limit(basename((string) $file->getClientOriginalName()), 180, ''),
                    'path' => $storedPath,
                    'mime_type' => (string) ($file->getMimeType() ?: $file->getClientMimeType() ?: 'application/octet-stream'),
                    'size' => (int) $file->getSize(),
                    'status' => 'pending',
                    'saved_expense_id' => null,
                    'ai_status' => 'queued',
                    'ai_result' => null,
                    'ai_message' => null,
                    'ai_started_at' => null,
                    'ai_completed_at' => null,
                ];
            }

            $batch = [
                'version' => 1,
                'id' => $batchId,
                'user_id' => $userId,
                'created_at' => now()->timestamp,
                'items' => $items,
            ];
            $this->writeManifest($batch);

            return ['id' => $batchId, 'items' => $items];
        } catch (\Throwable $exception) {
            $disk->deleteDirectory(self::ROOT.'/'.$batchId);

            throw $exception;
        }
    }

    /** @return array<string, mixed>|null */
    public function forUser(string $batchId, string $userId): ?array
    {
        if (! Str::isUuid($batchId)) {
            return null;
        }

        $path = $this->manifestPath($batchId);
        $disk = Storage::disk('local');
        if (! $disk->exists($path)) {
            return null;
        }

        try {
            $batch = json_decode((string) $disk->get($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\Throwable) {
            return null;
        }

        if (! is_array($batch) || (string) ($batch['user_id'] ?? '') !== $userId || ! is_array($batch['items'] ?? null)) {
            return null;
        }

        return $batch;
    }

    /** @return array{batch: array<string, mixed>, item: array<string, mixed>}|null */
    public function itemForUser(string $batchId, string $itemId, string $userId): ?array
    {
        $batch = $this->forUser($batchId, $userId);
        if ($batch === null) {
            return null;
        }

        foreach ($batch['items'] as $item) {
            if ((string) ($item['id'] ?? '') !== $itemId || ($item['status'] ?? 'pending') !== 'pending') {
                continue;
            }

            $path = (string) ($item['path'] ?? '');
            if ($path === '' || ! Storage::disk('local')->exists($path)) {
                return null;
            }

            return ['batch' => $batch, 'item' => $item];
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function nextPending(array $batch, string $currentItemId): ?array
    {
        $foundCurrent = false;
        foreach ($batch['items'] as $item) {
            if (! $foundCurrent) {
                $foundCurrent = (string) ($item['id'] ?? '') === $currentItemId;

                continue;
            }

            if (($item['status'] ?? 'pending') === 'pending') {
                return $item;
            }
        }

        return null;
    }

    /** @return array<string, mixed>|null */
    public function markSaved(string $batchId, string $itemId, string $userId, int $expenseId): ?array
    {
        return $this->markItem($batchId, $itemId, $userId, 'saved', $expenseId);
    }

    /** @return array<string, mixed>|null */
    public function markSkipped(string $batchId, string $itemId, string $userId): ?array
    {
        return $this->markItem($batchId, $itemId, $userId, 'skipped');
    }

    /** @return array{batch: array<string, mixed>, item: array<string, mixed>}|null */
    public function aiItemForUser(string $batchId, string $itemId, string $userId): ?array
    {
        $batch = $this->forUser($batchId, $userId);
        if ($batch === null) {
            return null;
        }

        foreach ($batch['items'] as $item) {
            if ((string) ($item['id'] ?? '') !== $itemId || ($item['status'] ?? 'pending') !== 'pending') {
                continue;
            }

            return ['batch' => $batch, 'item' => $item];
        }

        return null;
    }

    /** @return array{batch: array<string, mixed>, item: array<string, mixed>}|null */
    public function claimAi(string $batchId, string $itemId, string $userId): ?array
    {
        return $this->withManifestLock($batchId, function () use ($batchId, $itemId, $userId): ?array {
            $batch = $this->forUser($batchId, $userId);
            if ($batch === null) {
                return null;
            }

            foreach ($batch['items'] as &$item) {
                if ((string) ($item['id'] ?? '') !== $itemId || ($item['status'] ?? 'pending') !== 'pending') {
                    continue;
                }

                $aiStatus = (string) ($item['ai_status'] ?? 'queued');
                if ($aiStatus === 'completed') {
                    return null;
                }
                if ($aiStatus === 'processing') {
                    $startedAt = (int) ($item['ai_started_at'] ?? 0);
                    if ($startedAt > 0 && $startedAt > now()->subMinutes(10)->timestamp) {
                        return null;
                    }
                }

                $item['ai_status'] = 'processing';
                $item['ai_message'] = null;
                $item['ai_started_at'] = now()->timestamp;
                $this->writeManifest($batch);

                return ['batch' => $batch, 'item' => $item];
            }
            unset($item);

            return null;
        });
    }

    /** @param array<string, mixed> $result */
    public function markAiCompleted(string $batchId, string $itemId, string $userId, array $result): bool
    {
        return (bool) $this->withManifestLock($batchId, function () use ($batchId, $itemId, $userId, $result): bool {
            $batch = $this->forUser($batchId, $userId);
            if ($batch === null) {
                return false;
            }

            foreach ($batch['items'] as &$item) {
                if ((string) ($item['id'] ?? '') !== $itemId || ($item['status'] ?? 'pending') !== 'pending') {
                    continue;
                }

                $item['ai_status'] = 'completed';
                $item['ai_result'] = $result;
                $item['ai_message'] = null;
                $item['ai_completed_at'] = now()->timestamp;
                $this->writeManifest($batch);

                return true;
            }
            unset($item);

            return false;
        });
    }

    public function markAiQueued(string $batchId, string $itemId, string $userId): bool
    {
        return (bool) $this->withManifestLock($batchId, function () use ($batchId, $itemId, $userId): bool {
            $batch = $this->forUser($batchId, $userId);
            if ($batch === null) {
                return false;
            }

            foreach ($batch['items'] as &$item) {
                if ((string) ($item['id'] ?? '') !== $itemId || ($item['status'] ?? 'pending') !== 'pending') {
                    continue;
                }

                $item['ai_status'] = 'queued';
                $item['ai_message'] = null;
                $this->writeManifest($batch);

                return true;
            }
            unset($item);

            return false;
        });
    }

    public function markAiFailed(string $batchId, string $itemId, string $userId, string $message): bool
    {
        return (bool) $this->withManifestLock($batchId, function () use ($batchId, $itemId, $userId, $message): bool {
            $batch = $this->forUser($batchId, $userId);
            if ($batch === null) {
                return false;
            }

            foreach ($batch['items'] as &$item) {
                if ((string) ($item['id'] ?? '') !== $itemId || ($item['status'] ?? 'pending') !== 'pending') {
                    continue;
                }

                $item['ai_status'] = 'failed';
                $item['ai_result'] = null;
                $item['ai_message'] = Str::limit(trim(strip_tags($message)), 500, '');
                $item['ai_completed_at'] = now()->timestamp;
                $this->writeManifest($batch);

                return true;
            }
            unset($item);

            return false;
        });
    }

    /** @return array<string, mixed>|null */
    private function markItem(string $batchId, string $itemId, string $userId, string $status, ?int $expenseId = null): ?array
    {
        return $this->withManifestLock($batchId, function () use ($batchId, $itemId, $userId, $status, $expenseId): ?array {
            $batch = $this->forUser($batchId, $userId);
            if ($batch === null) {
                return null;
            }

            $savedPath = null;
            foreach ($batch['items'] as &$item) {
                if ((string) ($item['id'] ?? '') !== $itemId || ($item['status'] ?? 'pending') !== 'pending') {
                    continue;
                }

                $savedPath = (string) ($item['path'] ?? '');
                $item['status'] = $status;
                $item['saved_expense_id'] = $expenseId;
                break;
            }
            unset($item);

            if ($savedPath === null) {
                return null;
            }

            if ($savedPath !== '') {
                Storage::disk('local')->delete($savedPath);
            }

            $next = collect($batch['items'])->first(fn (array $item): bool => ($item['status'] ?? 'pending') === 'pending');
            if (! is_array($next)) {
                Storage::disk('local')->deleteDirectory(self::ROOT.'/'.$batchId);

                return null;
            }

            $this->writeManifest($batch);

            return $next;
        });
    }

    private function withManifestLock(string $batchId, Closure $callback): mixed
    {
        if (! Str::isUuid($batchId)) {
            return null;
        }

        return Cache::lock('expense-import-manifest-'.$batchId, 30)->block(10, $callback);
    }

    public function stagedFilePath(array $item): string
    {
        $path = (string) ($item['path'] ?? '');
        if ($path === '' || ! str_starts_with($path, self::ROOT.'/')) {
            throw new RuntimeException('The staged receipt path is invalid.');
        }

        return Storage::disk('local')->path($path);
    }

    public function cleanupExpired(int $hours = 24): int
    {
        $disk = Storage::disk('local');
        $removed = 0;
        $threshold = now()->subHours(max(1, $hours))->timestamp;

        foreach ($disk->directories(self::ROOT) as $directory) {
            $manifestPath = $directory.'/manifest.json';
            if (! $disk->exists($manifestPath)) {
                continue;
            }

            try {
                $manifest = json_decode((string) $disk->get($manifestPath), true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
                continue;
            }

            if (is_array($manifest) && (int) ($manifest['created_at'] ?? 0) < $threshold) {
                $disk->deleteDirectory($directory);
                $removed++;
            }
        }

        return $removed;
    }

    /** @param array<string, mixed> $batch */
    private function writeManifest(array $batch): void
    {
        $disk = Storage::disk('local');
        $path = $this->manifestPath((string) $batch['id']);
        $temporaryPath = $path.'.'.Str::uuid().'.tmp';

        try {
            $disk->put($temporaryPath, json_encode($batch, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
            $disk->move($temporaryPath, $path);
        } catch (\Throwable $exception) {
            $disk->delete($temporaryPath);

            throw $exception;
        }
    }

    private function manifestPath(string $batchId): string
    {
        return self::ROOT.'/'.$batchId.'/manifest.json';
    }

    private function extensionFor(UploadedFile $file): string
    {
        $extension = strtolower(trim((string) $file->getClientOriginalExtension()));
        if ($extension !== '' && preg_match('/^[a-z0-9]{1,8}$/', $extension)) {
            return $extension;
        }

        return match ((string) ($file->getMimeType() ?: $file->getClientMimeType())) {
            'application/pdf' => 'pdf',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/webp' => 'webp',
            default => 'bin',
        };
    }
}
