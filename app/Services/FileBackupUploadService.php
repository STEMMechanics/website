<?php

namespace App\Services;

use App\Models\ServerBackupRun;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class FileBackupUploadService
{
    public const UPLOAD_ROOT = FileBackupService::BACKUP_ROOT.'/.incoming';

    public const CHUNK_SIZE = 8 * 1024 * 1024;

    private const ABANDONED_AFTER_HOURS = 72;

    private const FREE_SPACE_RESERVE = 256 * 1024 * 1024;

    /** @return array{upload_id:string, filename:string, size:int, received_bytes:int, chunk_size:int, fingerprint:string, status:string} */
    public function begin(string $filename, int|string $size, string $fingerprint, int|string $ownerId): array
    {
        $filename = trim($filename);
        if ($filename === '' || mb_strlen($filename) > 255 || basename($filename) !== $filename || str_contains($filename, '\\') || preg_match('/[\x00-\x1F\x7F]/', $filename) || ! preg_match('/\.zip$/i', $filename)) {
            throw new RuntimeException('Choose a ZIP archive downloaded from File Backups.');
        }

        if (! is_numeric($size) || (int) $size < 1 || (string) (int) $size !== (string) $size) {
            throw new RuntimeException('The selected archive has an invalid size.');
        }

        if ($fingerprint === '' || mb_strlen($fingerprint) > 160 || preg_match('/[\x00-\x1F\x7F]/', $fingerprint)) {
            throw new RuntimeException('The selected archive could not be identified for safe resume.');
        }

        $size = (int) $size;

        return $this->withGlobalUploadLock(function () use ($filename, $size, $fingerprint, $ownerId): array {
            $this->assertNoOtherActiveUpload();
            $this->assertUploadDiskSpace($size);

            $uploadId = (string) Str::uuid();
            $directory = $this->uploadDirectory($uploadId);
            if (! mkdir($directory, 0700, true) && ! is_dir($directory)) {
                throw new RuntimeException('Could not create a private upload staging area.');
            }

            $metadata = [
                'upload_id' => $uploadId,
                'filename' => $filename,
                'size' => $size,
                'chunk_size' => self::CHUNK_SIZE,
                'received_bytes' => 0,
                'fingerprint' => $fingerprint,
                'owner_id' => (string) $ownerId,
                'status' => 'uploading',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
                'last_chunk_start' => null,
                'last_chunk_end' => null,
                'last_chunk_sha256' => null,
                'run_id' => null,
            ];

            try {
                $this->writeMetadata($uploadId, $metadata);
                @chmod($directory, 0700);
            } catch (Throwable $exception) {
                Storage::disk('local')->deleteDirectory($this->relativeUploadDirectory($uploadId));
                throw $exception;
            }

            return $this->publicStatus($metadata);
        });
    }

    /** @return array{upload_id:string, filename:string, size:int, received_bytes:int, chunk_size:int, fingerprint:string, status:string, run_id:?string} */
    public function status(string $uploadId, int|string $ownerId): array
    {
        return $this->withUploadLock($uploadId, function () use ($uploadId, $ownerId): array {
            $metadata = $this->readMetadata($uploadId);
            $this->assertOwner($metadata, $ownerId);

            return $this->publicStatus($metadata) + ['run_id' => isset($metadata['run_id']) ? (string) $metadata['run_id'] : null];
        });
    }

    /** @param resource $input */
    public function appendChunk(
        string $uploadId,
        int|string $ownerId,
        int $start,
        int $end,
        int $total,
        $input
    ): array {
        if (! is_resource($input)) {
            throw new RuntimeException('The upload chunk could not be read.');
        }

        return $this->withUploadLock($uploadId, function () use ($uploadId, $ownerId, $start, $end, $total, $input): array {
            $metadata = $this->readMetadata($uploadId);
            $this->assertOwner($metadata, $ownerId);
            if (($metadata['status'] ?? '') !== 'uploading') {
                throw new RuntimeException('This upload is no longer accepting file chunks.');
            }

            $fileSize = (int) ($metadata['size'] ?? 0);
            $chunkBytes = $end - $start + 1;
            if ($fileSize < 1 || $total !== $fileSize || $start < 0 || $end < $start || $end >= $fileSize || $chunkBytes > self::CHUNK_SIZE) {
                throw new RuntimeException('The upload chunk range is invalid.');
            }

            $receivedBytes = (int) ($metadata['received_bytes'] ?? 0);
            $directory = $this->uploadDirectory($uploadId);
            $archivePath = $directory.DIRECTORY_SEPARATOR.'archive.zip.part';
            $committedSize = is_file($archivePath) ? (int) filesize($archivePath) : 0;
            if ($committedSize > $receivedBytes) {
                $archive = @fopen($archivePath, 'c+b');
                if (! is_resource($archive) || ! ftruncate($archive, $receivedBytes)) {
                    if (is_resource($archive)) {
                        fclose($archive);
                    }
                    throw new RuntimeException('The previous upload chunk could not be recovered safely.');
                }
                fclose($archive);
                $committedSize = $receivedBytes;
            }
            if ($committedSize !== $receivedBytes) {
                throw new RuntimeException('The staged upload is incomplete. Start the upload again.');
            }

            $temporaryChunk = $directory.DIRECTORY_SEPARATOR.'chunk-'.bin2hex(random_bytes(8)).'.part';
            $temporary = @fopen($temporaryChunk, 'xb');
            if (! is_resource($temporary)) {
                throw new RuntimeException('Could not stage the upload chunk.');
            }

            try {
                try {
                    $copied = stream_copy_to_stream($input, $temporary, $chunkBytes + 1);
                    if ($copied !== $chunkBytes || ! fflush($temporary)) {
                        throw new RuntimeException('The upload chunk was incomplete. You can resume the upload safely.');
                    }
                } finally {
                    fclose($temporary);
                }
            } catch (Throwable $exception) {
                @unlink($temporaryChunk);
                throw $exception;
            }

            $chunkHash = hash_file('sha256', $temporaryChunk);
            if (! is_string($chunkHash)) {
                @unlink($temporaryChunk);
                throw new RuntimeException('The upload chunk could not be verified.');
            }

            $lastStart = $metadata['last_chunk_start'] ?? null;
            $lastEnd = $metadata['last_chunk_end'] ?? null;
            if ($start < $receivedBytes) {
                @unlink($temporaryChunk);
                if ((int) $lastStart === $start && (int) $lastEnd === $end && hash_equals((string) ($metadata['last_chunk_sha256'] ?? ''), $chunkHash)) {
                    return $this->publicStatus($metadata);
                }
                throw new RuntimeException('The upload chunk was already received with different content. Resume from the displayed offset.');
            }

            if ($start !== $receivedBytes) {
                @unlink($temporaryChunk);
                throw new RuntimeException('The upload is out of sequence. Resume from the displayed offset.');
            }

            $archive = @fopen($archivePath, 'c+b');
            $chunk = @fopen($temporaryChunk, 'rb');
            if (! is_resource($archive) || ! is_resource($chunk) || fseek($archive, $receivedBytes) !== 0) {
                if (is_resource($archive)) {
                    fclose($archive);
                }
                if (is_resource($chunk)) {
                    fclose($chunk);
                }
                @unlink($temporaryChunk);
                throw new RuntimeException('Could not append the upload chunk.');
            }

            try {
                $appended = stream_copy_to_stream($chunk, $archive);
                if ($appended !== $chunkBytes || ! fflush($archive)) {
                    throw new RuntimeException('The upload chunk could not be committed. Resume the upload to retry it.');
                }
                if (function_exists('fsync')) {
                    @fsync($archive);
                }
            } finally {
                fclose($chunk);
                fclose($archive);
                @unlink($temporaryChunk);
            }

            $metadata['received_bytes'] = $receivedBytes + $chunkBytes;
            $metadata['last_chunk_start'] = $start;
            $metadata['last_chunk_end'] = $end;
            $metadata['last_chunk_sha256'] = $chunkHash;
            $metadata['updated_at'] = now()->toIso8601String();
            $this->writeMetadata($uploadId, $metadata);

            return $this->publicStatus($metadata);
        });
    }

    /** @param callable(array<string, mixed>):string $createRun @return array{run_id:string, created:bool} */
    public function queueCompletedUpload(string $uploadId, int|string $ownerId, callable $createRun): array
    {
        return $this->withUploadLock($uploadId, function () use ($uploadId, $ownerId, $createRun): array {
            $metadata = $this->readMetadata($uploadId);
            $this->assertOwner($metadata, $ownerId);

            if (in_array(($metadata['status'] ?? ''), ['queued', 'completed'], true) && filled($metadata['run_id'] ?? null)) {
                return ['run_id' => (string) $metadata['run_id'], 'created' => false];
            }

            $size = (int) ($metadata['size'] ?? 0);
            $received = (int) ($metadata['received_bytes'] ?? 0);
            $archivePath = $this->archivePath($uploadId);
            if (($metadata['status'] ?? '') !== 'uploading' || $size < 1 || $received !== $size || ! is_file($archivePath) || (int) filesize($archivePath) !== $size) {
                throw new RuntimeException('The archive upload is incomplete. Resume it before validating.');
            }

            $runId = $createRun($metadata);
            if (! is_string($runId) || $runId === '') {
                throw new RuntimeException('Could not queue file backup validation.');
            }

            $metadata['status'] = 'queued';
            $metadata['run_id'] = $runId;
            $metadata['updated_at'] = now()->toIso8601String();
            try {
                $this->writeMetadata($uploadId, $metadata);
            } catch (Throwable $exception) {
                ServerBackupRun::query()->whereKey($runId)->delete();
                throw $exception;
            }

            return ['run_id' => $runId, 'created' => true];
        });
    }

    /** @return array<string, mixed> */
    public function sessionForImport(string $uploadId, int|string $ownerId): array
    {
        return $this->withUploadLock($uploadId, function () use ($uploadId, $ownerId): array {
            $metadata = $this->readMetadata($uploadId);
            $this->assertOwner($metadata, $ownerId);
            if (($metadata['status'] ?? '') !== 'queued' || ! filled($metadata['run_id'] ?? null)) {
                throw new RuntimeException('The file backup upload is not ready for validation.');
            }

            $metadata['archive_path'] = $this->archivePath($uploadId);

            return $metadata;
        });
    }

    public function cleanupUpload(string $uploadId): void
    {
        if (! Str::isUuid($uploadId)) {
            return;
        }

        Storage::disk('local')->deleteDirectory($this->relativeUploadDirectory($uploadId));
    }

    public function cancelUpload(string $uploadId, int|string $ownerId): void
    {
        $this->withUploadLock($uploadId, function () use ($uploadId, $ownerId): void {
            $metadata = $this->readMetadata($uploadId);
            $this->assertOwner($metadata, $ownerId);
            if (($metadata['status'] ?? '') !== 'uploading') {
                throw new RuntimeException('This upload can no longer be discarded.');
            }

            Storage::disk('local')->deleteDirectory($this->relativeUploadDirectory($uploadId));
        });
    }

    public function markImportCompleted(string $uploadId): void
    {
        $this->withUploadLock($uploadId, function () use ($uploadId): void {
            $metadata = $this->readMetadata($uploadId);
            foreach (glob($this->uploadDirectory($uploadId).DIRECTORY_SEPARATOR.'*.part') ?: [] as $temporaryPath) {
                @unlink($temporaryPath);
            }
            $metadata['status'] = 'completed';
            $metadata['updated_at'] = now()->toIso8601String();
            $this->writeMetadata($uploadId, $metadata);
        });
    }

    public function cleanupAbandonedUploads(): void
    {
        $root = Storage::disk('local')->path(self::UPLOAD_ROOT);
        if (! is_dir($root)) {
            return;
        }

        foreach (glob($root.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $directory) {
            $uploadId = basename($directory);
            if (! Str::isUuid($uploadId)) {
                continue;
            }

            try {
                $this->withUploadLock($uploadId, function () use ($uploadId): void {
                    $metadata = $this->readMetadata($uploadId);

                    if (($metadata['status'] ?? '') === 'queued' && filled($metadata['run_id'] ?? null)) {
                        $run = ServerBackupRun::query()->find((string) $metadata['run_id']);
                        if ($run instanceof ServerBackupRun) {
                            $run->recoverIfStale();
                            if (! $run->isFinished()) {
                                return;
                            }
                            Storage::disk('local')->deleteDirectory($this->relativeUploadDirectory($uploadId));

                            return;
                        }
                    }

                    $updatedAt = strtotime((string) ($metadata['updated_at'] ?? $metadata['created_at'] ?? ''));
                    if ($updatedAt === false || $updatedAt >= now()->subHours(self::ABANDONED_AFTER_HOURS)->timestamp) {
                        return;
                    }

                    Storage::disk('local')->deleteDirectory($this->relativeUploadDirectory($uploadId));
                });
            } catch (Throwable) {
                $metadataPath = $directory.DIRECTORY_SEPARATOR.'session.json';
                $lastActivity = is_file($metadataPath) ? (int) filemtime($metadataPath) : (int) filemtime($directory);
                if ($lastActivity < now()->subHours(self::ABANDONED_AFTER_HOURS)->timestamp) {
                    Storage::disk('local')->deleteDirectory($this->relativeUploadDirectory($uploadId));
                }
            }
        }
    }

    public function archivePath(string $uploadId): string
    {
        return $this->uploadDirectory($uploadId).DIRECTORY_SEPARATOR.'archive.zip.part';
    }

    /** @param array<string, mixed> $metadata @return array{upload_id:string, filename:string, size:int, received_bytes:int, chunk_size:int, fingerprint:string, status:string} */
    private function publicStatus(array $metadata): array
    {
        return [
            'upload_id' => (string) ($metadata['upload_id'] ?? ''),
            'filename' => (string) ($metadata['filename'] ?? ''),
            'size' => (int) ($metadata['size'] ?? 0),
            'received_bytes' => (int) ($metadata['received_bytes'] ?? 0),
            'chunk_size' => (int) ($metadata['chunk_size'] ?? self::CHUNK_SIZE),
            'fingerprint' => (string) ($metadata['fingerprint'] ?? ''),
            'status' => (string) ($metadata['status'] ?? ''),
        ];
    }

    /** @param array<string, mixed> $metadata */
    private function assertOwner(array $metadata, int|string $ownerId): void
    {
        if (! hash_equals((string) ($metadata['owner_id'] ?? ''), (string) $ownerId)) {
            throw new RuntimeException('This staged upload is not available to your account.');
        }

        $updatedAt = strtotime((string) ($metadata['updated_at'] ?? $metadata['created_at'] ?? ''));
        if ($updatedAt === false || $updatedAt < now()->subHours(self::ABANDONED_AFTER_HOURS)->timestamp) {
            throw new RuntimeException('This staged upload has expired. Choose the archive again to start a new upload.');
        }
    }

    /** @return array<string, mixed> */
    private function readMetadata(string $uploadId): array
    {
        $path = $this->uploadDirectory($uploadId).DIRECTORY_SEPARATOR.'session.json';
        $raw = @file_get_contents($path);
        $metadata = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($metadata) || (string) ($metadata['upload_id'] ?? '') !== $uploadId) {
            throw new RuntimeException('The staged file backup upload could not be found.');
        }

        return $metadata;
    }

    /** @param array<string, mixed> $metadata */
    private function writeMetadata(string $uploadId, array $metadata): void
    {
        $path = $this->uploadDirectory($uploadId).DIRECTORY_SEPARATOR.'session.json';
        $temporaryPath = $path.'.'.bin2hex(random_bytes(6)).'.tmp';
        $json = json_encode($metadata, JSON_UNESCAPED_SLASHES);
        if (! is_string($json) || file_put_contents($temporaryPath, $json, LOCK_EX) !== strlen($json) || ! @rename($temporaryPath, $path)) {
            @unlink($temporaryPath);
            throw new RuntimeException('Could not update the private upload staging record.');
        }
        @chmod($path, 0600);
    }

    private function withUploadLock(string $uploadId, callable $callback): mixed
    {
        if (! Str::isUuid($uploadId)) {
            throw new RuntimeException('The staged file backup upload could not be found.');
        }

        $directory = $this->uploadDirectory($uploadId);
        if (! is_dir($directory)) {
            throw new RuntimeException('The staged file backup upload could not be found.');
        }
        $lock = @fopen($directory.DIRECTORY_SEPARATOR.'.lock', 'c');
        if (! is_resource($lock)) {
            throw new RuntimeException('The staged file backup upload is unavailable.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('The staged file backup upload is busy. Try again shortly.');
            }

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function assertUploadDiskSpace(int $size): void
    {
        $root = Storage::disk('local')->path(self::UPLOAD_ROOT);
        $directory = dirname($root);
        if (! is_dir($directory) && ! mkdir($directory, 0770, true) && ! is_dir($directory)) {
            throw new RuntimeException('Could not prepare the private file backup staging area.');
        }

        $freeBytes = @disk_free_space($directory);
        if ($freeBytes === false) {
            throw new RuntimeException('Available server storage could not be checked, so the upload was not started.');
        }

        if ($freeBytes < $size + self::FREE_SPACE_RESERVE) {
            throw new RuntimeException('There is not enough free server storage to stage this archive safely.');
        }
    }

    private function assertNoOtherActiveUpload(): void
    {
        $root = Storage::disk('local')->path(self::UPLOAD_ROOT);
        foreach (glob($root.DIRECTORY_SEPARATOR.'*', GLOB_ONLYDIR) ?: [] as $directory) {
            $uploadId = basename($directory);
            if (! Str::isUuid($uploadId)) {
                continue;
            }

            $metadataPath = $directory.DIRECTORY_SEPARATOR.'session.json';
            $raw = @file_get_contents($metadataPath);
            $metadata = is_string($raw) ? json_decode($raw, true) : null;
            if (! is_array($metadata)) {
                continue;
            }

            $updatedAt = strtotime((string) ($metadata['updated_at'] ?? $metadata['created_at'] ?? ''));
            if ($updatedAt === false || $updatedAt < now()->subHours(self::ABANDONED_AFTER_HOURS)->timestamp) {
                Storage::disk('local')->deleteDirectory($this->relativeUploadDirectory($uploadId));

                continue;
            }

            $status = (string) ($metadata['status'] ?? '');
            if ($status === 'queued') {
                $run = ServerBackupRun::query()->find((string) ($metadata['run_id'] ?? ''));
                if (! $run instanceof ServerBackupRun || $run->isFinished()) {
                    Storage::disk('local')->deleteDirectory($this->relativeUploadDirectory($uploadId));

                    continue;
                }
            }

            if (in_array($status, ['uploading', 'queued'], true)) {
                throw new RuntimeException('Another file backup upload or validation is already in progress. Resume it or wait for it to finish first.');
            }
        }
    }

    private function withGlobalUploadLock(callable $callback): mixed
    {
        $root = Storage::disk('local')->path(self::UPLOAD_ROOT);
        if (! is_dir($root) && ! mkdir($root, 0770, true) && ! is_dir($root)) {
            throw new RuntimeException('Could not prepare the private file backup staging area.');
        }
        $lock = @fopen($root.DIRECTORY_SEPARATOR.'.begin.lock', 'c');
        if (! is_resource($lock)) {
            throw new RuntimeException('The file backup staging area is unavailable.');
        }

        try {
            if (! flock($lock, LOCK_EX)) {
                throw new RuntimeException('Could not lock the file backup staging area.');
            }

            return $callback();
        } finally {
            flock($lock, LOCK_UN);
            fclose($lock);
        }
    }

    private function uploadDirectory(string $uploadId): string
    {
        if (! Str::isUuid($uploadId)) {
            throw new RuntimeException('The staged file backup upload could not be found.');
        }

        return Storage::disk('local')->path($this->relativeUploadDirectory($uploadId));
    }

    private function relativeUploadDirectory(string $uploadId): string
    {
        return self::UPLOAD_ROOT.'/'.$uploadId;
    }
}
