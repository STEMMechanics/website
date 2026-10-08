<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;
use ZipArchive;

class FileBackupImportService
{
    private const MAX_ARCHIVE_ENTRIES = 1_000_000;

    private const MAX_MANIFEST_BYTES = 64 * 1024 * 1024;

    private const FREE_SPACE_RESERVE = 256 * 1024 * 1024;

    /**
     * @param  callable(int, string):void|null  $onProgress
     * @return array{mode:string, filename:string, run_path:string, uploaded_files:int, deleted_files:int, size:int}
     */
    public function importArchive(string $archivePath, string $originalFilename, ?callable $onProgress = null): array
    {
        if (! is_file($archivePath) || ! is_readable($archivePath)) {
            throw new RuntimeException('The staged ZIP archive is unavailable.');
        }

        $zip = new ZipArchive;
        $opened = $zip->open($archivePath, ZipArchive::RDONLY);
        if ($opened !== true) {
            throw new RuntimeException('This is not a readable ZIP archive.');
        }

        $runPath = '';
        try {
            if ($zip->numFiles < 2 || $zip->numFiles > self::MAX_ARCHIVE_ENTRIES) {
                throw new RuntimeException('The ZIP archive has an unsupported number of entries.');
            }

            $manifestIndex = null;
            $manifestCount = 0;
            $totalExpandedBytes = 0;
            $entrySources = [];
            $sourceDefinitions = collect(ExternalBackupService::defaultFileSources())->keyBy('key');

            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (! is_array($stat) || ! isset($stat['name'])) {
                    throw new RuntimeException('The ZIP archive contains an unreadable entry.');
                }

                $rawName = (string) $stat['name'];
                $isDirectory = str_ends_with($rawName, '/');
                $entryName = $this->normalizeArchiveEntry($rawName, $isDirectory);
                $this->assertNotSpecialFile($zip, $index);
                if (! $isDirectory && (int) ($stat['comp_method'] ?? -1) !== ZipArchive::CM_STORE) {
                    throw new RuntimeException('Only uncompressed archives created by the File Backups archive action are supported.');
                }

                if ($entryName === '' && $isDirectory) {
                    continue;
                }

                if ($entryName === 'manifest.json') {
                    if ($isDirectory) {
                        throw new RuntimeException('The archive manifest must be a file.');
                    }
                    $manifestIndex = $index;
                    $manifestCount++;
                    if ($manifestCount > 1) {
                        throw new RuntimeException('The ZIP archive contains more than one manifest.');
                    }
                    if ((int) ($stat['size'] ?? 0) > self::MAX_MANIFEST_BYTES) {
                        throw new RuntimeException('The file backup manifest is too large to inspect safely.');
                    }

                    continue;
                }

                if ($isDirectory && $entryName === 'files') {
                    continue;
                }

                if (! str_starts_with($entryName, 'files/')) {
                    throw new RuntimeException('The ZIP archive is not a STEMMechanics file backup.');
                }

                $parts = explode('/', $entryName);
                if ($isDirectory && count($parts) === 2 && $parts[0] === 'files' && $sourceDefinitions->has($parts[1])) {
                    continue;
                }
                if (count($parts) < 3 || $parts[0] !== 'files' || ! $sourceDefinitions->has($parts[1])) {
                    throw new RuntimeException('The ZIP archive contains an unsupported backup path.');
                }

                if (! $isDirectory) {
                    $entrySize = (int) ($stat['size'] ?? -1);
                    if ($entrySize < 0 || $totalExpandedBytes > PHP_INT_MAX - $entrySize) {
                        throw new RuntimeException('The ZIP archive reports an invalid expanded size.');
                    }
                    $totalExpandedBytes += $entrySize;
                    $entrySources[$parts[1]] = true;
                }
            }

            if (! is_int($manifestIndex) || $manifestCount !== 1 || $entrySources === []) {
                throw new RuntimeException('The ZIP archive is missing its file backup manifest or files.');
            }

            $manifestJson = $zip->getFromIndex($manifestIndex);
            $archiveManifest = is_string($manifestJson) ? json_decode($manifestJson, true) : null;
            if (! is_array($archiveManifest) || ! is_array($archiveManifest['sources'] ?? null)) {
                throw new RuntimeException('The ZIP archive has an invalid file backup manifest.');
            }

            $mode = (string) ($archiveManifest['mode'] ?? '');
            if (! in_array($mode, [FileBackupService::MODE_FULL, FileBackupService::MODE_INCREMENTAL], true)) {
                throw new RuntimeException('The ZIP archive does not contain a supported file backup mode.');
            }

            $archiveSourceKeys = array_keys($archiveManifest['sources']);
            foreach (array_keys($entrySources) as $sourceKey) {
                if (! $sourceDefinitions->has($sourceKey) || ! array_key_exists($sourceKey, $archiveManifest['sources'])) {
                    throw new RuntimeException('The archive contains files for an unknown backup source.');
                }
            }
            foreach ($archiveSourceKeys as $sourceKey) {
                if (! is_string($sourceKey) || ! $sourceDefinitions->has($sourceKey) || ! is_array($archiveManifest['sources'][$sourceKey])) {
                    throw new RuntimeException('The archive manifest refers to an unsupported backup source.');
                }
            }

            $this->assertExpandedDiskSpace($totalExpandedBytes);
            $runName = now()->format('Ymd_His').'_import_'.Str::lower(Str::random(8));
            $runPath = FileBackupService::BACKUP_ROOT.'/'.$mode.'/'.$runName;
            $local = Storage::disk('local');
            $local->makeDirectory($runPath.'/files');

            if ($onProgress !== null) {
                $onProgress(15, 'Archive structure is valid. Preparing files…');
            }

            $copiedBytes = 0;
            $uploadedCounts = [];
            $fileCount = 0;
            $nextProgressAt = 64 * 1024 * 1024;
            for ($index = 0; $index < $zip->numFiles; $index++) {
                $stat = $zip->statIndex($index);
                if (! is_array($stat) || ! isset($stat['name'])) {
                    throw new RuntimeException('The ZIP archive changed while it was being checked.');
                }

                $rawName = (string) $stat['name'];
                $isDirectory = str_ends_with($rawName, '/');
                $entryName = $this->normalizeArchiveEntry($rawName, $isDirectory);
                if ($isDirectory || $entryName === '' || $entryName === 'manifest.json') {
                    continue;
                }

                $relativePath = substr($entryName, strlen('files/'));
                $destinationRelativePath = $runPath.'/files/'.$relativePath;
                $destinationPath = $local->path($destinationRelativePath);
                $destinationDirectory = dirname($destinationPath);
                if (! is_dir($destinationDirectory) && ! mkdir($destinationDirectory, 0770, true) && ! is_dir($destinationDirectory)) {
                    throw new RuntimeException('Could not prepare a destination folder for an archived file.');
                }

                $stream = $zip->getStreamIndex($index);
                $destination = @fopen($destinationPath, 'xb');
                if (! is_resource($stream) || ! is_resource($destination)) {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                    if (is_resource($destination)) {
                        fclose($destination);
                    }
                    throw new RuntimeException('A file in the ZIP archive could not be opened safely.');
                }

                $entryBytes = 0;
                $crc = hash_init('crc32b');
                try {
                    while (! feof($stream)) {
                        $chunk = fread($stream, 1024 * 1024);
                        if ($chunk === false) {
                            throw new RuntimeException('A file in the ZIP archive could not be read.');
                        }
                        if ($chunk === '') {
                            if (feof($stream)) {
                                break;
                            }
                            throw new RuntimeException('Reading a file from the ZIP archive stalled.');
                        }

                        hash_update($crc, $chunk);
                        $length = strlen($chunk);
                        $written = 0;
                        while ($written < $length) {
                            $result = fwrite($destination, substr($chunk, $written));
                            if ($result === false || $result === 0) {
                                throw new RuntimeException('A file from the ZIP archive could not be written.');
                            }
                            $written += $result;
                        }
                        $entryBytes += $length;
                        $copiedBytes += $length;
                        if ($onProgress !== null && $copiedBytes >= $nextProgressAt) {
                            $fraction = $totalExpandedBytes > 0 ? min(1, $copiedBytes / $totalExpandedBytes) : 1;
                            $onProgress(15 + (int) floor($fraction * 80), 'Validating and unpacking archived files…');
                            $nextProgressAt += 64 * 1024 * 1024;
                        }
                    }

                    if (! fflush($destination)) {
                        throw new RuntimeException('A restored backup file could not be flushed to storage.');
                    }
                } finally {
                    fclose($stream);
                    fclose($destination);
                }

                $expectedBytes = (int) ($stat['size'] ?? -1);
                $expectedCrc = sprintf('%08x', (int) ($stat['crc'] ?? 0));
                if ($entryBytes !== $expectedBytes || ! hash_equals($expectedCrc, hash_final($crc))) {
                    throw new RuntimeException('A file in the ZIP archive failed its size or checksum validation.');
                }

                @chmod($destinationPath, 0660);
                $sourceKey = explode('/', $relativePath, 2)[0];
                $uploadedCounts[$sourceKey] = (int) ($uploadedCounts[$sourceKey] ?? 0) + 1;
                $fileCount++;

                if ($onProgress !== null && ($fileCount % 32 === 0 || $copiedBytes >= $totalExpandedBytes)) {
                    $fraction = $totalExpandedBytes > 0 ? min(1, $copiedBytes / $totalExpandedBytes) : 1;
                    $onProgress(15 + (int) floor($fraction * 80), 'Validating and unpacking archived files…');
                }
            }

            $deletedPaths = $this->safeDeletedPaths($archiveManifest['deleted_paths'] ?? [], $sourceDefinitions);
            $sourceSummaries = [];
            foreach ($archiveSourceKeys as $sourceKey) {
                $definition = $sourceDefinitions->get($sourceKey);
                $deletedForSource = $deletedPaths[$sourceKey] ?? [];
                $sourceSummaries[$sourceKey] = [
                    'label' => (string) $definition['label'],
                    'disk' => (string) $definition['disk'],
                    'path' => (string) $definition['path'],
                    'total_files' => (int) ($uploadedCounts[$sourceKey] ?? 0),
                    'uploaded_files' => (int) ($uploadedCounts[$sourceKey] ?? 0),
                    'deleted_files' => count($deletedForSource),
                    'uploaded_paths' => [],
                    'deleted_paths' => $deletedForSource,
                ];
            }

            foreach (array_keys($uploadedCounts) as $sourceKey) {
                if (isset($sourceSummaries[$sourceKey])) {
                    continue;
                }
                $definition = $sourceDefinitions->get($sourceKey);
                $sourceSummaries[$sourceKey] = [
                    'label' => (string) $definition['label'],
                    'disk' => (string) $definition['disk'],
                    'path' => (string) $definition['path'],
                    'total_files' => (int) $uploadedCounts[$sourceKey],
                    'uploaded_files' => (int) $uploadedCounts[$sourceKey],
                    'deleted_files' => 0,
                    'uploaded_paths' => [],
                    'deleted_paths' => [],
                ];
            }

            $deletedCount = array_sum(array_map('count', $deletedPaths));
            try {
                $createdAt = isset($archiveManifest['created_at']) && mb_strlen((string) $archiveManifest['created_at']) <= 128
                    ? Carbon::parse((string) $archiveManifest['created_at'])->toIso8601String()
                    : now()->toIso8601String();
            } catch (Throwable) {
                $createdAt = now()->toIso8601String();
            }

            $safeManifest = [
                'mode' => $mode,
                'window_hours' => $mode === FileBackupService::MODE_INCREMENTAL
                    ? max(1, (int) ($archiveManifest['window_hours'] ?? 24))
                    : null,
                'run_path' => $runPath,
                'created_at' => $createdAt,
                'imported_at' => now()->toIso8601String(),
                'imported_from' => mb_substr(basename($originalFilename), 0, 255),
                'uploaded_files' => $fileCount,
                'deleted_files' => $deletedCount,
                'sources' => $sourceSummaries,
                'uploaded_paths' => [],
                'deleted_paths' => $deletedPaths,
            ];

            $manifestJson = json_encode($safeManifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (! is_string($manifestJson) || ! $local->put($runPath.'/manifest.json', $manifestJson)) {
                throw new RuntimeException('The validated backup manifest could not be saved.');
            }

            $absoluteRunPath = $local->path($runPath);
            @chmod($absoluteRunPath, 0770);

            if ($onProgress !== null) {
                $onProgress(100, 'File backup validated and ready to review.');
            }

            return [
                'mode' => $mode,
                'filename' => $runName,
                'run_path' => $runPath,
                'uploaded_files' => $fileCount,
                'deleted_files' => $deletedCount,
                'size' => $copiedBytes,
            ];
        } catch (Throwable $exception) {
            if ($runPath !== '') {
                Storage::disk('local')->deleteDirectory($runPath);
            }
            throw $exception;
        } finally {
            $zip->close();
        }
    }

