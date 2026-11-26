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
                return $record->with(
                    message: 'Unhandled application exception',
                    context: array_merge(
                        array_intersect_key($record->context, array_flip(['error_id', 'route'])),
                        $this->exceptionContext($record->context['exception']),
                    ),
                    extra: [],
                );
            }

            return $record->with(context: $this->redact($record->context));
        });
    }

    private function exceptionContext(\Throwable $exception): array
    {
        $chain = [];
        $current = $exception;

        do {
            $chain[] = [
                'class' => $current::class,
                'message' => $this->redactMessage($current->getMessage()),
                'file' => $this->sourcePath($current->getFile()),
                'line' => $current->getLine(),
            ];
            $previous = $current->getPrevious();
            if ($previous === null || count($chain) >= 5) {
                break;
            }
            $current = $previous;
        } while (true);

        // Never format the Throwable itself: its trace can contain argument values.
        $frames = array_map(fn (array $frame): array => array_filter([
            'file' => isset($frame['file']) ? $this->sourcePath($frame['file']) : null,
            'line' => $frame['line'] ?? null,
            'class' => $frame['class'] ?? null,
            'function' => $frame['function'] ?? null,
        ], fn ($value) => $value !== null), array_slice($current->getTrace(), 0, 30));

        return [
            'exception_class' => $exception::class,
            'exception_message' => $chain[0]['message'],
            'exception_file' => $chain[0]['file'],
            'exception_line' => $chain[0]['line'],
            'previous_exceptions' => array_slice($chain, 1),
            'exception_chain_truncated' => $previous !== null,
            'stack_frames' => $frames,
        ];
    }

    private function sourcePath(string $path): string
    {
        $prefix = base_path().DIRECTORY_SEPARATOR;

        return str_starts_with($path, $prefix) ? substr($path, strlen($prefix)) : $path;
    }

    private function redactMessage(string $message): string
    {
        $message = substr($message, 0, 16384);
        // QueryException appends interpolated SQL and connection details.
        $message = preg_replace('/\s*\((?:Connection|SQL):.*$/is', ' ([database details redacted])', $message);
        $message = preg_replace('/\bBearer\s+\S+/i', 'Bearer [redacted]', $message);
        $message = preg_replace('~\b[a-z][a-z0-9+.-]*://[^\s<>]+~i', '[redacted URL]', $message);
        $message = preg_replace('/[a-z0-9._%+-]+@[a-z0-9.-]+\.[a-z]{2,}/i', '[redacted email]', $message);
        $message = preg_replace('/(?<!\w)\+?\d[\d ()-]{7,}\d(?!\w)/', '[redacted phone]', $message);
        $sensitiveAssignment = <<<'REGEX'
~(\b[\w.-]*(?:password|passwd|pwd|secret|token|api[_-]?key|access[_-]?key|authorization|cookie|payload|email|phone|address|body|bindings|query|credential|signature|session|csrf)[\w.-]*["']?\s*[:=]\s*).*~is
REGEX;
        $message = preg_replace($sensitiveAssignment, '$1[redacted]', $message);
        // Quoted values can contain customer data even without a recognizable key.
        $quotedValues = <<<'REGEX'
~"(?:\\.|[^"\\])*(?:"|$)|'(?:\\.|[^'\\])*(?:'|$)~s
REGEX;
        $message = preg_replace($quotedValues, '[redacted]', $message);

        return mb_strcut($message, 0, 2000, 'UTF-8');
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
