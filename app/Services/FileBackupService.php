<?php

namespace App\Services;

use App\Models\SiteOption;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

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
        $this->normalizeLocalBackupPermissions($runPath);

        $uploadedFiles = 0;
        $uploadedPaths = [];
        $sourceSummaries = [];

        foreach ($currentManifest['sources'] as $sourceKey => $sourceManifest) {
            /** @var array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}> $sourceFiles */
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
        $this->normalizeLocalBackupPermissions($runPath);

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
     * @return array<int, array{filename: string, size: int, modified_at: string, file_count: int, mode: string, is_readable: bool, error: string|null}>
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

            try {
                $directories = $target->directories($baseDirectory);
            } catch (Throwable) {
                continue;
            }

            foreach ($directories as $directory) {
                $runName = basename($directory);
                if ($runName === 'state') {
                    continue;
                }

                $files = [];
                $size = 0;
                $isReadable = true;
                $error = null;
                $lastModified = 0;

                try {
                    $files = $target->allFiles($directory);
                    foreach ($files as $path) {
                        $size += (int) ($target->size($path) ?: 0);
                    }
                } catch (Throwable $e) {
                    $isReadable = false;
                    $error = $e->getMessage();
                }

                try {
                    $manifest = $this->readRunManifest(basename(dirname($directory)), $runName);
                } catch (Throwable $e) {
                    $manifest = [];
                    $isReadable = false;
                    $error ??= $e->getMessage();
                }

                try {
                    $lastModified = (int) ($target->lastModified($directory) ?: 0);
                } catch (Throwable $e) {
                    $isReadable = false;
                    $error ??= $e->getMessage();
                }

                $createdAt = isset($manifest['created_at'])
                    ? (string) $manifest['created_at']
                    : date('c', $lastModified);
                $modifiedAt = date('Y-m-d H:i:s', $lastModified);

                $backups[] = [
                    'filename' => $runName,
                    'mode' => basename(dirname($directory)),
                    'run_path' => $directory,
                    'manifest_path' => $this->joinPath($directory, 'manifest.json'),
                    'size' => $size,
                    'modified_at' => $modifiedAt,
                    'created_at' => $createdAt,
                    'window_hours' => isset($manifest['window_hours']) ? (int) $manifest['window_hours'] : null,
                    'file_count' => count($files),
                    'uploaded_files' => isset($manifest['uploaded_files']) ? (int) $manifest['uploaded_files'] : count($files),
                    'deleted_files' => isset($manifest['deleted_files']) ? (int) $manifest['deleted_files'] : 0,
                    'is_readable' => $isReadable,
                    'error' => $error,
                ];
            }
        }

        usort($backups, function (array $a, array $b): int {
            return strcmp((string) $b['created_at'], (string) $a['created_at']);
        });

        return $backups;
    }

    public function backupPath(string $mode, string $filename): string
    {
        return $this->joinPath($this->modeRoot($this->normalizeMode($mode)), ltrim($filename, '/'));
    }

    /**
     * @return array{
     *     backup: array<string, mixed>,
     *     run_path: string,
     *     path_prefix: string,
     *     entries: array<int, array<string, mixed>>,
     *     deleted_entries: array<int, array<string, mixed>>,
     *     breadcrumbs: array<int, array{label: string, path: string}>
     * }
     */
    public function inspectBackupRun(string $mode, string $filename, string $pathPrefix = ''): array
    {
        $mode = $this->normalizeMode($mode);
        $filename = basename(trim($filename));
        $pathPrefix = $this->normalizeRunRelativePath($pathPrefix);
        $target = Storage::disk('local');
        $runPath = $this->backupPath($mode, $filename);
        $manifest = $this->readRunManifest($mode, $filename);
        $currentManifest = $this->buildCurrentFilesManifest(ExternalBackupService::defaultFileSources());

        if (! $target->exists($runPath)) {
            throw new RuntimeException('File backup run not found.');
        }

        $entries = $this->buildBackupEntries($mode, $runPath, $manifest, $currentManifest, $pathPrefix);
        $deletedEntries = $this->buildDeletedEntries($manifest, $pathPrefix);

        return [
            'backup' => $this->backupSummaryForRun($mode, $filename, $manifest, $runPath),
            'run_path' => $runPath,
            'path_prefix' => $pathPrefix,
            'entries' => $entries,
            'deleted_entries' => $deletedEntries,
            'breadcrumbs' => $this->buildBreadcrumbs($pathPrefix),
        ];
    }

    /**
     * @param array<int, string> $selectedItems
     * @return array{restored_files: int, restored_paths: array<int, string>, skipped_paths: array<int, string>}
     */
    public function restoreBackupItems(string $mode, string $filename, array $selectedItems): array
    {
        $mode = $this->normalizeMode($mode);
        $filename = basename(trim($filename));
        $runPath = $this->backupPath($mode, $filename);
        $manifest = $this->readRunManifest($mode, $filename);
        $target = Storage::disk('local');

        if (! $target->exists($runPath)) {
            throw new RuntimeException('File backup run not found.');
        }

        $selectedItems = array_values(array_filter(array_map(function ($item): string {
            return $this->normalizeSelectedBackupItem((string) $item);
        }, $selectedItems), fn (string $item): bool => $item !== ''));

        if ($selectedItems === []) {
            return [
                'restored_files' => 0,
                'restored_paths' => [],
                'skipped_paths' => [],
            ];
        }

        $restoredFiles = 0;
        $restoredPaths = [];
        $skippedPaths = [];

        foreach ($this->expandSelectedBackupItems($runPath, $selectedItems) as $item) {
            $sourceInfo = $this->sourceInfoForBackupItem($manifest, $item['relative_path']);
            if ($sourceInfo === null) {
                $skippedPaths[] = $item['relative_path'];
                continue;
            }

            $destinationPath = $this->restoreDestinationPath($sourceInfo['path'], $item['source_relative_path']);
            $sourcePath = $this->joinPath($runPath, 'files', $item['relative_path']);

            if (! $target->exists($sourcePath)) {
                $skippedPaths[] = $item['relative_path'];
                continue;
            }

            $this->copyBetweenDisks('local', $sourcePath, $sourceInfo['disk'], $destinationPath);
            $restoredFiles++;
            $restoredPaths[] = $item['relative_path'];
        }

        return [
            'restored_files' => $restoredFiles,
            'restored_paths' => $restoredPaths,
            'skipped_paths' => $skippedPaths,
        ];
    }

    /**
     * @param array<int, string> $selectedItems
     * @return array{zip_path: string, zip_name: string, included_files: int, skipped_paths: array<int, string>}
     */
    public function downloadBackupItems(string $mode, string $filename, array $selectedItems): array
    {
        $mode = $this->normalizeMode($mode);
        $filename = basename(trim($filename));
        $runPath = $this->backupPath($mode, $filename);
        $target = Storage::disk('local');

        if (! $target->exists($runPath)) {
            throw new RuntimeException('File backup run not found.');
        }

        $selectedItems = array_values(array_filter(array_map(function ($item): string {
            return $this->normalizeSelectedBackupItem((string) $item);
        }, $selectedItems), fn (string $item): bool => $item !== ''));

        if ($selectedItems === []) {
            throw new RuntimeException('No file backup items were selected.');
        }

        $expandedItems = $this->expandSelectedBackupItems($runPath, $selectedItems);
        if ($expandedItems === []) {
            throw new RuntimeException('No downloadable files were found for the selected items.');
        }

        $zipPath = tempnam(sys_get_temp_dir(), 'file-backup-');
        if (! is_string($zipPath)) {
            throw new RuntimeException('Unable to create temporary file backup archive.');
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::OVERWRITE) !== true) {
            @unlink($zipPath);
            throw new RuntimeException('Unable to create file backup archive.');
        }

        $includedFiles = 0;
        $skippedPaths = [];
        foreach ($expandedItems as $item) {
            $sourcePath = $this->joinPath($runPath, 'files', $item['relative_path']);
            if (! $target->exists($sourcePath)) {
                $skippedPaths[] = $item['relative_path'];
                continue;
            }

            $zip->addFile($target->path($sourcePath), $item['relative_path']);
            $includedFiles++;
        }

        $zip->close();

        if ($includedFiles === 0) {
            @unlink($zipPath);
            throw new RuntimeException('No downloadable files were found for the selected items.');
        }

        return [
            'zip_path' => $zipPath,
            'zip_name' => sprintf('file-backup-%s.zip', $filename),
            'included_files' => $includedFiles,
            'skipped_paths' => $skippedPaths,
        ];
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
     * @return array{sources: array<string, array{label: string, disk: string, path: string, files: array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}>}>}
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
                    'hash' => $this->hashDiskFile($source['disk'], $normalizedPath),
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
     * @return array<string, mixed>
     */
    private function readRunManifest(string $mode, string $filename): array
    {
        $path = $this->joinPath($this->modeRoot($mode), $filename, 'manifest.json');
        $target = Storage::disk('local');

        if (! $target->exists($path)) {
            return [];
        }

        $decoded = json_decode((string) $target->get($path), true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{sources: array<string, array{label?: string, disk?: string, path?: string, files?: array<string, array{size?: int, last_modified?: int, hash?: string, source_disk?: string, source_path?: string}>}>}
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
     * @param array{sources: array<string, array{files: array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}>}>} $currentManifest
     * @return array<string, array{files: array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}>}>
     */
    private function selectAllFiles(array $currentManifest): array
    {
        /** @var array<string, array{files: array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}>}> $selected */
        $selected = [];

        foreach ($currentManifest['sources'] as $sourceKey => $sourceManifest) {
            $selected[$sourceKey] = [
                'files' => $sourceManifest['files'],
            ];
        }

        return $selected;
    }

    /**
     * @param array{sources: array<string, array{files: array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}>}>} $currentManifest
     * @return array<string, array{files: array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}>}>
     */
    private function selectRecentFiles(array $currentManifest, int $windowHours): array
    {
        /** @var array<string, array{files: array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}>}> $selected */
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
     * @param array{sources: array<string, array{files?: array<string, array{size?: int, last_modified?: int, hash?: string, source_disk?: string, source_path?: string}>}>} $previousManifest
     * @param array{sources: array<string, array{files: array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}>}>} $currentManifest
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
     * @param array{sources: array<string, array{label: string, disk: string, path: string, files: array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}>}>} $currentManifest
     * @return array{sources: array<string, array{label: string, disk: string, path: string, files: array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}>}>}
     */
    private function buildStateManifest(array $currentManifest): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'sources' => $currentManifest['sources'],
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<int, array<string, mixed>>
     */
    private function buildBackupEntries(string $mode, string $runPath, array $manifest, array $currentManifest, string $pathPrefix): array
    {
        $target = Storage::disk('local');
        $runFilesPrefix = $this->joinPath($runPath, 'files');
        $files = collect($target->allFiles($runFilesPrefix))
            ->map(fn (string $path): string => ltrim(trim($path), '/'))
            ->filter()
            ->values();

        $prefix = trim($pathPrefix, '/');
        $entriesByPath = [];
        $folderStats = [];
        $mediaGroups = [];

        foreach ($files as $path) {
            $relativePath = $this->relativeBackupItemPath($runFilesPrefix, $path);
            if ($relativePath === '') {
                continue;
            }

            if ($prefix !== '' && ! str_starts_with($relativePath, $prefix.'/') && $relativePath !== $prefix) {
                continue;
            }

            $trimmedPath = $prefix === ''
                ? $relativePath
                : (str_starts_with($relativePath, $prefix.'/') ? substr($relativePath, strlen($prefix) + 1) : '');

            if ($trimmedPath === '') {
                continue;
            }

            $firstSegment = explode('/', $trimmedPath, 2)[0];
            if ($firstSegment === '') {
                continue;
            }

            if (str_contains($trimmedPath, '/')) {
                $folderPath = $prefix === '' ? $firstSegment : $prefix.'/'.$firstSegment;
                $folderStats[$folderPath] ??= [
                    'type' => 'folder',
                    'path' => $folderPath,
                    'name' => $firstSegment,
                    'file_count' => 0,
                    'size' => 0,
                    'last_modified' => 0,
                    'status' => $mode === self::MODE_INCREMENTAL ? 'changed' : 'snapshot',
                ];
                $folderStats[$folderPath]['file_count']++;
                $folderStats[$folderPath]['size'] += (int) ($target->size($path) ?: 0);
                $folderStats[$folderPath]['last_modified'] = max(
                    (int) $folderStats[$folderPath]['last_modified'],
                    (int) ($target->lastModified($path) ?: 0)
                );
                continue;
            }

            $sourceInfo = $this->sourceInfoForBackupItem($manifest, $relativePath);
            if ($sourceInfo === null) {
                continue;
            }

            $currentState = $this->liveBackupItemState(
                $currentManifest,
                (string) $sourceInfo['key'],
                (string) $sourceInfo['source_relative_path'],
                $this->hashDiskFile('local', $path)
            );

            $fileEntry = [
                'type' => 'file',
                'path' => $relativePath,
                'name' => basename($relativePath),
                'source_key' => $sourceInfo['key'],
                'source_label' => $sourceInfo['label'],
                'source_disk' => $sourceInfo['disk'],
                'source_path' => $sourceInfo['path'],
                'source_relative_path' => $sourceInfo['source_relative_path'],
                'size' => (int) ($target->size($path) ?: 0),
                'last_modified' => (int) ($target->lastModified($path) ?: 0),
                'status' => $mode === self::MODE_INCREMENTAL ? 'changed' : 'snapshot',
                'current_state' => $currentState,
                'search_text' => strtolower(trim(implode(' ', array_filter([
                    basename($relativePath),
                    $relativePath,
                    (string) ($sourceInfo['label'] ?? ''),
                    (string) ($currentState['label'] ?? ''),
                ])))),
            ];

            if ($sourceInfo['key'] === 'media' && $prefix === 'media') {
                $baseName = $this->mediaGroupBaseName($relativePath);
                $basename = basename($relativePath);
                $groupKey = $sourceInfo['key'].'/'.$baseName;

                if ($baseName !== '' && $basename !== $baseName) {
                    $mediaGroups[$groupKey]['children'][$relativePath] = $fileEntry;
                    continue;
                }

                $mediaGroups[$groupKey]['base'] = $fileEntry;
                continue;
            }

            $entriesByPath[$relativePath] = $fileEntry;
        }

        foreach ($mediaGroups as $groupKey => $group) {
            $baseEntry = $group['base'] ?? null;
            $children = isset($group['children']) && is_array($group['children']) ? array_values($group['children']) : [];

            if (! is_array($baseEntry)) {
                foreach ($children as $childEntry) {
                    $entriesByPath[(string) ($childEntry['path'] ?? '')] = $childEntry;
                }
                continue;
            }

            if ($children === []) {
                $entriesByPath[(string) $baseEntry['path']] = $baseEntry;
                continue;
            }

            $groupState = $this->summarizeCurrentStates(array_merge([$baseEntry], $children));
            $groupSize = (int) ($baseEntry['size'] ?? 0);
            $groupLastModified = (int) ($baseEntry['last_modified'] ?? 0);
            foreach ($children as $childEntry) {
                $groupSize += (int) ($childEntry['size'] ?? 0);
                $groupLastModified = max($groupLastModified, (int) ($childEntry['last_modified'] ?? 0));
            }
            $groupSearchText = array_filter(array_merge(
                [(string) ($baseEntry['search_text'] ?? '')],
                array_map(fn (array $child): string => (string) ($child['search_text'] ?? ''), $children)
            ));

            $entriesByPath[(string) $baseEntry['path']] = array_merge($baseEntry, [
                'type' => 'media_group',
                'group_id' => (string) $groupKey,
                'group_state' => $groupState,
                'group_summary' => count($children) === 1
                    ? '1 variant'
                    : count($children).' variants',
                'group_children' => $children,
                'size' => $groupSize,
                'last_modified' => $groupLastModified,
                'search_text' => strtolower(trim(implode(' ', $groupSearchText))),
            ]);
        }

        $entries = array_values(array_merge($folderStats, $entriesByPath));
        usort($entries, function (array $a, array $b): int {
            $aType = (string) $a['type'];
            $bType = (string) $b['type'];
            if ($aType !== $bType) {
                return $aType === 'folder' ? -1 : 1;
            }

            return strcmp((string) $a['path'], (string) $b['path']);
        });

        return $entries;
    }

    private function mediaGroupBaseName(string $relativePath): string
    {
        $basename = basename($relativePath);
        $position = strpos($basename, '-');

        if ($position === false) {
            return $basename;
        }

        return substr($basename, 0, $position);
    }

    /**
     * @param array<int, array{current_state?: array{state?: string, label?: string, tone?: string}}> $states
     * @return array{state: string, label: string, tone: string}
     */
    private function summarizeCurrentStates(array $states): array
    {
        $hasMissing = false;
        $hasDifferent = false;

        foreach ($states as $state) {
            $value = (string) ($state['current_state']['state'] ?? '');
            if ($value === 'missing') {
                $hasMissing = true;
                continue;
            }

            if ($value === 'different') {
                $hasDifferent = true;
            }
        }

        if ($hasMissing) {
            return [
                'state' => 'missing',
                'label' => 'No longer on site',
                'tone' => 'danger',
            ];
        }

        if ($hasDifferent) {
            return [
                'state' => 'different',
                'label' => 'Changed on site',
                'tone' => 'warning',
            ];
        }

        return [
            'state' => 'same',
            'label' => 'Matches current site',
            'tone' => 'neutral',
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array<int, array<string, mixed>>
     */
    private function buildDeletedEntries(array $manifest, string $pathPrefix): array
    {
        $prefix = trim($pathPrefix, '/');
        $deleted = [];

        foreach (($manifest['deleted_paths'] ?? []) as $sourceKey => $paths) {
            if (! is_array($paths)) {
                continue;
            }

            foreach ($paths as $path) {
                $relativePath = $this->normalizeRunRelativePath((string) $path);
                $backupPath = $sourceKey.'/'.$relativePath;
                if ($prefix !== '' && ! str_starts_with($backupPath, $prefix.'/') && $backupPath !== $prefix) {
                    continue;
                }

                $deleted[] = [
                    'type' => 'deleted',
                    'path' => $backupPath,
                    'name' => basename($backupPath),
                    'source_key' => $sourceKey,
                    'source_relative_path' => $relativePath,
                    'status' => 'deleted',
                ];
            }
        }

        usort($deleted, fn (array $a, array $b): int => strcmp((string) $a['path'], (string) $b['path']));

        return $deleted;
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{key: string, label: string, disk: string, path: string, source_relative_path: string}|null
     */
    private function sourceInfoForBackupItem(array $manifest, string $relativePath): ?array
    {
        $relativePath = $this->normalizeRunRelativePath($relativePath);
        $sourceKey = explode('/', $relativePath, 2)[0];
        if ($sourceKey === '') {
            return null;
        }

        $sourceManifest = $manifest['sources'][$sourceKey] ?? null;
        if (! is_array($sourceManifest)) {
            return null;
        }

        $sourceRelativePath = ltrim(substr($relativePath, strlen($sourceKey)), '/');

        return [
            'key' => $sourceKey,
            'label' => (string) ($sourceManifest['label'] ?? $sourceKey),
            'disk' => (string) ($sourceManifest['disk'] ?? ''),
            'path' => (string) ($sourceManifest['path'] ?? ''),
            'source_relative_path' => $sourceRelativePath,
        ];
    }

    /**
     * @param array{sources: array<string, array{files: array<string, array{size: int, last_modified: int, hash: string, source_disk: string, source_path: string}>}>} $currentManifest
     * @return array{state: string, label: string, tone: string}
     */
    private function liveBackupItemState(array $currentManifest, string $sourceKey, string $sourceRelativePath, string $backupHash): array
    {
        $sourceManifest = $currentManifest['sources'][$sourceKey] ?? null;
        if (! is_array($sourceManifest)) {
            return [
                'state' => 'missing',
                'label' => 'No longer on site',
                'tone' => 'danger',
            ];
        }

        $currentFiles = $sourceManifest['files'] ?? [];
        if (! is_array($currentFiles) || ! isset($currentFiles[$sourceRelativePath])) {
            return [
                'state' => 'missing',
                'label' => 'No longer on site',
                'tone' => 'danger',
            ];
        }

        $currentFile = $currentFiles[$sourceRelativePath];
        $currentHash = (string) ($currentFile['hash'] ?? '');

        if ($currentHash !== '' && $backupHash !== '' && hash_equals($backupHash, $currentHash)) {
            return [
                'state' => 'same',
                'label' => 'Matches current site',
                'tone' => 'neutral',
            ];
        }

        return [
            'state' => 'different',
            'label' => 'Changed on site',
            'tone' => 'warning',
        ];
    }

    private function hashDiskFile(string $disk, string $path): string
    {
        $stream = Storage::disk($disk)->readStream($path);
        if ($stream === false) {
            throw new RuntimeException('Could not hash file: '.$disk.'://'.$path);
        }

        $context = hash_init('md5');

        try {
            hash_update_stream($context, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return hash_final($context);
    }

    /**
     * @param array<string, mixed> $manifest
     * @return array{
     *     filename: string,
     *     mode: string,
     *     run_path: string,
     *     manifest_path: string,
     *     created_at: string,
     *     window_hours: int|null,
     *     uploaded_files: int,
     *     deleted_files: int,
     *     file_count: int,
     *     size: int,
     *     sources: array<string, mixed>
     * }
     */
    private function backupSummaryForRun(string $mode, string $filename, array $manifest, string $runPath): array
    {
        $target = Storage::disk('local');
        $files = $target->allFiles($this->joinPath($runPath, 'files'));
        $size = 0;
        foreach ($files as $path) {
            $size += (int) ($target->size($path) ?: 0);
        }

        return [
            'filename' => $filename,
            'mode' => $mode,
            'run_path' => $runPath,
            'manifest_path' => $this->joinPath($runPath, 'manifest.json'),
            'created_at' => (string) ($manifest['created_at'] ?? ''),
            'window_hours' => isset($manifest['window_hours']) ? (int) $manifest['window_hours'] : null,
            'uploaded_files' => (int) ($manifest['uploaded_files'] ?? count($files)),
            'deleted_files' => (int) ($manifest['deleted_files'] ?? 0),
            'file_count' => count($files),
            'size' => $size,
            'sources' => $manifest['sources'] ?? [],
        ];
    }

    /**
     * @return array<int, array{label: string, path: string}>
     */
    private function buildBreadcrumbs(string $pathPrefix): array
    {
        $prefix = trim($pathPrefix, '/');
        if ($prefix === '') {
            return [];
        }

        $breadcrumbs = [];
        $parts = array_values(array_filter(explode('/', $prefix), fn (string $part): bool => $part !== ''));
        $accumulated = [];

        foreach ($parts as $part) {
            $accumulated[] = $part;
            $breadcrumbs[] = [
                'label' => $part,
                'path' => implode('/', $accumulated),
            ];
        }

        return $breadcrumbs;
    }

    /**
     * @param array<int, string> $selectedItems
     * @return array<int, array{relative_path: string, source_relative_path: string}>
     */
    private function expandSelectedBackupItems(string $runPath, array $selectedItems): array
    {
        $target = Storage::disk('local');
        $runFilesPrefix = $this->joinPath($runPath, 'files');
        $allBackupFiles = collect($target->allFiles($runFilesPrefix))
            ->map(fn (string $path): string => ltrim(trim($path), '/'))
            ->filter()
            ->values()
            ->all();

        $selected = [];
        foreach ($selectedItems as $item) {
            $item = $this->normalizeSelectedBackupItem($item);
            if ($item === '') {
                continue;
            }

            if (str_ends_with($item, '/')) {
                $prefix = rtrim($item, '/');
                foreach ($allBackupFiles as $path) {
                    $relative = $this->relativeBackupItemPath($runFilesPrefix, $path);
                    if ($relative === '' || ! str_starts_with($relative, $prefix.'/') && $relative !== $prefix) {
                        continue;
                    }

                    $selected[$relative] = [
                        'relative_path' => $relative,
                        'source_relative_path' => $this->sourceRelativePathFromBackupRelativePath($relative),
                    ];
                }

                continue;
            }

            $relative = $this->normalizeRunRelativePath($item);
            if ($relative === '') {
                continue;
            }

            $selected[$relative] = [
                'relative_path' => $relative,
                'source_relative_path' => $this->sourceRelativePathFromBackupRelativePath($relative),
            ];
        }

        ksort($selected);

        return array_values($selected);
    }

    private function relativeBackupItemPath(string $runFilesPrefix, string $absolutePath): string
    {
        $normalized = ltrim(trim($absolutePath), '/');
        $prefix = trim($runFilesPrefix, '/');
        if ($normalized === '' || $prefix === '' || ! str_starts_with($normalized, $prefix.'/')) {
            return '';
        }

        return substr($normalized, strlen($prefix) + 1);
    }

    private function normalizeSelectedBackupItem(string $item): string
    {
        $item = trim($item);
        $item = str_replace('\\', '/', $item);
        $item = ltrim($item, '/');

        return $item;
    }

    private function normalizeRunRelativePath(string $path): string
    {
        $path = $this->normalizeSelectedBackupItem($path);

        return trim($path, '/');
    }

    private function sourceRelativePathFromBackupRelativePath(string $relativePath): string
    {
        $relativePath = $this->normalizeRunRelativePath($relativePath);
        $parts = explode('/', $relativePath, 2);
        $sourceKey = $parts[0] ?? '';
        if ($sourceKey === '') {
            return '';
        }

        return $parts[1] ?? '';
    }

    private function restoreDestinationPath(string $sourcePath, string $sourceRelativePath): string
    {
        $sourcePath = trim($sourcePath, '/');
        $sourceRelativePath = trim($sourceRelativePath, '/');

        if ($sourcePath === '') {
            return $sourceRelativePath;
        }

        if ($sourceRelativePath === '') {
            return $sourcePath;
        }

        return $this->joinPath($sourcePath, $sourceRelativePath);
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

    private function copyBetweenDisks(string $sourceDisk, string $sourcePath, string $destinationDisk, string $destinationPath): void
    {
        $stream = Storage::disk($sourceDisk)->readStream($sourcePath);
        if ($stream === false) {
            throw new RuntimeException('Could not read source file for restore: '.$sourceDisk.'://'.$sourcePath);
        }

        try {
            if (! Storage::disk($destinationDisk)->put($destinationPath, $stream)) {
                throw new RuntimeException('Could not restore file to '.$destinationDisk.'://'.$destinationPath);
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

    private function normalizeLocalBackupPermissions(string $path): void
    {
        $absolutePath = Storage::disk('local')->path($path);
        if (! file_exists($absolutePath)) {
            return;
        }

        try {
            if (is_dir($absolutePath)) {
                @chmod($absolutePath, 0775);
                $iterator = new \RecursiveIteratorIterator(
                    new \RecursiveDirectoryIterator($absolutePath, \FilesystemIterator::SKIP_DOTS),
                    \RecursiveIteratorIterator::SELF_FIRST
                );

                foreach ($iterator as $item) {
                    @chmod($item->getPathname(), $item->isDir() ? 0775 : 0664);
                }

                return;
            }

            @chmod($absolutePath, 0664);
        } catch (Throwable) {
            // Best-effort only; listing code still handles unreadable backup runs gracefully.
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
