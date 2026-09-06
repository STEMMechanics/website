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

        $context = $this->buildContext($request);
        $context['errorId'] = (string) \Illuminate\Support\Str::uuid();
        Log::error('Site error reference', ['error_id' => $context['errorId'], 'exception_class' => $exception::class, 'route' => $context['requestRoute'] ?? 'console']);
        try {
            Mail::to($recipients)->send(new SiteErrorAlert($exception::class, $context));
        } catch (Throwable $mailException) {
            Log::warning('Failed to send site error alert', [
                'exception' => get_class($mailException),
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
            'requestRoute' => (string) ($request->route()?->getName() ?? 'unmatched'),
        ];
    }

    /**
     * @return array<int, string>
     */
    private function adminRecipients(): array
    {
        $configured = preg_split('/[;,]+/', (string) config('security.error_recipients', '')) ?: [];

        return collect($configured)
            ->map(fn ($email) => strtolower(trim((string) $email)))
            ->filter(fn ($email) => $email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL))
            ->unique()
            ->values()
            ->all();
    }
}
