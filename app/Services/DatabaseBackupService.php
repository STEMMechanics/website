<?php

namespace App\Services;

use App\Models\SiteOption;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Throwable;

class DatabaseBackupService
{
    public const KEEP_COUNT_OPTION = 'backup.database.keep';
    public const DEFAULT_KEEP_COUNT = 168;

    private string $backupDirectory;
    private ?string $dumpCommand;
    private ?string $mysqlImportCommand;
    private ?string $gzipCommand;
    private ExecutableFinder $executableFinder;

    public function __construct()
    {
        $this->backupDirectory = storage_path('app/backups/database');
        $this->executableFinder = new ExecutableFinder;
        $this->dumpCommand = $this->resolveAvailableCommand(['mysqldump', 'mariadb-dump']);
        $this->mysqlImportCommand = $this->resolveAvailableCommand(['mysql', 'mariadb']);
        $this->gzipCommand = $this->resolveAvailableCommand(['gzip']);
    }

    public function backupPath(string $filename): string
    {
        return $this->backupDirectory.'/'.ltrim($filename, '/');
    }

    public function assertBackupEnvironment(): void
    {
        $this->assertMysqlConnection();
        $this->requireResolvedCommand($this->dumpCommand, ['mysqldump', 'mariadb-dump']);
        $this->requireResolvedCommand($this->gzipCommand, ['gzip']);
        $this->mysqlConfig();
    }

    public function createBackup(?string $prefix = null): string
    {
        $this->assertBackupEnvironment();
        $dumpCommand = $this->requireResolvedCommand($this->dumpCommand, ['mysqldump', 'mariadb-dump']);
        $gzipCommand = $this->requireResolvedCommand($this->gzipCommand, ['gzip']);

        if (! is_dir($this->backupDirectory)) {
            mkdir($this->backupDirectory, 0775, true);
        }

        $this->pruneTemporaryFiles();

        $database = (string) config('database.connections.mysql.database');
        $timestamp = now()->format('Ymd_His');
        $safePrefix = trim((string) ($prefix ?? $database));
        $safePrefix = preg_replace('/[^a-zA-Z0-9._-]/', '-', $safePrefix) ?: 'database';
        $filename = $safePrefix.'_'.$timestamp.'.sql.gz';
        $temporaryToken = bin2hex(random_bytes(8));
        $tmpSqlPath = $this->backupPath('.'.$filename.'.'.$temporaryToken.'.sql.tmp');
        $tmpGzPath = $this->backupPath('.'.$filename.'.'.$temporaryToken.'.gz.tmp');
        $finalPath = $this->backupPath($filename);

        try {
            $mysql = $this->mysqlConfig();

            $dumpArgs = [
                $dumpCommand,
                '--host='.$mysql['host'],
                '--port='.(string) $mysql['port'],
                '--user='.$mysql['username'],
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--events',
                '--hex-blob',
                '--default-character-set=utf8mb4',
                '--result-file='.$tmpSqlPath,
                $mysql['database'],
            ];

            if ($this->supportsSetGtidPurgedFlag($dumpCommand)) {
                $dumpArgs[] = '--set-gtid-purged=OFF';
            }

            $dumpProcess = new Process($dumpArgs, null, [
                'MYSQL_PWD' => $mysql['password'],
            ], null, 300);
            $dumpProcess->run();

            if (! $dumpProcess->isSuccessful()) {
                throw new RuntimeException('Database backup failed: '.$dumpProcess->getErrorOutput());
            }

            if (! is_file($tmpSqlPath) || filesize($tmpSqlPath) === 0) {
                throw new RuntimeException('Database backup failed: dump output is empty.');
            }

            $gzipProcess = Process::fromShellCommandline(
                escapeshellarg($gzipCommand).' -9 -c '.escapeshellarg($tmpSqlPath).' > '.escapeshellarg($tmpGzPath)
            );
            $gzipProcess->setTimeout(300);
            $gzipProcess->run();

            if (! $gzipProcess->isSuccessful()) {
                throw new RuntimeException('Database backup failed during compression: '.$gzipProcess->getErrorOutput());
            }

            if (! is_file($tmpGzPath) || filesize($tmpGzPath) === 0) {
                throw new RuntimeException('Database backup failed: compressed output is empty.');
            }

            $verifyProcess = new Process([$gzipCommand, '-t', $tmpGzPath]);
            $verifyProcess->run();
            if (! $verifyProcess->isSuccessful()) {
                throw new RuntimeException('Database backup failed: compressed output verification failed.');
            }

            if ((int) filesize($tmpGzPath) < 100) {
                throw new RuntimeException('Database backup failed: output appears incomplete (too small).');
            }

            if (! @rename($tmpGzPath, $finalPath)) {
                throw new RuntimeException('Database backup failed: unable to finalise compressed output.');
            }

            return $finalPath;
        } finally {
            foreach ([$tmpSqlPath, $tmpGzPath] as $temporaryPath) {
                if (is_file($temporaryPath)) {
                    @unlink($temporaryPath);
                }
            }
        }
    }

