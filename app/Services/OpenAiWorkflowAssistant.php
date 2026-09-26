<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;

class OpenAiWorkflowAssistant
{
    /**
     * @param  array<string, mixed>|list<array<string, mixed>>  $input
     * @param  array<string, mixed>  $schema
     * @return array<string, mixed>
     *
     * @throws JsonException
     */
    public function generateJson(string $instructions, array|string $input, string $schemaName, array $schema, ?string $reasoningEffort = null): array
    {
        $apiKey = trim((string) config('services.openai.api_key'));
        if ($apiKey === '') {
            throw new RuntimeException('AI drafting is not configured yet. Add OPENAI_API_KEY to the server environment.');
        }

        $reasoningEffort = trim((string) ($reasoningEffort ?: config('services.openai.reasoning_effort', 'max')));

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(max(10, (int) config('services.openai.timeout', 60)))
                ->post('https://api.openai.com/v1/responses', [
                    'model' => (string) config('services.openai.model', 'gpt-6-luna'),
                    'reasoning' => ['effort' => $reasoningEffort],
                    'instructions' => $instructions,
                    'input' => $input,
                    'store' => false,
                    'max_output_tokens' => 25000,
                    'text' => [
                        'format' => [
                            'type' => 'json_schema',
                            'name' => $schemaName,
                            'strict' => true,
                            'schema' => $schema,
                        ],
                    ],
                ]);
        } catch (\Throwable $exception) {
            Log::warning('OpenAI admin drafting request failed to connect.', ['exception' => $exception::class]);
            throw new RuntimeException('The AI service could not be reached. Please try again shortly.');
        }

        if (! $response->successful()) {
            $apiError = $response->json('error', []);
            Log::warning('OpenAI admin drafting request returned an error.', [
                'status' => $response->status(),
                'model' => (string) config('services.openai.model', 'gpt-6-luna'),
                'error_type' => is_array($apiError) ? ($apiError['type'] ?? null) : null,
                'error_code' => is_array($apiError) ? ($apiError['code'] ?? null) : null,
                'error_param' => is_array($apiError) ? ($apiError['param'] ?? null) : null,
                'error_message' => is_array($apiError) && is_string($apiError['message'] ?? null)
                    ? Str::limit(preg_replace('/\s+/', ' ', strip_tags($apiError['message'])), 400, '')
                    : null,
                'request_id' => $response->header('x-request-id'),
            ]);
            $providerMessage = is_array($apiError) && is_string($apiError['message'] ?? null)
                ? trim(preg_replace('/\s+/', ' ', strip_tags($apiError['message'])))
                : '';
            $message = match ($response->status()) {
                401, 403 => 'The AI service credentials or project do not have permission to process this request.',
                404 => 'The configured AI model was not found. Check the model setting.',
                413 => 'The PDF is too large for the AI service. Choose a smaller file.',
                429 => 'The AI service is busy or its API quota has been reached. Try again later.',
                400 => $providerMessage !== ''
                    ? 'The AI service rejected the request: '.Str::limit($providerMessage, 300, '')
                    : 'The AI service rejected the request (HTTP 400). Please try again.',
                default => 'The AI service returned an error (HTTP '.$response->status().'). Please try again.',
            };

            throw new RuntimeException($message);
        }

        $responseData = $response->json();
        if (($responseData['status'] ?? null) === 'incomplete') {
            $reason = (string) data_get($responseData, 'incomplete_details.reason', 'unknown');
            Log::warning('OpenAI admin drafting response was incomplete.', [
                'reason' => $reason,
                'model' => (string) config('services.openai.model', 'gpt-6-luna'),
                'output_tokens' => data_get($responseData, 'usage.output_tokens'),
                'reasoning_tokens' => data_get($responseData, 'usage.output_tokens_details.reasoning_tokens'),
            ]);

            throw new RuntimeException($reason === 'max_output_tokens'
                ? 'The AI response ran out of room before finishing. Please try again.'
                : 'The AI response did not finish. Please try again.');
        }

        $outputText = is_array($responseData) ? trim((string) ($responseData['output_text'] ?? '')) : '';

        if ($outputText === '' && is_array($responseData['output'] ?? null)) {
            foreach ($responseData['output'] as $outputItem) {
                foreach (($outputItem['content'] ?? []) as $contentItem) {
                    if (($contentItem['type'] ?? null) === 'output_text' && is_string($contentItem['text'] ?? null)) {
                        $outputText .= $contentItem['text'];
                    }
                }
            }
        }

        try {
            $decoded = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Log::warning('OpenAI admin drafting response was not valid JSON.');
            throw new RuntimeException('The AI returned an unreadable draft. Please try again.');
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('The AI returned an unreadable draft. Please try again.');
        }

