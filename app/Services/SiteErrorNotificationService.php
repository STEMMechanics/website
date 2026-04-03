<?php

namespace App\Services;

use App\Mail\SiteErrorAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SiteErrorNotificationService
{
    public function notify(Throwable $exception, ?Request $request = null): void
    {
        if (trim((string) config('app.env', '')) === 'local') {
            return;
        }

        $recipients = $this->adminRecipients();
        if ($recipients === []) {
            return;
        }

        try {
            Mail::to($recipients)->send(new SiteErrorAlert($exception, $this->buildContext($request)));
        } catch (Throwable $mailException) {
            Log::warning('Failed to send site error alert', [
                'exception' => get_class($mailException),
                'message' => $mailException->getMessage(),
            ]);
        }
    }

    /**
     * @return array<string, string>
     */
    private function buildContext(?Request $request): array
    {
        if ($request === null) {
            return [];
        }

        return [
            'requestMethod' => (string) $request->method(),
            'requestUrl' => (string) $request->fullUrl(),
            'requestUserAgent' => (string) $request->userAgent(),
            'requestUserId' => (string) ($request->user()?->id ?? ''),
            'requestUserEmail' => (string) ($request->user()?->email ?? ''),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function adminRecipients(): array
    {
        $configured = preg_split('/[;,]+/', (string) config('mail.admin_bcc', 'admin@stemmechanics.com.au')) ?: [];

        return collect($configured)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
