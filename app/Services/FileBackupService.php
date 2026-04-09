<?php

namespace App\Services;

use App\Models\SiteOption;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

class FileBackupService
{
    public const KEEP_COUNT_OPTION = 'backup.files.keep';
    public const FULL_KEEP_COUNT_OPTION = 'backup.files.full.keep';
    public const INCREMENTAL_KEEP_COUNT_OPTION = 'backup.files.incremental.keep';
    public const BACKUP_ROOT = 'backups/files';
    public const STATE_MANIFEST_PATH = self::BACKUP_ROOT.'/state/files-manifest.json';
    public const MODE_FULL = 'full';
    public const MODE_INCREMENTAL = 'incremental';
    public const DEFAULT_KEEP_COUNT = 35;
    public const DEFAULT_FULL_KEEP_COUNT = 12;
    public const DEFAULT_INCREMENTAL_KEEP_COUNT = 35;
    public const DEFAULT_INCREMENTAL_WINDOW_HOURS = 24;

    /**
     * @return array<string, mixed>
     */
    public function createFullBackup(int|string|null $keepCount = null): array
    {
        return $this->createBackup(self::MODE_FULL, null, $keepCount);
    }

    /**
     * @return array<string, mixed>
     */
    public function createIncrementalBackup(int|string|null $window = null, int|string|null $keepCount = null): array
    {
        return $this->createBackup(self::MODE_INCREMENTAL, $window, $keepCount);
    }

    /**
     * @return array<string, mixed>
     */
    public function createBackup(string $mode, int|string|null $window = null, int|string|null $keepCount = null): array
    {
        $mode = $this->normalizeMode($mode);
        $sources = ExternalBackupService::defaultFileSources();
        $currentManifest = $this->buildCurrentFilesManifest($sources);
        $previousManifest = $this->loadStateManifest();
        $timestamp = now()->format('Ymd_His');
        $runName = $mode === self::MODE_FULL
            ? $timestamp.'_full'
            : $timestamp.'_incremental_'.$this->resolveWindowHours($window).'h';
        $runPath = $this->joinPath(self::BACKUP_ROOT, $mode, $runName);

        $selectedFiles = $mode === self::MODE_FULL
            ? $this->selectAllFiles($currentManifest)
            : $this->selectRecentFiles($currentManifest, $this->resolveWindowHours($window));
        $deletedPathsBySource = $mode === self::MODE_INCREMENTAL
            ? $this->detectDeletedFiles($previousManifest, $currentManifest)
            : [];

        $target = Storage::disk('local');
        $target->makeDirectory($runPath);

        $uploadedFiles = 0;
        $uploadedPaths = [];
        $sourceSummaries = [];

        foreach ($currentManifest['sources'] as $sourceKey => $sourceManifest) {
            /** @var array<string, array{size: int, last_modified: int, source_disk: string, source_path: string}> $sourceFiles */
            $sourceFiles = $selectedFiles[$sourceKey]['files'] ?? [];
            $deletedPaths = $deletedPathsBySource[$sourceKey] ?? [];
            $sourceSummaries[$sourceKey] = [
                'label' => $sourceManifest['label'],
                'disk' => $sourceManifest['disk'],
                'path' => $sourceManifest['path'],
                'total_files' => count($sourceManifest['files']),
                'uploaded_files' => count($sourceFiles),
                'deleted_files' => count($deletedPaths),
                'uploaded_paths' => [],
                'deleted_paths' => array_values($deletedPaths),
            ];

            foreach ($sourceFiles as $relativePath => $meta) {
                $destinationPath = $this->joinPath($runPath, 'files', $sourceKey, $relativePath);
                $this->copySourceFile((string) $meta['source_disk'], (string) $meta['source_path'], $destinationPath);
                $uploadedFiles++;
                $uploadedPaths[] = $sourceKey.'/'.$relativePath;
                $sourceSummaries[$sourceKey]['uploaded_paths'][] = $relativePath;
            }
        }

        $manifest = [
            'mode' => $mode,
            'window_hours' => $mode === self::MODE_INCREMENTAL ? $this->resolveWindowHours($window) : null,
            'run_path' => $runPath,
            'created_at' => now()->toIso8601String(),
            'uploaded_files' => $uploadedFiles,
            'deleted_files' => $mode === self::MODE_INCREMENTAL
                ? array_sum(array_map('count', $deletedPathsBySource))
                : 0,
            'sources' => $sourceSummaries,
            'uploaded_paths' => $uploadedPaths,
            'deleted_paths' => $mode === self::MODE_INCREMENTAL
                ? $deletedPathsBySource
                : [],
        ];

        $this->writeJson($this->joinPath($runPath, 'manifest.json'), $manifest);
        $this->writeJson(self::STATE_MANIFEST_PATH, $this->buildStateManifest($currentManifest));

        $keep = $this->resolvedKeepCount($mode, $keepCount);
        $pruned = $this->pruneOldBackups($mode, $keep);

        return [
            'mode' => $mode,
            'window_hours' => $manifest['window_hours'],
            'run_path' => $runPath,
            'keep_count' => $keep,
            'pruned' => $pruned,
            'files' => [
                'mode' => $mode,
                'uploaded_files' => $uploadedFiles,
                'deleted_files' => $manifest['deleted_files'],
                'uploaded_paths' => $uploadedPaths,
                'deleted_paths' => $deletedPathsBySource,
                'sources' => $sourceSummaries,
            ],
        ];
    }

