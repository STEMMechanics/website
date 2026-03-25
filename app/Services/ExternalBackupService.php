<?php

namespace App\Services;

use App\Models\SiteOption;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ExternalBackupService
{
    public const OPTION_TARGET_DISK = 'backup.remote.disk';
    public const OPTION_TARGET_PATH = 'backup.remote.path';
    public const OPTION_INCLUDE_DATABASE = 'backup.remote.include-database';
    public const OPTION_INCLUDE_FILES = 'backup.remote.include-files';
    public const OPTION_FILES_MODE = 'backup.remote.files-mode';
    public const OPTION_FILE_SOURCES = 'backup.remote.file-sources';
    public const DEFAULT_TARGET_PATH = 'offsite-backups';

    public function __construct(
        private readonly DatabaseBackupService $databaseBackupService
    ) {}

    /**
     * @param array{
     *     disk?: string,
     *     path?: string,
     *     database?: bool|int|string|null,
     *     files?: bool|int|string|null,
     *     files_mode?: string|null,
     *     file_sources?: array<int, mixed>|string|null
     * } $overrides
     * @return array<string, mixed>
     */
    public function run(array $overrides = []): array
    {
        $config = $this->resolvedConfig($overrides);
        $this->assertConfiguration($config);

        $remote = Storage::disk($config['disk']);
        $runName = now()->format('Ymd_His').'_'.Str::lower(Str::random(6));
        $runPath = $this->joinPath($config['path'], $runName);

        $summary = [
            'disk' => $config['disk'],
            'path' => $config['path'],
            'run_path' => $runPath,
            'include_database' => $config['include_database'],
            'include_files' => $config['include_files'],
            'files_mode' => $config['files_mode'],
            'started_at' => now()->toIso8601String(),
            'database' => null,
            'files' => null,
        ];

        if ($config['include_database']) {
            $summary['database'] = $this->backupDatabase($remote, $runPath);
        }

        if ($config['include_files']) {
            $summary['files'] = $this->backupFiles($remote, $config, $runPath);
        }

        $summary['completed_at'] = now()->toIso8601String();

        $this->writeJson($remote, $this->joinPath($runPath, 'backup-manifest.json'), $summary);
        $this->writeJson($remote, $this->joinPath($config['path'], 'state', 'latest-run.json'), [
            'run_path' => $runPath,
            'completed_at' => $summary['completed_at'],
            'files_mode' => $config['files_mode'],
        ]);

        return $summary;
    }

    /**
     * @param array{
     *     disk?: string,
     *     path?: string,
     *     database?: bool|int|string|null,
     *     files?: bool|int|string|null,
     *     files_mode?: string|null,
     *     file_sources?: array<int, mixed>|string|null
     * } $overrides
     * @return array{
     *     disk: string,
     *     path: string,
     *     include_database: bool,
     *     include_files: bool,
     *     files_mode: string,
     *     sources: array<int, array{key: string, disk: string, path: string, label: string}>
     * }
     */
    public function resolvedConfig(array $overrides = []): array
    {
        return [
            'disk' => trim((string) ($overrides['disk'] ?? $this->optionValue(self::OPTION_TARGET_DISK, ''))),
            'path' => trim((string) ($overrides['path'] ?? $this->optionValue(self::OPTION_TARGET_PATH, self::DEFAULT_TARGET_PATH)), '/'),
            'include_database' => $this->resolveBoolean(
                $overrides['database'] ?? null,
                $this->optionValue(self::OPTION_INCLUDE_DATABASE, '1')
            ),
            'include_files' => $this->resolveBoolean(
                $overrides['files'] ?? null,
                $this->optionValue(self::OPTION_INCLUDE_FILES, '1')
            ),
            'files_mode' => $this->resolveFilesMode($overrides['files_mode'] ?? null),
            'sources' => $this->resolveFileSources($overrides['file_sources'] ?? null),
        ];
    }

    /**
     * @return array<int, array{key: string, disk: string, path: string, label: string}>
     */
    public static function defaultFileSources(): array
    {
        return [
            [
                'key' => 'media',
                'disk' => 'media',
                'path' => '',
                'label' => 'Media files',
            ],
            [
                'key' => 'finance',
                'disk' => 'local',
                'path' => 'finance',
                'label' => 'Finance files',
            ],
        ];
    }

    /**
     * @param array{
     *     disk: string,
     *     path: string,
     *     include_database: bool,
     *     include_files: bool,
     *     files_mode: string,
     *     sources: array<int, array{key: string, disk: string, path: string, label: string}>
     * } $config
     */
    private function assertConfiguration(array $config): void
    {
        if ($config['disk'] === '') {
            throw new RuntimeException('Remote backup target disk is not configured. Set site option backup.remote.disk or pass --disk=');
        }

        if (! array_key_exists($config['disk'], (array) config('filesystems.disks', []))) {
            throw new RuntimeException('Remote backup target disk ['.$config['disk'].'] is not defined in config/filesystems.php.');
        }

        if (! $config['include_database'] && ! $config['include_files']) {
            throw new RuntimeException('Remote backup has nothing to do. Enable database and/or file backups.');
        }

        if ($config['include_files'] && $config['sources'] === []) {
            throw new RuntimeException('Remote file backup sources are empty. Update site option backup.remote.file-sources.');
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function backupDatabase(FilesystemAdapter $remote, string $runPath): array
    {
        $localPath = $this->databaseBackupService->createBackup('remote');
        $remotePath = $this->joinPath($runPath, 'database', basename($localPath));

        $this->uploadLocalFile($remote, $localPath, $remotePath);

        return [
            'filename' => basename($localPath),
            'remote_path' => $remotePath,
            'size' => is_file($localPath) ? (int) (filesize($localPath) ?: 0) : 0,
        ];
    }

    /**
     * @param array{
     *     disk: string,
     *     path: string,
     *     include_database: bool,
     *     include_files: bool,
     *     files_mode: string,
     *     sources: array<int, array{key: string, disk: string, path: string, label: string}>
     * } $config
     * @return array<string, mixed>
     */
    private function backupFiles(FilesystemAdapter $remote, array $config, string $runPath): array
    {
        $currentManifest = $this->buildCurrentFilesManifest($config['sources']);
        $previousManifest = $config['files_mode'] === 'incremental'
            ? $this->loadStateManifest($remote, $config['path'])
            : ['sources' => []];

        $summary = [
            'mode' => $config['files_mode'],
            'uploaded_files' => 0,
            'deleted_files' => 0,
            'sources' => [],
        ];

        foreach ($currentManifest['sources'] as $key => $sourceManifest) {
            $previousFiles = $previousManifest['sources'][$key]['files'] ?? [];
            $currentFiles = $sourceManifest['files'];
            $pathsToUpload = [];

            if ($config['files_mode'] === 'full') {
                $pathsToUpload = array_keys($currentFiles);
            } else {
                foreach ($currentFiles as $relativePath => $meta) {
                    $previousMeta = $previousFiles[$relativePath] ?? null;
                    if (! is_array($previousMeta)
                        || (int) ($previousMeta['size'] ?? -1) !== (int) $meta['size']
                        || (int) ($previousMeta['last_modified'] ?? -1) !== (int) $meta['last_modified']) {
                        $pathsToUpload[] = $relativePath;
                    }
                }
            }

            sort($pathsToUpload);
            $deletedPaths = array_values(array_diff(array_keys($previousFiles), array_keys($currentFiles)));
            sort($deletedPaths);

            foreach ($pathsToUpload as $relativePath) {
                $meta = $currentFiles[$relativePath];
                $remotePath = $this->joinPath($runPath, 'files', $key, $relativePath);
                $this->copySourceFileToRemote($sourceManifest['disk'], (string) $meta['source_path'], $remote, $remotePath);
            }

            $summary['uploaded_files'] += count($pathsToUpload);
            $summary['deleted_files'] += count($deletedPaths);
            $summary['sources'][$key] = [
                'label' => $sourceManifest['label'],
                'disk' => $sourceManifest['disk'],
                'path' => $sourceManifest['path'],
                'total_files' => count($currentFiles),
                'uploaded_files' => count($pathsToUpload),
                'deleted_files' => count($deletedPaths),
                'uploaded_paths' => $pathsToUpload,
                'deleted_paths' => $deletedPaths,
            ];
        }

        $statePayload = [
            'generated_at' => now()->toIso8601String(),
            'sources' => [],
        ];

        foreach ($currentManifest['sources'] as $key => $sourceManifest) {
            $statePayload['sources'][$key] = [
                'label' => $sourceManifest['label'],
                'disk' => $sourceManifest['disk'],
                'path' => $sourceManifest['path'],
                'files' => [],
            ];

            foreach ($sourceManifest['files'] as $relativePath => $meta) {
                $statePayload['sources'][$key]['files'][$relativePath] = [
                    'size' => (int) $meta['size'],
                    'last_modified' => (int) $meta['last_modified'],
                ];
            }
        }

        $this->writeJson($remote, $this->joinPath($config['path'], 'state', 'files-manifest.json'), $statePayload);

        return $summary;
    }

    /**
     * @param array<int, array{key: string, disk: string, path: string, label: string}> $sources
     * @return array{sources: array<string, array{label: string, disk: string, path: string, files: array<string, array{size: int, last_modified: int, source_path: string}>}>}
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
     * @return array{sources: array<string, array{files?: array<string, array{size?: int, last_modified?: int}>}>}
     */
    private function loadStateManifest(FilesystemAdapter $remote, string $basePath): array
    {
        $path = $this->joinPath($basePath, 'state', 'files-manifest.json');
        if (! $remote->exists($path)) {
            return ['sources' => []];
        }

        $decoded = json_decode((string) $remote->get($path), true);
        if (! is_array($decoded) || ! isset($decoded['sources']) || ! is_array($decoded['sources'])) {
            throw new RuntimeException('Existing remote file manifest is invalid JSON.');
        }

        return $decoded;
    }

    private function uploadLocalFile(FilesystemAdapter $remote, string $localPath, string $remotePath): void
    {
        if (! is_file($localPath) || ! is_readable($localPath)) {
            throw new RuntimeException('Backup source file is missing or unreadable: '.$localPath);
        }

        $stream = fopen($localPath, 'r');
        if ($stream === false) {
            throw new RuntimeException('Could not open backup source file: '.$localPath);
        }

        try {
            if (! $remote->put($remotePath, $stream)) {
                throw new RuntimeException('Could not upload backup file to remote storage: '.$remotePath);
            }
        } finally {
            fclose($stream);
        }
    }

    private function copySourceFileToRemote(string $sourceDisk, string $sourcePath, FilesystemAdapter $remote, string $remotePath): void
    {
        $stream = Storage::disk($sourceDisk)->readStream($sourcePath);
        if ($stream === false) {
            throw new RuntimeException('Could not read source file for remote backup: '.$sourceDisk.'://'.$sourcePath);
        }

        try {
            if (! $remote->put($remotePath, $stream)) {
                throw new RuntimeException('Could not upload file to remote storage: '.$remotePath);
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
    private function writeJson(FilesystemAdapter $remote, string $path, array $payload): void
    {
        $encoded = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        if (! is_string($encoded)) {
            throw new RuntimeException('Could not encode remote backup metadata.');
        }

        if (! $remote->put($path, $encoded)) {
            throw new RuntimeException('Could not write remote backup metadata: '.$path);
        }
    }

    private function relativePathWithinSource(string $path, string $basePath): string
    {
        $normalizedPath = ltrim($path, '/');
        $normalizedBasePath = trim($basePath, '/');

        if ($normalizedBasePath === '') {
            return $normalizedPath;
        }

        if (Str::startsWith($normalizedPath, $normalizedBasePath.'/')) {
            return substr($normalizedPath, strlen($normalizedBasePath) + 1);
        }

        return $normalizedPath;
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

    private function optionValue(string $name, string $default = ''): string
    {
        try {
            if (! Schema::hasTable('site_options')) {
                return $default;
            }

            return trim((string) (SiteOption::value($name, $default) ?? $default));
        } catch (Throwable) {
            return $default;
        }
    }

    private function resolveBoolean(bool|int|string|null $override, string $default): bool
    {
        $value = $override;
        if ($value === null || trim((string) $value) === '') {
            $value = $default;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'yes', 'on'], true);
    }

    private function resolveFilesMode(?string $override): string
    {
        $mode = strtolower(trim((string) ($override ?? $this->optionValue(self::OPTION_FILES_MODE, 'incremental'))));
        if (! in_array($mode, ['full', 'incremental'], true)) {
            throw new RuntimeException('Remote backup files mode must be full or incremental.');
        }

        return $mode;
    }

    /**
     * @param array<int, mixed>|string|null $override
     * @return array<int, array{key: string, disk: string, path: string, label: string}>
     */
    private function resolveFileSources(array|string|null $override): array
    {
        $raw = $override;
        if ($raw === null || $raw === '') {
            $raw = $this->optionValue(
                self::OPTION_FILE_SOURCES,
                json_encode(static::defaultFileSources(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '[]'
            );
        }

        $decoded = is_array($raw) ? $raw : json_decode((string) $raw, true);
        if (! is_array($decoded)) {
            throw new RuntimeException('Remote backup file sources must be a JSON array.');
        }

        $resolved = [];
        foreach ($decoded as $index => $source) {
            if (! is_array($source)) {
                throw new RuntimeException('Remote backup file source at index '.$index.' is invalid.');
            }

            $disk = trim((string) ($source['disk'] ?? ''));
            $path = trim((string) ($source['path'] ?? ''), '/');
            $label = trim((string) ($source['label'] ?? ''));
            $key = trim((string) ($source['key'] ?? ''));

            if ($disk === '') {
                throw new RuntimeException('Remote backup file source at index '.$index.' is missing a disk.');
            }

            if (! array_key_exists($disk, (array) config('filesystems.disks', []))) {
                throw new RuntimeException('Remote backup file source disk ['.$disk.'] is not defined in config/filesystems.php.');
            }

            if ($key === '') {
                $key = Str::slug($label !== '' ? $label : $disk.($path !== '' ? '-'.$path : ''), '-');
            }

            if ($key === '') {
                throw new RuntimeException('Remote backup file source at index '.$index.' is missing a stable key.');
            }

            if ($label === '') {
                $label = Str::headline(str_replace('-', ' ', $key));
            }

            if (isset($resolved[$key])) {
                throw new RuntimeException('Remote backup file source key ['.$key.'] is duplicated.');
            }

            $resolved[$key] = [
                'key' => $key,
                'disk' => $disk,
                'path' => $path,
                'label' => $label,
            ];
        }

        return array_values($resolved);
    }
}