    private function normalizeArchiveEntry(string $name, bool $isDirectory): string
    {
        if ($name === '' || str_contains($name, "\0") || str_contains($name, '\\') || preg_match('/[\x00-\x1F\x7F]/', $name)) {
            throw new RuntimeException('The ZIP archive contains an unsafe path.');
        }

        if (str_starts_with($name, './')) {
            $name = substr($name, 2);
        }
        if (str_starts_with($name, '/') || preg_match('/^[A-Za-z]:/', $name)) {
            throw new RuntimeException('The ZIP archive contains an absolute path.');
        }

        $normalized = $isDirectory ? rtrim($name, '/') : $name;
        if ($normalized === '') {
            return '';
        }

        foreach (explode('/', $normalized) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('The ZIP archive contains a path traversal entry.');
            }
        }

        return $normalized;
    }

    private function assertNotSpecialFile(ZipArchive $zip, int $index): void
    {
        $operatingSystem = 0;
        $attributes = 0;
        if (! $zip->getExternalAttributesIndex($index, $operatingSystem, $attributes) || $operatingSystem !== ZipArchive::OPSYS_UNIX) {
            return;
        }

        $type = ($attributes >> 16) & 0170000;
        if ($type !== 0 && ! in_array($type, [0100000, 0040000], true)) {
            throw new RuntimeException('The ZIP archive contains a symbolic link or special file.');
        }
    }

    private function assertExpandedDiskSpace(int $expandedBytes): void
    {
        $path = Storage::disk('local')->path(FileBackupService::BACKUP_ROOT);
        $freeBytes = @disk_free_space(dirname($path));
        if ($freeBytes === false) {
            throw new RuntimeException('Available server storage could not be checked before unpacking.');
        }

        if ($freeBytes < $expandedBytes + self::FREE_SPACE_RESERVE) {
            throw new RuntimeException('There is not enough free server storage to unpack this archive safely.');
        }
    }

    /** @param mixed $deletedPaths @param \Illuminate\Support\Collection<string, array{key:string,disk:string,path:string,label:string}> $sourceDefinitions @return array<string, array<int, string>> */
    private function safeDeletedPaths(mixed $deletedPaths, $sourceDefinitions): array
    {
        if (! is_array($deletedPaths)) {
            return [];
        }

        $safe = [];
        foreach ($deletedPaths as $sourceKey => $paths) {
            if (! is_string($sourceKey) || ! $sourceDefinitions->has($sourceKey) || ! is_array($paths)) {
                continue;
            }
            foreach ($paths as $path) {
                if (! is_string($path)) {
                    continue;
                }

                try {
                    $safePath = $this->normalizeArchiveEntry($path, false);
                    if ($safePath !== '') {
                        $safe[$sourceKey][] = $safePath;
                    }
                } catch (Throwable) {
                    // Skip unsafe deleted-path labels; they do not participate in restoring files.
                }
            }
        }

        return $safe;
    }
}