    public function resolvedKeepCount(string $mode = self::MODE_FULL, int|string|null $keepCount = null): int
    {
        if ($keepCount !== null && trim((string) $keepCount) !== '') {
            return max(1, (int) $keepCount);
        }

        $fallback = $this->defaultKeepCountForMode($mode);
        $optionName = $this->keepCountOptionForMode($mode);

        try {
            if (Schema::hasTable('site_options')) {
                $configured = trim((string) SiteOption::value($optionName, ''));
                if ($configured !== '' && is_numeric($configured)) {
                    return max(1, (int) $configured);
                }

                $legacy = trim((string) SiteOption::value(self::KEEP_COUNT_OPTION, (string) $fallback));
                if ($legacy !== '' && is_numeric($legacy)) {
                    return max(1, (int) $legacy);
                }
            }
        } catch (Throwable) {
            // Fall back to the hard-coded retention count when site options are unavailable.
        }

        return $fallback;
    }

    public function resolveWindowHours(int|string|null $window = null): int
    {
        if ($window === null || trim((string) $window) === '') {
            return self::DEFAULT_INCREMENTAL_WINDOW_HOURS;
        }

        $value = strtolower(trim((string) $window));
        $value = preg_replace('/\s+/', ' ', $value) ?? $value;

        if (in_array($value, ['a week', '1 week', 'week', 'weekly'], true)) {
            return 168;
        }

        if (in_array($value, ['a day', '1 day', 'day', 'daily'], true)) {
            return 24;
        }

        if (preg_match('/^(\d+)\s*(h|hr|hrs|hour|hours)?$/', $value, $matches)) {
            return max(1, (int) $matches[1]);
        }

        if (preg_match('/^(\d+)\s*(d|day|days)$/', $value, $matches)) {
            return max(1, (int) $matches[1]) * 24;
        }

        if (preg_match('/^(\d+)\s*(w|week|weeks)$/', $value, $matches)) {
            return max(1, (int) $matches[1]) * 168;
        }

        throw new RuntimeException('Incremental file backup window must be an hour, day, or week value such as 24h, 3d, or 1w.');
    }

    /**
     * @return array<int, array{filename: string, size: int, modified_at: string, file_count: int, mode: string}>
     */
    public function listBackups(?string $mode = null): array
    {
        $target = Storage::disk('local');
        $mode = $mode !== null ? $this->normalizeMode($mode) : null;
        $baseDirectories = $mode !== null
            ? [$this->modeRoot($mode)]
            : [$this->modeRoot(self::MODE_FULL), $this->modeRoot(self::MODE_INCREMENTAL)];

        $backups = [];
        foreach ($baseDirectories as $baseDirectory) {
            if (! $target->exists($baseDirectory)) {
                continue;
            }

            foreach ($target->directories($baseDirectory) as $directory) {
                $runName = basename($directory);
                if ($runName === 'state') {
                    continue;
                }

                $files = $target->allFiles($directory);
                $size = 0;
                foreach ($files as $path) {
                    $size += (int) ($target->size($path) ?: 0);
                }

                $backups[] = [
                    'filename' => $runName,
                    'mode' => basename(dirname($directory)),
                    'size' => $size,
                    'modified_at' => date('Y-m-d H:i:s', (int) ($target->lastModified($directory) ?: 0)),
                    'file_count' => count($files),
                ];
            }
        }

        usort($backups, fn (array $a, array $b): int => strcmp((string) $b['filename'], (string) $a['filename']));

        return $backups;
    }

