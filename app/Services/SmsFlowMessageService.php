<?php

namespace App\Services;

use App\Models\SentSms;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class SmsFlowMessageService
{
    public function __construct(
        private readonly SmsFlowService $smsFlowService
    ) {}

    /**
     * @param  array{
     *     origin?: string,
     *     reference?: string,
     *     from_number?: string,
     *     callback_url?: string,
     *     recipient_name?: string,
     *     initiated_by_user_id?: string|null,
     *     initiated_by_name?: string|null,
     *     context?: array<string, mixed>
     * }  $options
     */
    public function send(string $recipient, string $message, array $options = []): SentSms
    {
        $recipient = trim($recipient);
        $message = trim($message);
        if ($recipient === '') {
            throw new RuntimeException('Recipient phone number is required.');
        }
        if ($message === '') {
            throw new RuntimeException('SMS message body is required.');
        }

        $sms = SentSms::query()->create([
            'recipient' => $recipient,
            'recipient_name' => trim((string) ($options['recipient_name'] ?? '')) ?: null,
            'message' => $message,
            'status' => SentSms::STATUS_QUEUED,
            'from_number' => $this->resolveFromNumber($options),
            'origin' => trim((string) ($options['origin'] ?? '')) ?: null,
            'reference' => trim((string) ($options['reference'] ?? '')) ?: null,
            'initiated_by_user_id' => $this->resolveInitiatedByUserId($options),
            'initiated_by_name' => trim((string) ($options['initiated_by_name'] ?? '')) ?: null,
            'context' => $this->normalizeContext($options['context'] ?? null),
        ]);

        try {
            $payload = [
                'to' => $this->smsFlowService->normalizePhoneNumber($recipient),
                'body' => $message,
            ];

            $from = $this->resolveFromNumber($options);
            if ($from !== null) {
                $payload['from'] = $from;
            }

            $callbackUrl = trim((string) ($options['callback_url'] ?? config('services.smsflow.callback_url', '')));
            if ($callbackUrl !== '') {
                $payload['callback_url'] = $callbackUrl;
            }

            $reference = trim((string) ($options['reference'] ?? ''));
            if ($reference !== '') {
                $payload['reference'] = $reference;
            }

            $response = $this->smsFlowService->sendRaw($payload);
            $decoded = $response->json();
            $sms->response_status = $response->status();
            $sms->response_payload = is_array($decoded) ? $decoded : ['body' => $response->body()];

            if (! $response->successful()) {
                $sms->status = SentSms::STATUS_FAILED;
                $sms->failed_at = now();
                $sms->error_message = $this->extractResponseError($response);
                $sms->save();

                throw new RuntimeException($sms->error_message ?? 'SMSFlow send SMS failed.');
            }

            $sms->provider_message_id = $this->extractProviderMessageId($decoded);
            $sms->status = SentSms::STATUS_SENT;
            $sms->sent_at = now();
            $sms->failed_at = null;
            $sms->error_message = null;
            $sms->save();

            return $sms->refresh();
        } catch (Throwable $exception) {
            if ($sms->exists && $sms->status !== SentSms::STATUS_SENT) {
                $sms->status = SentSms::STATUS_FAILED;
                $sms->failed_at = now();
                $sms->error_message = mb_substr($exception->getMessage(), 0, 5000);
                $sms->save();
            }

            throw $exception;
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    public function accountBalance(): ?array
    {
        if (! $this->smsFlowService->isConfigured()) {
            return null;
        }

        return $this->smsFlowService->getAccountBalance();
    }

    /**
     * @param  array<string, mixed>|null  $context
     * @return array<string, mixed>|null
     */
    private function normalizeContext(mixed $context): ?array
    {
        if (! is_array($context)) {
            return null;
        }

        return $context;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveFromNumber(array $options): ?string
    {
        $from = trim((string) ($options['from_number'] ?? config('services.smsflow.from', '')));

        return $from !== '' ? $from : null;
    }

    /**
     * @param  array<string, mixed>  $options
     */
    private function resolveInitiatedByUserId(array $options): ?string
    {
        $userId = trim((string) ($options['initiated_by_user_id'] ?? (string) Auth::id()));

        return $userId !== '' ? $userId : null;
    }

    private function extractProviderMessageId(mixed $decoded): ?string
    {
        foreach ($this->walkDecoded($decoded) as $value) {
            if (is_array($value) && isset($value['message_id'])) {
                $messageId = trim((string) $value['message_id']);
                if ($messageId !== '') {
                    return $messageId;
                }
            }
        }

        return null;
    }

    private function extractResponseError(Response $response): string
    {
        $decoded = $response->json();
        if (is_array($decoded)) {
            $message = trim((string) ($decoded['message'] ?? $decoded['error'] ?? ''));
            if ($message !== '') {
                return $message;
            }

            $errors = $decoded['errors'] ?? null;
            if (is_array($errors) && $errors !== []) {
                $parts = [];
                foreach ($errors as $error) {
                    if (! is_array($error)) {
                        continue;
                    }
                    $code = trim((string) ($error['code'] ?? ''));
                    $detail = trim((string) ($error['detail'] ?? $error['message'] ?? ''));
                    $parts[] = trim(($code !== '' ? $code.': ' : '').$detail);
                }

                $combined = implode(' | ', array_filter($parts));
                if ($combined !== '') {
                    return $combined;
                }
            }
        }

        $body = trim($response->body());
        if ($body !== '') {
            return $body;
        }

        return 'SMSFlow send SMS failed (HTTP '.$response->status().')';
    }

    /**
     * @return iterable<mixed>
     */
    private function walkDecoded(mixed $value): iterable
    {
        if (! is_array($value)) {
            return [];
        }

        yield $value;

        foreach ($value as $child) {
            if (is_array($child)) {
                foreach ($this->walkDecoded($child) as $nested) {
                    yield $nested;
                }
            }
        }
    }
}
