<?php

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Throwable;

class ServerMaintenanceService
{
    /**
     * Run the cache/queue maintenance commands used from the admin site options page.
     *
     * @return array{success: bool, message: string, commands: array<int, array{command: string, success: bool, exit_code: int|null, output: string, error: string|null}>}
     */
    public function refreshCachesAndRestartQueue(): array
    {
        $commands = [
            'optimize:clear',
            'config:clear',
            'cache:clear',
            'view:clear',
            'queue:restart',
        ];

        $results = [];
        $success = true;

        foreach ($commands as $command) {
            $results[] = $this->runCommand($command);
        }

        foreach ($results as $result) {
            if (! $result['success']) {
                $success = false;
                break;
            }
        }

        return [
            'success' => $success,
            'message' => $success
                ? 'Application caches were cleared and the queue restart was requested.'
                : 'One or more maintenance commands failed.',
            'commands' => $results,
        ];
    }

    /**
     * @return array{command: string, success: bool, exit_code: int|null, output: string, error: string|null}
     */
    private function runCommand(string $command): array
    {
        try {
            $exitCode = Artisan::call($command);
            $output = trim((string) Artisan::output());

            return [
                'command' => $command,
                'success' => $exitCode === 0,
                'exit_code' => $exitCode,
                'output' => $output,
                'error' => null,
            ];
        } catch (Throwable $throwable) {
            return [
                'command' => $command,
                'success' => false,
                'exit_code' => null,
                'output' => '',
                'error' => $throwable->getMessage(),
            ];
        }
    }
}