    public function backupPath(string $mode, string $filename): string
    {
        return $this->joinPath($this->modeRoot($this->normalizeMode($mode)), ltrim($filename, '/'));
    }

    public function restoreStateManifest(): array
    {
        return $this->loadStateManifest();
    }

    public function pruneOldBackups(string $mode, int $keepCount = 14): int
    {
        $mode = $this->normalizeMode($mode);
        $keepCount = max(1, $keepCount);
        $target = Storage::disk('local');
        $baseDirectory = $this->modeRoot($mode);

        if (! $target->exists($baseDirectory)) {
            return 0;
        }

        $runs = array_values(array_filter(
            $target->directories($baseDirectory),
            fn (string $path): bool => basename($path) !== 'state'
        ));

        usort($runs, fn (string $a, string $b): int => strcmp(basename($b), basename($a)));

        if (count($runs) <= $keepCount) {
            return 0;
        }

        $removed = 0;
        foreach (array_slice($runs, $keepCount) as $directory) {
            if ($target->deleteDirectory($directory)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @param array<int, array{key: string, disk: string, path: string, label: string}> $sources
     * @return array{sources: array<string, array{label: string, disk: string, path: string, files: array<string, array{size: int, last_modified: int, source_disk: string, source_path: string}>}>}
     */
    private function buildCurrentFilesManifest(array $sources): array
    {
        $manifest = ['sources' => []];

        foreach ($sources as $source) {
            $disk = Storage::disk($source['disk']);
            $basePath = trim($source['path'], '/');
            $files = [];

            foreach ($disk->allFiles($basePath) as $path) {
                $normalizedPath = ltrim((string) $path, '/');
                if ($normalizedPath === '') {
                    continue;
                }

                $relativePath = $this->relativePathWithinSource($normalizedPath, $basePath);
                $files[$relativePath] = [
                    'size' => (int) $disk->size($normalizedPath),
                    'last_modified' => (int) $disk->lastModified($normalizedPath),
                    'source_disk' => $source['disk'],
                    'source_path' => $normalizedPath,
                ];
            }

            ksort($files);

            $manifest['sources'][$source['key']] = [
                'label' => $source['label'],
                'disk' => $source['disk'],
                'path' => $basePath,
                'files' => $files,
            ];
        }

        return $manifest;
    }

    /**
     * @return array{sources: array<string, array{label?: string, disk?: string, path?: string, files?: array<string, array{size?: int, last_modified?: int, source_disk?: string, source_path?: string}>}>}
     */
    private function loadStateManifest(): array
    {
        $target = Storage::disk('local');
        if (! $target->exists(self::STATE_MANIFEST_PATH)) {
            return ['sources' => []];
        }

        $decoded = json_decode((string) $target->get(self::STATE_MANIFEST_PATH), true);
        if (! is_array($decoded) || ! isset($decoded['sources']) || ! is_array($decoded['sources'])) {
            throw new RuntimeException('Existing file backup state manifest is invalid JSON.');
        }

        return $decoded;
    }

    /**
     * @param array{sources: array<string, array{files: array<string, array{size: int, last_modified: int, source_disk: string, source_path: string}>}>} $currentManifest
     * @return array<string, array{files: array<string, array{size: int, last_modified: int, source_disk: string, source_path: string}>}>
     */
    private function selectAllFiles(array $currentManifest): array
    {
        $selected = [];

        foreach ($currentManifest['sources'] as $sourceKey => $sourceManifest) {
            $selected[$sourceKey] = [
                'files' => $sourceManifest['files'],
            ];
        }

        return $selected;
    }

    /**
     * @param array{sources: array<string, array{files: array<string, array{size: int, last_modified: int, source_disk: string, source_path: string}>}>} $currentManifest
     * @return array<string, array{files: array<string, array{size: int, last_modified: int, source_disk: string, source_path: string}>}>
     */
    private function selectRecentFiles(array $currentManifest, int $windowHours): array
    {
        $selected = [];
        $cutoff = now()->subHours(max(1, $windowHours))->timestamp;

        foreach ($currentManifest['sources'] as $sourceKey => $sourceManifest) {
            $selected[$sourceKey] = ['files' => []];

            foreach ($sourceManifest['files'] as $relativePath => $meta) {
                if ((int) $meta['last_modified'] >= $cutoff) {
                    $selected[$sourceKey]['files'][$relativePath] = $meta;
                }
            }
        }

        return $selected;
    }

    /**
     * @param array{sources: array<string, array{files?: array<string, array{size?: int, last_modified?: int, source_disk?: string, source_path?: string}>}>} $previousManifest
     * @param array{sources: array<string, array{files: array<string, array{size: int, last_modified: int, source_disk: string, source_path: string}>}>} $currentManifest
     * @return array<string, array<int, string>>
     */
    private function detectDeletedFiles(array $previousManifest, array $currentManifest): array
    {
        $deleted = [];

        foreach ($currentManifest['sources'] as $sourceKey => $sourceManifest) {
            $previousFiles = $previousManifest['sources'][$sourceKey]['files'] ?? [];
            $currentFiles = $sourceManifest['files'];
            $deletedPaths = array_values(array_diff(array_keys((array) $previousFiles), array_keys($currentFiles)));
            sort($deletedPaths);
            $deleted[$sourceKey] = $deletedPaths;
        }

        return $deleted;
    }

    /**
     * @param array{sources: array<string, array{label: string, disk: string, path: string, files: array<string, array{size: int, last_modified: int, source_disk: string, source_path: string}>}>} $currentManifest
     * @return array{sources: array<string, array{label: string, disk: string, path: string, files: array<string, array{size: int, last_modified: int, source_disk: string, source_path: string}>}>}
     */
    private function buildStateManifest(array $currentManifest): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'sources' => $currentManifest['sources'],
        ];
    }

    private function copySourceFile(string $sourceDisk, string $sourcePath, string $destinationPath): void
    {
        $stream = Storage::disk($sourceDisk)->readStream($sourcePath);
        if ($stream === false) {
            throw new RuntimeException('Could not read source file for backup: '.$sourceDisk.'://'.$sourcePath);
        }

        $target = Storage::disk('local');
        try {
            if (! $target->put($destinationPath, $stream)) {
                throw new RuntimeException('Could not write file backup to local storage: '.$destinationPath);
            }
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function writeJson(string $path, array $payload): void
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded)) {
            throw new RuntimeException('Could not encode file backup metadata.');
        }

        if (! Storage::disk('local')->put($path, $encoded)) {
            throw new RuntimeException('Could not write file backup metadata: '.$path);
        }
    }

