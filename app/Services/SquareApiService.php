<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class SquareApiService
{
    public function userFacingPaymentErrorMessage(string $rawMessage): string
    {
        $message = trim($rawMessage);
        if ($message === '') {
            return 'We could not process your card payment right now. Please try again or use another payment method.';
        }

        preg_match('/([A-Z_]{3,})\s*:/', $message, $matches);
        $code = strtoupper(trim((string) ($matches[1] ?? '')));

        return match ($code) {
            'GENERIC_DECLINE', 'CARD_DECLINED' => 'Your card has been declined. Please try another card or contact your bank.',
            'INSUFFICIENT_FUNDS' => 'Your card has insufficient funds. Please try another payment method.',
            'CVV_FAILURE' => 'Your card security code (CVV) did not match. Please check and try again.',
            'ADDRESS_VERIFICATION_FAILURE' => 'Your billing address did not match your card details. Please check and try again.',
            'EXPIRATION_FAILURE' => 'Your card expiry details appear incorrect. Please check and try again.',
            'INVALID_ACCOUNT', 'INVALID_CARD' => 'Your card details appear invalid. Please check and try again.',
            'CARD_NOT_SUPPORTED' => 'This card is not supported for this payment. Please try a different card.',
            'TRANSACTION_LIMIT' => 'This payment exceeds your card limit. Please try a smaller amount or another card.',
            default => 'We could not process your card payment right now. Please try again or use another payment method.',
        };
    }

    public function isEnabled(): bool
    {
        return (bool) config('services.square.enabled');
    }

    public function createPayment(array $payload): array
    {
        $response = $this->request()->post('/payments', $payload);

        return $this->parseResponse($response, 'Square create payment failed');
    }

    public function createRefund(array $payload): array
    {
        $response = $this->request()->post('/refunds', $payload);

        return $this->parseResponse($response, 'Square create refund failed');
    }

    public function validateWebhookSignature(string $payload, string $signatureHeader, string $requestUrl): bool
    {
        $signatureKey = (string) config('services.square.webhook_signature_key');
        if ($signatureKey === '' || $signatureHeader === '' || $requestUrl === '') {
            return false;
        }

        $computed = base64_encode(
            hash_hmac('sha256', $requestUrl.$payload, $signatureKey, true)
        );

        return hash_equals($computed, $signatureHeader);
    }

    public function baseUrl(): string
    {
        return config('services.square.environment') === 'production'
            ? 'https://connect.squareup.com/v2'
            : 'https://connect.squareupsandbox.com/v2';
    }

    private function request()
    {
        $token = (string) config('services.square.access_token');
        if ($token === '') {
            throw new RuntimeException('Square access token is not configured.');
        }

        return Http::baseUrl($this->baseUrl())
            ->acceptJson()
            ->contentType('application/json')
            ->withToken($token)
            ->withHeaders([
                'Square-Version' => (string) config('services.square.api_version'),
            ]);
    }

    private function parseResponse(Response $response, string $fallbackMessage): array
    {
        if ($response->successful()) {
            return $response->json() ?? [];
        }

        $body = $response->json();
        $errors = is_array($body['errors'] ?? null) ? $body['errors'] : [];

        if (! empty($errors)) {
            $messages = [];
            foreach ($errors as $error) {
                if (! is_array($error)) {
                    continue;
                }
                $detail = trim((string) ($error['detail'] ?? ''));
                $code = trim((string) ($error['code'] ?? ''));
                $messages[] = trim(($code !== '' ? $code.': ' : '').$detail);
            }
            $message = implode(' | ', array_filter($messages));
            if ($message !== '') {
                throw new RuntimeException($message);
            }
        }

        throw new RuntimeException($fallbackMessage.' (HTTP '.$response->status().')');
    }
}
