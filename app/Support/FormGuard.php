<?php

namespace App\Support;

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;
use JsonException;

class FormGuard
{
    public const TOKEN_FIELD = '_form_guard';

    public const ERROR_KEY = 'form_guard';

    public function issueToken(string $form): string
    {
        $payload = [
            'form' => $form,
            'issued_at' => now()->timestamp,
        ];

        return Crypt::encryptString(json_encode($payload, JSON_THROW_ON_ERROR));
    }

    public function honeypotField(string $form): string
    {
        return 'details_'.substr(hash('sha256', 'form-guard|'.$form.'|'.(string) config('app.key')), 0, 12);
    }

    public function ensureValid(Request $request, string $form): void
    {
        $honeypotField = $this->honeypotField($form);
        $honeypotValue = trim((string) $request->input($honeypotField, ''));

        if ($honeypotValue !== '') {
            $this->fail(
                $request,
                $form,
                'honeypot_filled',
                'We could not verify your submission. Please try again.'
            );
        }

        $token = trim((string) $request->input(self::TOKEN_FIELD, ''));
        if ($token === '') {
            $this->fail(
                $request,
                $form,
                'token_missing',
                'We could not verify your submission. Please reload the page and try again.'
            );
        }

        try {
            /** @var array<string, mixed> $payload */
            $payload = json_decode(Crypt::decryptString($token), true, 512, JSON_THROW_ON_ERROR);
        } catch (DecryptException|JsonException) {
            $this->fail(
                $request,
                $form,
                'token_invalid',
                'We could not verify your submission. Please reload the page and try again.'
            );
        }

        if (($payload['form'] ?? null) !== $form) {
            $this->fail(
                $request,
                $form,
                'form_mismatch',
                'We could not verify your submission. Please reload the page and try again.'
            );
        }

        $issuedAt = (int) ($payload['issued_at'] ?? 0);
        $minimumSeconds = max(0, (int) config('security.form_protection.minimum_seconds', 2));

        if ($issuedAt <= 0) {
            $this->fail(
                $request,
                $form,
                'issued_at_missing',
                'We could not verify your submission. Please reload the page and try again.'
            );
        }

        if ($minimumSeconds > 0 && (now()->timestamp - $issuedAt) < $minimumSeconds) {
            $this->fail(
                $request,
                $form,
                'submitted_too_quickly',
                'Please wait a moment before sending the form.'
            );
        }
    }

    private function fail(Request $request, string $form, string $reason, string $message): never
    {
        Log::channel('honeypot')->info('Form guard blocked submission.', [
            'form' => $form,
            'reason' => $reason,
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'url' => $request->fullUrl(),
        ]);

        throw ValidationException::withMessages([
            self::ERROR_KEY => $message,
        ]);
    }
}