    private function normalizeMode(string $mode): string
    {
        $normalized = strtolower(trim($mode));
        if (! in_array($normalized, [self::MODE_FULL, self::MODE_INCREMENTAL], true)) {
            throw new RuntimeException('File backup mode must be full or incremental.');
        }

        return $normalized;
    }

    private function defaultKeepCountForMode(string $mode): int
    {
        return $mode === self::MODE_INCREMENTAL
            ? self::DEFAULT_INCREMENTAL_KEEP_COUNT
            : self::DEFAULT_FULL_KEEP_COUNT;
    }

    private function keepCountOptionForMode(string $mode): string
    {
        return $mode === self::MODE_INCREMENTAL
            ? self::INCREMENTAL_KEEP_COUNT_OPTION
            : self::FULL_KEEP_COUNT_OPTION;
    }

    private function modeRoot(string $mode): string
    {
        return $this->joinPath(self::BACKUP_ROOT, $this->normalizeMode($mode));
    }

    private function joinPath(string ...$segments): string
    {
        $clean = [];
        foreach ($segments as $segment) {
            $segment = trim($segment, '/');
            if ($segment === '') {
                continue;
            }
            $clean[] = $segment;
        }

        return implode('/', $clean);
    }

    private function relativePathWithinSource(string $path, string $basePath): string
    {
        $normalizedPath = ltrim($path, '/');
        $normalizedBasePath = trim($basePath, '/');

        if ($normalizedBasePath === '') {
            return $normalizedPath;
        }

        if (str_starts_with($normalizedPath, $normalizedBasePath.'/')) {
            return substr($normalizedPath, strlen($normalizedBasePath) + 1);
        }

        return $normalizedPath;
    }
}