        return $decoded;
    }

    /**
     * Stream a structured response, surfacing its document classification as soon as the model emits it.
     *
     * @param  array<string, mixed>|list<array<string, mixed>>  $input
     * @param  array<string, mixed>  $schema
     * @param  callable(string): void  $onDocumentType
     * @return array<string, mixed>
     */
    public function generateJsonStreaming(string $instructions, array|string $input, string $schemaName, array $schema, callable $onDocumentType): array
    {
        $apiKey = trim((string) config('services.openai.api_key'));
        if ($apiKey === '') {
            throw new RuntimeException('AI drafting is not configured yet. Add OPENAI_API_KEY to the server environment.');
        }

        $payload = [
            'model' => (string) config('services.openai.model', 'gpt-6-luna'),
            'reasoning' => ['effort' => (string) config('services.openai.reasoning_effort', 'max')],
            'instructions' => $instructions,
            'input' => $input,
            'store' => false,
            'stream' => true,
            'max_output_tokens' => 25000,
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $schemaName,
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
        ];

        try {
            $response = Http::withToken($apiKey)
                ->acceptJson()
                ->asJson()
                ->connectTimeout(10)
                ->timeout(max(10, (int) config('services.openai.timeout', 60)))
                ->withOptions(['stream' => true])
                ->post('https://api.openai.com/v1/responses', $payload);
        } catch (\Throwable $exception) {
            Log::warning('OpenAI admin drafting stream failed to connect.', ['exception' => $exception::class]);
            throw new RuntimeException('The AI service could not be reached. Please try again shortly.');
        }

        if (! $response->successful()) {
            $apiError = $response->json('error', []);
            Log::warning('OpenAI admin drafting stream returned an error.', [
                'status' => $response->status(),
                'model' => (string) config('services.openai.model', 'gpt-6-luna'),
                'error_type' => is_array($apiError) ? ($apiError['type'] ?? null) : null,
                'error_code' => is_array($apiError) ? ($apiError['code'] ?? null) : null,
                'error_param' => is_array($apiError) ? ($apiError['param'] ?? null) : null,
                'request_id' => $response->header('x-request-id'),
            ]);

            throw new RuntimeException(match ($response->status()) {
                401, 403 => 'The AI service credentials or project do not have permission to process this request.',
                404 => 'The configured AI model was not found. Check the model setting.',
                413 => 'The PDF is too large for the AI service. Choose a smaller file.',
                429 => 'The AI service is busy or its API quota has been reached. Try again later.',
                default => 'The AI service returned an error (HTTP '.$response->status().'). Please try again.',
            });
        }

        $outputText = '';
        $buffer = '';
        $completed = false;
        $incompleteReason = null;
        $streamError = false;
        $documentTypeSent = false;
        $consumeEvent = function (string $block) use (&$outputText, &$completed, &$incompleteReason, &$streamError, &$documentTypeSent, $onDocumentType): void {
            $dataLines = [];
            foreach (preg_split('/\r?\n/', $block) ?: [] as $line) {
                if (str_starts_with($line, 'data:')) {
                    $dataLines[] = ltrim(substr($line, 5));
                }
            }

            $event = json_decode(implode("\n", $dataLines), true);
            if (! is_array($event)) {
                return;
            }

            $eventType = (string) ($event['type'] ?? '');
            if ($eventType === 'response.output_text.delta' && is_string($event['delta'] ?? null)) {
                $outputText .= $event['delta'];
                if (! $documentTypeSent && preg_match('/"document_type"\s*:\s*("(?:\\\\.|[^"\\\\])*")/', $outputText, $matches) === 1) {
                    $documentType = json_decode($matches[1], true);
                    if (is_string($documentType) && trim($documentType) !== '') {
                        $documentTypeSent = true;
                        $onDocumentType(Str::limit(trim($documentType), 48, ''));
                    }
                }
            }

            if ($eventType === 'response.completed') {
                $completed = true;
                if (($event['response']['status'] ?? null) === 'incomplete') {
                    $incompleteReason = (string) data_get($event, 'response.incomplete_details.reason', 'unknown');
                }
            } elseif ($eventType === 'response.incomplete') {
                $incompleteReason = (string) data_get($event, 'response.incomplete_details.reason', 'unknown');
                $completed = true;
            } elseif ($eventType === 'response.failed' || $eventType === 'error') {
                $streamError = true;
                Log::warning('OpenAI admin drafting stream emitted an error.', [
                    'event_type' => $eventType,
                    'error_code' => data_get($event, 'response.error.code', $event['code'] ?? null),
                    'request_id' => data_get($event, 'response._request_id'),
                ]);
            }
        };

        try {
            $stream = $response->toPsrResponse()->getBody();
            while (! $stream->eof()) {
                $chunk = $stream->read(8192);
                if ($chunk === '') {
                    continue;
                }
                $buffer .= str_replace("\r\n", "\n", $chunk);
                while (($boundary = strpos($buffer, "\n\n")) !== false) {
                    $consumeEvent(substr($buffer, 0, $boundary));
                    $buffer = substr($buffer, $boundary + 2);
                }
            }
            if (trim($buffer) !== '') {
                $consumeEvent($buffer);
            }
        } catch (\Throwable $exception) {
            Log::warning('OpenAI admin drafting stream read failed.', ['exception' => $exception::class]);
            throw new RuntimeException('The AI service connection was interrupted. Please try again.');
        }

        if ($streamError) {
            throw new RuntimeException('The AI service could not complete this request. Please try again.');
        }

        if ($incompleteReason !== null) {
            Log::warning('OpenAI admin drafting stream was incomplete.', [
                'reason' => $incompleteReason,
                'model' => (string) config('services.openai.model', 'gpt-6-luna'),
            ]);
            throw new RuntimeException($incompleteReason === 'max_output_tokens'
                ? 'The AI response ran out of room before finishing. Please try again.'
                : 'The AI response did not finish. Please try again.');
        }

        if (! $completed) {
            throw new RuntimeException('The AI response did not finish. Please try again.');
        }

        try {
            $decoded = json_decode($outputText, true, 512, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            Log::warning('OpenAI admin drafting stream did not return valid JSON.');
            throw new RuntimeException('The AI returned an unreadable draft. Please try again.');
        }

        if (! is_array($decoded)) {
            throw new RuntimeException('The AI returned an unreadable draft. Please try again.');
        }

        return $decoded;
    }

    public function configured(): bool
    {
        return trim((string) config('services.openai.api_key')) !== '';
    }
}
