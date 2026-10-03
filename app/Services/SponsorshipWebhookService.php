<?php

namespace App\Services;

class SponsorshipWebhookService
{
    public function __construct(private readonly SponsorshipService $sponsorships) {}

    /** @param array<string, mixed> $payload */
    public function handle(array $payload): void
    {
        $type = trim((string) ($payload['type'] ?? ''));
        if (in_array($type, ['payment.created', 'payment.updated'], true)) {
            $payment = data_get($payload, 'data.object.payment');
            if (is_array($payment)) $this->sponsorships->syncPaymentEvent($payment);
            return;
        }

    }
}
