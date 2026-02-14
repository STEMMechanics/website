<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ServerController extends Controller
{
    public function admin_index(): View
    {
        $logPath = $this->getLaravelLogPath();
        $logData = $this->getFileData($logPath, 300);
        $deployLogPath = $this->getDeployOutputPath();
        $deployLogData = $this->getFileData($deployLogPath, 150);
        return view('admin.server.index', [
            'serverInfo' => $this->getServerInfo(),
            'logPath' => $logPath,
            'logExists' => $logData['exists'],
            'logSize' => $logData['size'],
            'logModifiedAt' => $logData['modified_at'],
            'logContent' => $logData['content'],
            'deployOutputPath' => $deployLogPath,
            'deployOutputExists' => $deployLogData['exists'],
            'deployOutputModifiedAt' => $deployLogData['modified_at'],
            'deployOutputContent' => $deployLogData['content'],
        ]);
    }

    public function admin_clear_log(): RedirectResponse
    {
        $logPath = $this->getLaravelLogPath();

        if (!file_exists($logPath)) {
            // Create the file to keep behavior predictable for the log viewer.
            file_put_contents($logPath, '');
        } else {
            file_put_contents($logPath, '');
        }

        session()->flash('message', 'laravel.log has been cleared');
        session()->flash('message-title', 'Log cleared');
        session()->flash('message-type', 'warning');

        return redirect()->route('admin.server.index');
    }

    public function admin_clear_deploy_log(): RedirectResponse
    {
        $logPath = $this->getDeployOutputPath();

        if (!file_exists($logPath)) {
            file_put_contents($logPath, '');
        } else {
            file_put_contents($logPath, '');
        }

        session()->flash('message', 'deploy.log has been cleared');
        session()->flash('message-title', 'Deploy log cleared');
        session()->flash('message-type', 'warning');

        return redirect()->route('admin.server.index');
    }

    public function admin_deploy(Request $request): RedirectResponse
    {
        $args = [];
        $label = [];
        if ($request->boolean('current')) {
            $args[] = '--current';
            $label[] = 'current';
        } else {
            $label[] = 'release';
        }
        if ($request->boolean('force')) {
            $args[] = '--force';
            $label[] = 'force';
        }

        return $this->startDeployProcess($args, 'Deploy started (' . implode(', ', $label) . ')');
    }

    public function admin_deploy_log(): JsonResponse
    {
        $deployLogPath = $this->getDeployOutputPath();
        $deployLogData = $this->getFileData($deployLogPath, 150);

        return response()->json([
            'exists' => $deployLogData['exists'],
            'modified_at' => $deployLogData['modified_at'],
            'content' => $deployLogData['content'],
        ]);
    }

    public function admin_laravel_log(): JsonResponse
    {
        $laravelLogPath = $this->getLaravelLogPath();
        $laravelLogData = $this->getFileData($laravelLogPath, 300);

        return response()->json([
            'exists' => $laravelLogData['exists'],
            'size' => $laravelLogData['size'],
            'modified_at' => $laravelLogData['modified_at'],
            'content' => $laravelLogData['content'],
        ]);
    }

    private function startDeployProcess(array $args, string $successTitle): RedirectResponse
    {
        $scriptPath = $this->getDeployScriptPath();
        $outputPath = $this->getDeployOutputPath();

        if (!function_exists('exec')) {
            session()->flash('message', 'Cannot run deploy script: PHP exec() is disabled');
            session()->flash('message-title', 'Deploy not started');
            session()->flash('message-type', 'danger');
            return redirect()->route('admin.server.index');
        }

        if (!is_file($scriptPath)) {
            session()->flash('message', "Deploy script not found at $scriptPath");
            session()->flash('message-title', 'Deploy not started');
            session()->flash('message-type', 'danger');
            return redirect()->route('admin.server.index');
        }

        if (!is_executable($scriptPath)) {
            session()->flash('message', "Deploy script is not executable: $scriptPath");
            session()->flash('message-title', 'Deploy not started');
            session()->flash('message-type', 'danger');
            return redirect()->route('admin.server.index');
        }

        $outputDir = dirname($outputPath);
        if (!is_dir($outputDir)) {
            @mkdir($outputDir, 0775, true);
        }

        $argString = '';
        foreach ($args as $arg) {
            $argString .= ' ' . escapeshellarg($arg);
        }

        $deployCommand = escapeshellarg($scriptPath) . $argString;
        $timestampedCommand = $deployCommand . " 2>&1 | awk '{ print strftime(\"[%Y-%m-%d %H:%M:%S]\"), \$0; fflush(); }'";

        @file_put_contents(
            $outputPath,
            '[' . date('Y-m-d H:i:s') . '] Starting deploy command: ' . $deployCommand . PHP_EOL
        );

        $command = sprintf(
            'nohup bash -lc %s >> %s 2>&1 & echo $!',
            escapeshellarg($timestampedCommand),
            escapeshellarg($outputPath)
        );

        $output = [];
        $status = 0;
        @exec($command, $output, $status);

        if ($status !== 0) {
            session()->flash('message', "Failed to start deploy script (exit $status). Check permissions for the web user.");
            session()->flash('message-title', 'Deploy not started');
            session()->flash('message-type', 'danger');
            return redirect()->route('admin.server.index');
        }

        $pid = trim((string) ($output[0] ?? ''));
        session()->flash('message', 'Deploy script started in background' . ($pid !== '' ? " (PID: $pid)" : '') . '.');
        session()->flash('message-title', $successTitle);
        session()->flash('message-type', 'success');

        return redirect()->route('admin.server.index');
    }

    private function getServerInfo(): array
    {
        $rootPath = '/';
        $storagePublicPath = storage_path('app');
        $diskFree = @disk_free_space($rootPath);

        return [
            'App Environment' => app()->environment(),
            'App Version' => config('app.version'),
            'App Commit' => (config('app.commit') ?: 'N/A'),
            'Laravel Version' => app()->version(),
            'PHP Version' => PHP_VERSION,
            'PHP SAPI' => PHP_SAPI,
            'Web Server' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
            'Operating System' => php_uname(),
            'Timezone' => config('app.timezone'),
            'Memory Limit' => ini_get('memory_limit'),
            'Max Execution Time' => ini_get('max_execution_time') . 's',
            'Upload Max Filesize' => ini_get('upload_max_filesize'),
            'Post Max Size' => ini_get('post_max_size'),
            'PHP INI File' => php_ini_loaded_file() ?: 'Unknown',
            'OPcache Enabled' => extension_loaded('Zend OPcache') ? 'Yes' : 'No',
            'Disk Free Space' => is_numeric($diskFree) ? $this->formatBytes((int) $diskFree) : 'N/A',
            'Storage Usage (storage/app)' => is_dir($storagePublicPath)
                ? $this->formatBytes($this->getDirectorySize($storagePublicPath))
                : 'N/A',
            'Loaded Extensions' => implode(', ', get_loaded_extensions()),
        ];
    }

    private function getLaravelLogPath(): string
    {
        return storage_path('logs/laravel.log');
    }

    private function getDeployScriptPath(): string
    {
        return env('DEPLOY_SCRIPT_PATH', '/app/deploy.sh');
    }

    private function getDeployOutputPath(): string
    {
        return env('DEPLOY_OUTPUT_LOG', '/var/tmp/stemmechanics_deploy.log');
    }

    private function getFileData(string $path, int $tailLines): array
    {
        $exists = file_exists($path);

        return [
            'exists' => $exists,
            'size' => $exists ? filesize($path) : 0,
            'modified_at' => $exists ? date('Y-m-d H:i:s', filemtime($path)) : null,
            'content' => $exists ? $this->tailFile($path, $tailLines) : '',
        ];
    }

    private function tailFile(string $path, int $lineCount = 300): string
    {
        $lines = [];
        $file = new \SplFileObject($path, 'r');
        $file->seek(PHP_INT_MAX);
        $lastLine = $file->key();
        $start = max(0, $lastLine - $lineCount);

        $file->seek($start);
        while (!$file->eof()) {
            $lines[] = rtrim((string) $file->current(), "\r\n");
            $file->next();
        }

        return trim(implode(PHP_EOL, $lines));
    }

    private function getDirectorySize(string $directory): int
    {
        $size = 0;

        try {
            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $size += $file->getSize();
                }
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return $size;
    }

    private function formatBytes(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes . ' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB', 'PB'];
        $value = $bytes / 1024;
        $index = 0;

        while ($value >= 1024 && $index < count($units) - 1) {
            $value /= 1024;
            $index++;
        }

        return number_format($value, 2) . ' ' . $units[$index];
    }
}