    public function resolvedKeepCount(int|string|null $keepCount = null): int
    {
        if ($keepCount !== null && trim((string) $keepCount) !== '') {
            return max(1, (int) $keepCount);
        }

        $fallback = self::DEFAULT_KEEP_COUNT;

        try {
            if (Schema::hasTable('site_options')) {
                $configured = trim((string) SiteOption::value(self::KEEP_COUNT_OPTION, (string) $fallback));
                if ($configured !== '' && is_numeric($configured)) {
                    return max(1, (int) $configured);
                }
            }
        } catch (Throwable) {
            // Fall back to the hard-coded retention count when site options are unavailable.
        }

        return $fallback;
    }

    public function pruneOldBackups(int $keepCount = 168): int
    {
        $keepCount = max(1, $keepCount);
        $backups = $this->listBackups();

        if (count($backups) <= $keepCount) {
            return 0;
        }

        $removed = 0;
        $filesToDelete = array_slice($backups, $keepCount);
        foreach ($filesToDelete as $backup) {
            $path = $this->backupPath($backup['filename']);
            if (is_file($path) && @unlink($path)) {
                $removed++;
            }
        }

        return $removed;
    }

    /**
     * @return array<int, array{filename: string, size: int, modified_at: string}>
     */
    public function listBackups(): array
    {
        if (! is_dir($this->backupDirectory)) {
            return [];
        }

        $this->pruneTemporaryFiles();

        $files = glob($this->backupDirectory.'/*.sql.gz') ?: [];
        rsort($files, SORT_STRING);

        $backups = [];
        foreach ($files as $path) {
            if (! is_file($path)) {
                continue;
            }

            $backups[] = [
                'filename' => basename($path),
                'size' => (int) (filesize($path) ?: 0),
                'modified_at' => date('Y-m-d H:i:s', (int) filemtime($path)),
            ];
        }

        return $backups;
    }

    private function pruneTemporaryFiles(): void
    {
        $cutoff = now()->subDay()->getTimestamp();
        $files = glob($this->backupDirectory.'/.'.'*.tmp') ?: [];

        foreach ($files as $path) {
            if (! is_file($path)) {
                continue;
            }

            $modifiedAt = filemtime($path);
            if ($modifiedAt !== false && $modifiedAt >= $cutoff) {
                continue;
            }

            @unlink($path);
        }
    }

    public function restoreBackup(string $sourcePath): void
    {
        $this->assertMysqlConnection();
        $mysqlImportCommand = $this->requireResolvedCommand($this->mysqlImportCommand, ['mysql', 'mariadb']);

        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException('Restore file not found or unreadable.');
        }

