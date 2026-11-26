<?php

namespace Tests\Feature;

use App\Logging\RedactSensitiveContext;
use Illuminate\Database\QueryException;
use Illuminate\Log\Logger;
use Illuminate\Support\Facades\Blade;
use Illuminate\View\ViewException;
use Monolog\Handler\TestHandler;
use Monolog\LogRecord;
use Tests\TestCase;

class ProductionExceptionLoggingTest extends TestCase
{
    public function test_production_view_errors_include_the_underlying_error_and_source(): void
    {
        $cause = new \ErrorException('Undefined variable $files', 0, E_WARNING, base_path('resources/views/admin/workshop/files.blade.php'), 123);
        $exception = new ViewException('Undefined variable $files (View: '.base_path('resources/views/admin/workshop/files.blade.php').')', 0, E_WARNING, $cause->getFile(), $cause->getLine(), $cause);

        $record = $this->logException($exception, ['error_id' => 'reference-123', 'route' => 'admin.workshop.media']);

        $this->assertSame('Unhandled application exception', $record->message);
        $this->assertSame(ViewException::class, $record->context['exception_class']);
        $this->assertStringContainsString('Undefined variable $files', $record->context['exception_message']);
        $this->assertSame('resources/views/admin/workshop/files.blade.php', $record->context['exception_file']);
        $this->assertSame(123, $record->context['exception_line']);
        $this->assertSame(\ErrorException::class, $record->context['previous_exceptions'][0]['class']);
        $this->assertSame('Undefined variable $files', $record->context['previous_exceptions'][0]['message']);
        $this->assertSame('reference-123', $record->context['error_id']);
        $this->assertSame('admin.workshop.media', $record->context['route']);
        $this->assertNotEmpty($record->context['stack_frames']);
        $this->assertArrayNotHasKey('exception', $record->context);
    }

    public function test_exception_arguments_context_and_extra_are_not_logged(): void
    {
        $original = ini_set('zend.exception_ignore_args', '0');
        try {
            $exception = $this->exceptionWithArgument('private-argument-value');
        } finally {
            ini_set('zend.exception_ignore_args', $original);
        }
        $this->assertSame('private-argument-value', $exception->getTrace()[0]['args'][0]);
        $record = $this->logException($exception, ['payload' => 'private-context-value']);
        $encoded = json_encode($record->toArray(), JSON_THROW_ON_ERROR);

        $this->assertStringNotContainsString('private-argument-value', $encoded);
        $this->assertStringNotContainsString('private-context-value', $encoded);
        foreach ($record->context['stack_frames'] as $frame) {
            $this->assertArrayNotHasKey('args', $frame);
            $this->assertArrayNotHasKey('object', $frame);
        }
        $this->assertSame([], $record->extra);
    }

    public function test_a_real_blade_render_failure_keeps_the_original_php_error(): void
    {
        try {
            Blade::render('{{ $missingWorkshopMedia }}', [], deleteCachedView: true);
            $this->fail('The missing view variable should throw.');
        } catch (ViewException $exception) {
            $record = $this->logException($exception);
        }

        $this->assertStringContainsString('Undefined variable $missingWorkshopMedia', $record->context['exception_message']);
        $this->assertSame(\ErrorException::class, $record->context['previous_exceptions'][0]['class']);
        $this->assertStringContainsString('Undefined variable $missingWorkshopMedia', $record->context['previous_exceptions'][0]['message']);
        $this->assertNotEmpty($record->context['exception_file']);
        $this->assertGreaterThan(0, $record->context['exception_line']);
    }

    public function test_exception_messages_redact_credentials_contact_details_urls_and_quoted_values(): void
    {
        foreach ([
            'Failure for customer@example.com' => 'customer@example.com',
            'Failure calling https://user:pass@example.com/path?token=private-url' => 'private-url',
            'Failure with Bearer private-bearer' => 'private-bearer',
            'Failure password=private-password another=value' => 'private-password',
            'Failure token: private-token' => 'private-token',
            'Failure access_token=private-access-token' => 'private-access-token',
            'Failure client_secret: private-client-secret' => 'private-client-secret',
            'Failure apiKey=private-api-key' => 'private-api-key',
            'Failure Authorization: Basic private-basic-auth' => 'private-basic-auth',
            'Failure {"password":"private-json-password"}' => 'private-json-password',
            'Failure Cookie: session=private-session; other=private-cookie' => 'private-cookie',
            'Failure for "Customer Name"' => 'Customer Name',
            "Failure for 'Customer Name'" => 'Customer Name',
            'Failure calling +61 412 345 678' => '412 345 678',
        ] as $message => $privateValue) {
            $record = $this->logException(new \RuntimeException($message));
            $this->assertStringNotContainsString($privateValue, $record->context['exception_message']);
            $this->assertStringContainsString('Failure', $record->context['exception_message']);
        }
    }

    public function test_wrapped_query_exceptions_do_not_log_interpolated_sql_or_bindings(): void
    {
        $query = new QueryException('mysql', 'select * from customers where private_id = ?', ['private-binding'], new \PDOException('SQLSTATE[HY000]: General error: 1 no such table: customers'));
        $exception = new \RuntimeException($query->getMessage(), 0, $query);
        $record = $this->logException($exception);
        $encoded = json_encode($record->context, JSON_THROW_ON_ERROR);

        $this->assertStringContainsString('SQLSTATE[HY000]', $encoded);
        $this->assertStringContainsString('database details redacted', $encoded);
        $this->assertStringNotContainsString('select * from customers', $encoded);
        $this->assertStringNotContainsString('private-binding', $encoded);
    }

    public function test_diagnostic_messages_and_exception_chains_are_bounded(): void
    {
        $exception = new \RuntimeException(str_repeat('x', 20000));
        $record = $this->logException($exception);
        $this->assertLessThanOrEqual(2000, strlen($record->context['exception_message']));
        for ($i = 0; $i < 10; $i++) {
            $exception = new \RuntimeException('Wrapped error', 0, $exception);
        }
        $record = $this->logException($exception);
        $this->assertCount(4, $record->context['previous_exceptions']);
        $this->assertTrue($record->context['exception_chain_truncated']);
        $this->assertLessThanOrEqual(30, count($record->context['stack_frames']));
    }

    public function test_local_and_testing_environments_keep_the_existing_exception_behavior(): void
    {
        foreach (['local', 'testing'] as $environment) {
            $this->app['env'] = $environment;
            $exception = new \RuntimeException('Development detail');
            $record = $this->capture(['exception' => $exception]);
            $this->assertSame($exception, $record->context['exception']);
            $this->assertSame('Original exception message', $record->message);
        }
    }

    private function exceptionWithArgument(string $privateValue): \RuntimeException
    {
        return new \RuntimeException('Call to undefined method App\\Models\\Workshop::missingMethod()');
    }

    private function logException(\Throwable $exception, array $context = []): LogRecord
    {
        $this->app['env'] = 'production';

        return $this->capture(['exception' => $exception, ...$context]);
    }

    private function capture(array $context): LogRecord
    {
        $handler = new TestHandler;
        $logger = new Logger(new \Monolog\Logger('diagnostic-test', [$handler]));
        (new RedactSensitiveContext)($logger);
        $logger->error('Original exception message', $context);

        return $handler->getRecords()[0];
    }
}
