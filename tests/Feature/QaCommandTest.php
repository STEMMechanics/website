<?php

namespace Tests\Feature;

use App\Console\Commands\QaCommand;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\TestCase;

class QaCommandTest extends TestCase
{
    public function test_qa_command_runs_audit_by_default(): void
    {
        $command = $this->newInspectableQaCommand();
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        $exitCode = $command->handle();

        $this->assertSame(QaCommand::SUCCESS, $exitCode);
        $this->assertSame([
            'Running tests',
            'Running PHPStan',
            'Running composer audit',
            'Running npm audit',
        ], array_column($command->steps, 'title'));
    }

    public function test_qa_command_skips_audit_when_no_audit_is_set(): void
    {
        $command = $this->newInspectableQaCommand([
            'no-audit' => true,
        ]);
        $command->setOutput(new OutputStyle(new ArrayInput([]), new BufferedOutput()));

        $exitCode = $command->handle();

        $this->assertSame(QaCommand::SUCCESS, $exitCode);
        $this->assertSame([
            'Running tests',
            'Running PHPStan',
        ], array_column($command->steps, 'title'));
    }

    private function newInspectableQaCommand(array $overrides = []): InspectableQaCommand
    {
        return new InspectableQaCommand(array_merge([
            'without-tty' => true,
            'tests-only' => false,
            'stan-only' => false,
            'no-audit' => false,
            'test-memory-limit' => '1G',
            'stan-memory-limit' => '1G',
            'test' => [],
            'stan' => [],
        ], $overrides));
    }
}

final class InspectableQaCommand extends QaCommand
{
    /**
     * @var array<string, mixed>
     */
    private array $optionValues;

    /**
     * @var array<int, array{title: string, command: array<int, string>, without_tty: bool, env: array<string, string>}>
     */
    public array $steps = [];

    /**
     * @param array<string, mixed> $optionValues
     */
    public function __construct(array $optionValues)
    {
        parent::__construct();

        $this->optionValues = $optionValues;
    }

    public function option($key = null): mixed
    {
        if (is_array($key)) {
            return array_map(fn (string $option) => $this->option($option), $key);
        }

        return $this->optionValues[(string) $key] ?? null;
    }

    /**
     * @param array<int, string> $command
     * @param array<string, string> $env
     */
    protected function runStep(string $title, array $command, bool $withoutTty, array $env = []): int
    {
        $this->steps[] = [
            'title' => $title,
            'command' => $command,
            'without_tty' => $withoutTty,
            'env' => $env,
        ];

        return self::SUCCESS;
    }
}
