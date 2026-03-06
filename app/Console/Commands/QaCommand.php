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
        {--stan-memory-limit=1G : Memory limit passed to phpstan, e.g. 1G or 2G}
        {--test=* : Additional args passed to artisan test}
        {--stan=* : Additional args passed to phpstan analyse}';

    protected $description = 'Run quality checks (tests and PHPStan)';

    public function handle(): int
    {
        $testsOnly = (bool) $this->option('tests-only');
        $stanOnly = (bool) $this->option('stan-only');

        if ($testsOnly && $stanOnly) {
            $this->error('Use either --tests-only or --stan-only, not both.');

            return self::INVALID;
        }

        $withoutTty = (bool) $this->option('without-tty');

        if (! $stanOnly) {
            $testArgs = array_values(array_map('strval', (array) $this->option('test')));
            $testExitCode = $this->runStep(
                'Running tests',
                array_merge([PHP_BINARY, base_path('artisan'), '--env=testing', 'test'], $withoutTty ? ['--without-tty'] : [], $testArgs),
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

        $this->info('QA checks passed.');

        return self::SUCCESS;
    }

    /**
     * @param array<int, string> $command
     * @param array<string, string> $env
     */
    private function runStep(string $title, array $command, bool $withoutTty, array $env = []): int
    {
        $this->newLine();
        $this->line('<fg=cyan>'.$title.'</>');

        $process = new Process($command, base_path(), $env);

        if (! $withoutTty && Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process->run(function (string $type, string $output): void {
            $this->output->write($output);
        });
    }
}
