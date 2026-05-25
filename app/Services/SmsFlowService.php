<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SmsFlowService
{
    public function isConfigured(): bool
    {
        return trim((string) config('services.smsflow.api_key', '')) !== '';
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function send(array $payload): array
    {
        $response = $this->sendRaw($payload);

        return $this->parseResponse($response, 'SMSFlow send SMS failed');
    }

    public function sendRaw(array $payload): Response
    {
        return $this->request()->post('/sms/send', $this->normalizePayload($payload));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function sendText(string $to, string $body, array $payload = []): array
    {
        return $this->send(array_merge($payload, [
            'to' => $to,
            'body' => $body,
        ]));
    }

    public function normalizePhoneNumber(string $phone): string
    {
        $trimmed = trim($phone);
        if ($trimmed === '') {
            throw new RuntimeException('Recipient phone number is required.');
        }

        $normalized = preg_replace('/[^\d+]/', '', $trimmed) ?? $trimmed;

        if (str_starts_with($normalized, '+')) {
            if (! preg_match('/^\+\d{8,15}$/', $normalized)) {
                throw new RuntimeException('Recipient phone number must be in international format.');
            }

            return $normalized;
        }

        if (preg_match('/^04\d{8}$/', $normalized) === 1) {
            return '+61'.substr($normalized, 1);
        }

        if (preg_match('/^61\d{9}$/', $normalized) === 1) {
            return '+'.$normalized;
        }

        if (preg_match('/^4\d{8}$/', $normalized) === 1) {
            return '+61'.$normalized;
        }

        throw new RuntimeException('Recipient phone number must be an Australian mobile or international number.');
    }

    /**
     * @return array<string, mixed>
     */
    public function getAccountBalance(): array
    {
        $response = $this->getAccountBalanceRaw();

        return $this->parseResponse($response, 'SMSFlow account balance request failed');
    }

    public function getAccountBalanceRaw(): Response
    {
        return $this->request()->get('/account/balance');
    }

    private function request()
    {
        $apiKey = trim((string) config('services.smsflow.api_key', ''));
        if ($apiKey === '') {
            throw new RuntimeException('SMSFlow API key is not configured.');
        }

        return Http::baseUrl((string) config('services.smsflow.base_url', 'https://api.smsflow.com.au/v2'))
            ->acceptJson()
            ->contentType('application/json')
            ->withToken($apiKey);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalizePayload(array $payload): array
    {
        $message = trim((string) ($payload['body'] ?? ''));
        if ($message === '') {
            throw new RuntimeException('SMS message body is required.');
        }

        $to = trim((string) ($payload['to'] ?? ''));
        $normalizedTo = $this->normalizePhoneNumber($to);

        $normalized = [
            'to' => $normalizedTo,
            'body' => $message,
        ];

        $from = trim((string) ($payload['from'] ?? config('services.smsflow.from', '')));
        if ($from !== '') {
            $normalized['from'] = $from;
        }

        $callbackUrl = trim((string) ($payload['callback_url'] ?? config('services.smsflow.callback_url', '')));
        if ($callbackUrl !== '') {
            $normalized['callback_url'] = $callbackUrl;
        }

        $reference = trim((string) ($payload['reference'] ?? ''));
        if ($reference !== '') {
            $normalized['reference'] = $reference;
        }

        foreach (['send_at', 'send_at_timezone', 'delay', 'contact_id'] as $key) {
            if (array_key_exists($key, $payload) && $payload[$key] !== null && $payload[$key] !== '') {
                $normalized[$key] = $payload[$key];
            }
        }

        return $normalized;
    }

    /**
     * @param  Response  $response
     * @return array<string, mixed>
     */
    private function parseResponse(Response $response, string $fallbackMessage): array
    {
        if ($response->successful()) {
            $decoded = $response->json();

            if (is_array($decoded)) {
                return $decoded;
            }

            throw new RuntimeException('SMSFlow response was not valid JSON.');
        }

        $body = trim($response->body());
        $decoded = $response->json();

        if (is_array($decoded)) {
            $message = trim((string) ($decoded['message'] ?? $decoded['error'] ?? ''));
            if ($message !== '') {
                throw new RuntimeException($message);
            }

            $errors = $decoded['errors'] ?? null;
            if (is_array($errors) && $errors !== []) {
                $parts = [];
                foreach ($errors as $error) {
                    if (! is_array($error)) {
                        continue;
                    }
                    $detail = trim((string) ($error['detail'] ?? $error['message'] ?? ''));
                    $code = trim((string) ($error['code'] ?? ''));
                    $parts[] = trim(($code !== '' ? $code.': ' : '').$detail);
                }

                $combined = implode(' | ', array_filter($parts));
                if ($combined !== '') {
                    throw new RuntimeException($combined);
                }
            }
        }

        if ($body !== '') {
            throw new RuntimeException($fallbackMessage.': '.$body);
        }

        throw new RuntimeException($fallbackMessage.' (HTTP '.$response->status().')');
    }
}
