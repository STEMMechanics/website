<?php

namespace App\Logging;

use Illuminate\Log\Logger;
use Monolog\LogRecord;

class RedactSensitiveContext
{
    public function __invoke(Logger $logger): void
    {
        $logger->pushProcessor(function (LogRecord $record): LogRecord {
            if (app()->environment('local', 'testing')) {
                return $record;
            }
            if (($record->context['exception'] ?? null) instanceof \Throwable) {
                return $record->with(message: 'Unhandled application exception', context: [
                    'exception_class' => $record->context['exception']::class,
                ], extra: []);
            }

            return $record->with(context: $this->redact($record->context));
        });
    }

    private function redact(array $context): array
    {
        foreach ($context as $key => $value) {
            if (preg_match('/password|secret|token|authorization|cookie|payload|email|phone|address|body|bindings|query|trace/i', (string) $key)) {
                $context[$key] = '[redacted]';
            } elseif (is_array($value)) {
                $context[$key] = $this->redact($value);
            } elseif (is_object($value)) {
                $context[$key] = get_class($value);
            }
        }

        return $context;
    }
}
