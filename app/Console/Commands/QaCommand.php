<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class QaCommand extends Command
{
    protected $signature = 'qa
        {--without-tty : Disable TTY output for child processes}
        {--tests-only : Run tests only}
        {--stan-only : Run PHPStan only}
        {--no-audit : Skip Composer and npm audits}
        {--test-memory-limit=1G : Memory limit passed to PHPUnit, e.g. 512M or 1G}
        {--stan-memory-limit=1G : Memory limit passed to phpstan, e.g. 1G or 2G}
        {--test=* : Additional args passed to artisan test}
        {--stan=* : Additional args passed to phpstan analyse}';

    protected $description = 'Run quality checks (tests, PHPStan, Composer audit, and npm audit)';

    public function handle(): int
    {
        $testsOnly = (bool) $this->option('tests-only');
        $stanOnly = (bool) $this->option('stan-only');

        if ($testsOnly && $stanOnly) {
            $this->error('Use either --tests-only or --stan-only, not both.');

            return self::INVALID;
        }

        $withoutTty = (bool) $this->option('without-tty');
        $skipAudit = (bool) $this->option('no-audit');

        if (! $stanOnly) {
            $testArgs = array_values(array_map('strval', (array) $this->option('test')));
            $testMemoryLimit = trim((string) $this->option('test-memory-limit'));
            $memoryArgs = $testMemoryLimit !== '' ? ['-d', 'memory_limit='.$testMemoryLimit] : [];
            $phpunit = base_path('vendor/bin/phpunit');
            if (! is_file($phpunit)) {
                $this->error('PHPUnit executable not found at vendor/bin/phpunit.');

                return self::FAILURE;
            }

            $testExitCode = $this->runStep(
                'Running tests',
                array_merge([PHP_BINARY], $memoryArgs, [$phpunit], $testArgs),
                $withoutTty,
                [
                    'APP_ENV' => 'testing',
                    'DB_CONNECTION' => 'sqlite',
                    'DB_DATABASE' => ':memory:',
                ]
            );

            if ($testExitCode !== 0) {
                return $testExitCode;
            }
        }

        if (! $testsOnly) {
            $phpstan = base_path('vendor/bin/phpstan');
            if (! is_file($phpstan)) {
                $this->error('PHPStan executable not found at vendor/bin/phpstan.');

                return self::FAILURE;
            }

            $stanArgs = array_values(array_map('strval', (array) $this->option('stan')));
            $stanMemoryLimit = trim((string) $this->option('stan-memory-limit'));
            $memoryArgs = $stanMemoryLimit !== '' ? ['--memory-limit='.$stanMemoryLimit] : [];
            $stanExitCode = $this->runStep(
                'Running PHPStan',
                array_merge([PHP_BINARY, $phpstan, 'analyse'], $memoryArgs, $stanArgs),
                $withoutTty
            );

            if ($stanExitCode !== 0) {
                return $stanExitCode;
            }
        }

        if (! $testsOnly && ! $stanOnly && ! $skipAudit) {
            $composerAuditExitCode = $this->runStep(
                'Running composer audit',
                ['composer', 'audit', '--no-interaction'],
                $withoutTty
            );

            if ($composerAuditExitCode !== 0) {
                return $composerAuditExitCode;
            }

            $npmAuditExitCode = $this->runStep(
                'Running npm audit',
                ['npm', 'audit', '--no-fund'],
                $withoutTty
            );

            if ($npmAuditExitCode !== 0) {
                return $npmAuditExitCode;
            }
        }

        $this->info('QA checks passed.');

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $command
     * @param array<string, string> $env
     */
    protected function runStep(string $title, array $command, bool $withoutTty, array $env = []): int
    {
        $this->newLine();
        $this->line('<fg=cyan>'.$title.'</>');

        $process = new Process($command, base_path(), $env);
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        if (! $withoutTty && Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process->run(function (string $type, string $output): void {
            $this->output->write($output);
        });
    }
}