        $isGzip = str_ends_with(strtolower($sourcePath), '.gz');
        $gzipCommand = $isGzip
            ? $this->requireResolvedCommand($this->gzipCommand, ['gzip'])
            : null;

        $mysql = $this->mysqlConfig();
        $targetDatabase = str_replace('`', '``', $mysql['database']);
        $databaseDirectiveFilter = <<<'AWK'
{
    statement = $0
    sub(/^[[:space:]]*/, "", statement)
    upperStatement = toupper(statement)
    controlStatement = upperStatement
    sub(/^\/\*(!|M!)[0-9]*[[:space:]]*/, "", controlStatement)

    if (controlStatement ~ /^(DROP|CREATE|ALTER)[[:space:]]+(DATABASE|SCHEMA)([[:space:]]|;)/) {
        next
    }

    if (controlStatement ~ /^USE[[:space:]]+/) {
        print "USE `" ENVIRON["DB_IMPORT_TARGET"] "`;"
        next
    }

    # Dumped programmable objects can carry production-only account definers.
    gsub(/DEFINER[[:space:]]*=[[:space:]]*`[^`]*`@`[^`]*`/, "", statement)
    gsub(/DEFINER[[:space:]]*=[[:space:]]*'[^']*'@'[^']*'/, "", statement)

    print statement
}
AWK;

        $inputCommand = $isGzip
            ? escapeshellarg((string) $gzipCommand).' -dc '.escapeshellarg($sourcePath)
            : 'cat '.escapeshellarg($sourcePath);

        $command = $inputCommand.' | awk '.escapeshellarg($databaseDirectiveFilter).' | '.escapeshellarg($mysqlImportCommand).' '
            .'--host='.escapeshellarg($mysql['host']).' '
            .'--port='.escapeshellarg((string) $mysql['port']).' '
            .'--user='.escapeshellarg($mysql['username']).' '
            .'--database='.escapeshellarg($mysql['database']);

        $process = Process::fromShellCommandline($command, null, [
            'MYSQL_PWD' => $mysql['password'],
            'DB_IMPORT_TARGET' => $targetDatabase,
        ], null, 600);
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException('Database restore failed: '.$process->getErrorOutput());
        }
    }

    private function assertMysqlConnection(): void
    {
        if ((string) config('database.default') !== 'mysql') {
            throw new RuntimeException('Database backup supports only mysql connections.');
        }
    }

    /**
     * @return array{host: string, port: int, database: string, username: string, password: string}
     */
    private function mysqlConfig(): array
    {
        $host = (string) config('database.connections.mysql.host', '127.0.0.1');
        $port = (int) config('database.connections.mysql.port', 3306);
        $database = (string) config('database.connections.mysql.database', '');
        $username = (string) config('database.connections.mysql.username', '');
        $password = (string) config('database.connections.mysql.password', '');

        if ($database === '' || $username === '') {
            throw new RuntimeException('Missing database configuration for backup/restore.');
        }

        return [
            'host' => $host,
            'port' => $port,
            'database' => $database,
            'username' => $username,
            'password' => $password,
        ];
    }

    private function supportsSetGtidPurgedFlag(string $dumpCommand): bool
    {
        $process = new Process([$dumpCommand, '--help']);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            return false;
        }

        return str_contains($process->getOutput(), '--set-gtid-purged');
    }

    /**
     * @param array<int, string> $alternatives
     */
    private function requireResolvedCommand(?string $command, array $alternatives): string
    {
        if ($command !== null) {
            return $command;
        }

        throw new RuntimeException('Required command is not available on the server. Tried: '.implode(', ', $alternatives));
    }

    /**
     * @param array<int, string> $commands
     */
    private function resolveAvailableCommand(array $commands): ?string
    {
        foreach ($commands as $command) {
            if (trim($command) === '') {
                continue;
            }

            $executable = $this->executableFinder->find($command);
            if ($executable !== null) {
                return $executable;
            }
        }

        return null;
    }
}
