<?php

namespace Tests\Feature;

use App\Console\Commands\TestCommand;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class TestCommandTest extends TestCase
{
    public function test_test_command_strips_without_tty_from_forwarded_arguments(): void
    {
        $originalArgv = $_SERVER['argv'] ?? null;
        $_SERVER['argv'] = ['artisan', 'test', '--without-tty', '--filter', 'Smoke'];

        try {
            $command = new InspectableTestCommand();
            $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

            $exitCode = $command->handle();

            $this->assertSame(TestCommand::SUCCESS, $exitCode);
            $this->assertCount(1, $command->commands);
            $this->assertSame([
                PHP_BINARY,
                base_path('vendor/bin/phpunit'),
                '--filter',
                'Smoke',
            ], $command->commands[0]['command']);
            $this->assertTrue($command->commands[0]['without_tty']);
        } finally {
            if ($originalArgv === null) {
                unset($_SERVER['argv']);
            } else {
                $_SERVER['argv'] = $originalArgv;
            }
        }
    }
}

final class InspectableTestCommand extends TestCommand
{
    /**
     * @var array<int, array{command: array<int, string>, without_tty: bool}>
     */
    public array $commands = [];

    /**
     * @param array<int, string> $command
     */
    protected function runPhpUnit(array $command, bool $withoutTty): int
    {
        $this->commands[] = [
            'command' => $command,
            'without_tty' => $withoutTty,
        ];

        return self::SUCCESS;
    }
}
