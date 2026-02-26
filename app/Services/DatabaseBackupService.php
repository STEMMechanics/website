<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupService
{
    private string $backupDirectory;

    public function __construct()
    {
        $this->backupDirectory = storage_path('app/backups/database');
    }

    public function backupPath(string $filename): string
    {
        return $this->backupDirectory.'/'.ltrim($filename, '/');
    }

    public function createBackup(?string $prefix = null): string
    {
        $this->assertMysqlConnection();
        $this->ensureCommandAvailable('mysqldump');
        $this->ensureCommandAvailable('gzip');

        if (! is_dir($this->backupDirectory)) {
            mkdir($this->backupDirectory, 0775, true);
        }

        $database = (string) config('database.connections.mysql.database');
        $timestamp = now()->format('Ymd_His');
        $safePrefix = trim((string) ($prefix ?? $database));
        $safePrefix = preg_replace('/[^a-zA-Z0-9._-]/', '-', $safePrefix) ?: 'database';
        $filename = $safePrefix.'_'.$timestamp.'.sql.gz';
        $tmpSqlPath = $this->backupPath('.'.$filename.'.sql.tmp');
        $tmpGzPath = $this->backupPath('.'.$filename.'.gz.tmp');
        $finalPath = $this->backupPath($filename);

        $mysql = $this->mysqlConfig();

        $dumpArgs = [
            'mysqldump',
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
            '--add-drop-database',
            '--databases',
            $mysql['database'],
            '--result-file='.$tmpSqlPath,
        ];

        if ($this->supportsSetGtidPurgedFlag()) {
            $dumpArgs[] = '--set-gtid-purged=OFF';
        }

        $dumpProcess = new Process($dumpArgs, null, [
            'MYSQL_PWD' => $mysql['password'],
        ], null, 300);
        $dumpProcess->run();

        if (! $dumpProcess->isSuccessful()) {
            @unlink($tmpSqlPath);
            throw new RuntimeException('Database backup failed: '.$dumpProcess->getErrorOutput());
        }

        if (! is_file($tmpSqlPath) || filesize($tmpSqlPath) === 0) {
            @unlink($tmpSqlPath);
            throw new RuntimeException('Database backup failed: dump output is empty.');
        }

        $gzipProcess = Process::fromShellCommandline(
            'gzip -9 -c '.escapeshellarg($tmpSqlPath).' > '.escapeshellarg($tmpGzPath)
        );
        $gzipProcess->setTimeout(300);
        $gzipProcess->run();

        @unlink($tmpSqlPath);

        if (! $gzipProcess->isSuccessful()) {
            @unlink($tmpGzPath);
            throw new RuntimeException('Database backup failed during compression: '.$gzipProcess->getErrorOutput());
        }

        if (! is_file($tmpGzPath) || filesize($tmpGzPath) === 0) {
            @unlink($tmpGzPath);
            throw new RuntimeException('Database backup failed: compressed output is empty.');
        }

        $verifyProcess = new Process(['gzip', '-t', $tmpGzPath]);
        $verifyProcess->run();
        if (! $verifyProcess->isSuccessful()) {
            @unlink($tmpGzPath);
            throw new RuntimeException('Database backup failed: compressed output verification failed.');
        }

        if ((int) filesize($tmpGzPath) < 100) {
            @unlink($tmpGzPath);
            throw new RuntimeException('Database backup failed: output appears incomplete (too small).');
        }

        rename($tmpGzPath, $finalPath);

        return $finalPath;
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

    public function restoreBackup(string $sourcePath): void
    {
        $this->assertMysqlConnection();
        $this->ensureCommandAvailable('mysql');

        if (! is_file($sourcePath) || ! is_readable($sourcePath)) {
            throw new RuntimeException('Restore file not found or unreadable.');
        }

        $isGzip = str_ends_with(strtolower($sourcePath), '.gz');
        if ($isGzip) {
            $this->ensureCommandAvailable('gzip');
        }

        $mysql = $this->mysqlConfig();

        $inputCommand = $isGzip
            ? 'gzip -dc '.escapeshellarg($sourcePath)
            : 'cat '.escapeshellarg($sourcePath);

        $command = $inputCommand.' | mysql '
            .'--host='.escapeshellarg($mysql['host']).' '
            .'--port='.escapeshellarg((string) $mysql['port']).' '
            .'--user='.escapeshellarg($mysql['username']);

        $process = Process::fromShellCommandline($command, null, [
            'MYSQL_PWD' => $mysql['password'],
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

    private function ensureCommandAvailable(string $command): void
    {
        $process = Process::fromShellCommandline('command -v '.escapeshellarg($command));
        $process->run();

        if (! $process->isSuccessful()) {
            throw new RuntimeException("Required command '{$command}' is not available on the server.");
        }
    }

    private function supportsSetGtidPurgedFlag(): bool
    {
        $process = new Process(['mysqldump', '--help']);
        $process->setTimeout(10);
        $process->run();

        if (! $process->isSuccessful()) {
            return false;
        }

        return str_contains($process->getOutput(), '--set-gtid-purged');
    }
}
