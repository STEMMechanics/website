<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class TestCommand extends Command
{
    protected $signature = 'test
        {--without-tty : Disable TTY output for the child PHPUnit process}';

    protected $description = 'Run the application tests';

    protected function configure(): void
    {
        parent::configure();

        // Forward arbitrary options/arguments to PHPUnit.
        $this->ignoreValidationErrors();
    }

    public function handle(): int
    {
        $phpunit = base_path('vendor/bin/phpunit');

        if (! is_file($phpunit)) {
            $this->error('PHPUnit executable not found at vendor/bin/phpunit.');

            return self::FAILURE;
        }

        $forwardedArgs = $this->forwardedArgs();
        [$forwardedArgs, $withoutTty] = $this->stripWithoutTty($forwardedArgs);

        $command = array_merge([
            PHP_BINARY,
            $phpunit,
        ], $forwardedArgs);

        return $this->runPhpUnit($command, $withoutTty);
    }

    /**
     * @param array<int, string> $command
     */
    protected function runPhpUnit(array $command, bool $withoutTty): int
    {
        $process = new Process($command, base_path());
        $process->setTimeout(null);
        $process->setIdleTimeout(null);

        if (! $withoutTty && Process::isTtySupported()) {
            $process->setTty(true);
        }

        return $process->run(function (string $type, string $output): void {
            $this->output->write($output);
        });
    }

    /**
     * @return array<int, string>
     */
    private function forwardedArgs(): array
    {
        $argv = array_values(array_map('strval', $_SERVER['argv'] ?? []));
        $commandIndex = array_search('test', $argv, true);

        if ($commandIndex === false) {
            return [];
        }

        return array_slice($argv, $commandIndex + 1);
    }

    /**
     * @param array<int, string> $args
     * @return array{0: array<int, string>, 1: bool}
     */
    private function stripWithoutTty(array $args): array
    {
        $withoutTty = false;
        $remaining = [];

        foreach ($args as $arg) {
            if ($arg === '--without-tty') {
                $withoutTty = true;
                continue;
            }

            $remaining[] = $arg;
        }

        return [$remaining, $withoutTty];
    }
}
