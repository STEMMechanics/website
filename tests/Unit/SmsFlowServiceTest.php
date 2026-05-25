<?php

namespace Tests\Unit;

use App\Services\SmsFlowService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SmsFlowServiceTest extends TestCase
{
    public function test_it_normalizes_australian_mobile_numbers_for_production_sends(): void
    {
        config([
            'services.smsflow.api_key' => 'secret-key',
            'services.smsflow.base_url' => 'https://api.smsflow.com.au/v2',
            'services.smsflow.from' => '+61444123456',
        ]);

        Http::fake([
            'https://api.smsflow.com.au/v2/sms/send' => Http::response([
                'data' => [
                    [
                        'status' => 'queued',
                        'message_id' => 'message-abc',
                    ],
                ],
            ], 200),
        ]);

        $response = app(SmsFlowService::class)->sendText(
            '0400 123 456',
            'Production SMS send',
        );

        $this->assertSame('queued', $response['data'][0]['status'] ?? null);

        Http::assertSent(function ($request): bool {
            $decoded = json_decode($request->body(), true);

            return $request->url() === 'https://api.smsflow.com.au/v2/sms/send'
                && $request->method() === 'POST'
                && is_array($decoded)
                && ($decoded['to'] ?? null) === '+61400123456'
                && ($decoded['body'] ?? null) === 'Production SMS send'
                && ($decoded['from'] ?? null) === '+61444123456';
        });
    }

    public function test_it_preserves_an_alphanumeric_sender_id(): void
    {
        config([
            'services.smsflow.api_key' => 'secret-key',
            'services.smsflow.base_url' => 'https://api.smsflow.com.au/v2',
            'services.smsflow.from' => 'STEMMech',
        ]);

        Http::fake([
            'https://api.smsflow.com.au/v2/sms/send' => Http::response([
                'data' => [
                    [
                        'status' => 'queued',
                        'message_id' => 'message-def',
                    ],
                ],
            ], 200),
        ]);

        $response = app(SmsFlowService::class)->sendText(
            '0400 123 456',
            'Sender ID SMS send',
        );

        $this->assertSame('queued', $response['data'][0]['status'] ?? null);

        Http::assertSent(function ($request): bool {
            $decoded = json_decode($request->body(), true);

            return $request->url() === 'https://api.smsflow.com.au/v2/sms/send'
                && $request->method() === 'POST'
                && is_array($decoded)
                && ($decoded['to'] ?? null) === '+61400123456'
                && ($decoded['body'] ?? null) === 'Sender ID SMS send'
                && ($decoded['from'] ?? null) === 'STEMMech';
        });
    }

    public function test_it_uses_the_production_balance_endpoint(): void
    {
        config([
            'services.smsflow.api_key' => 'secret-key',
            'services.smsflow.base_url' => 'https://api.smsflow.com.au/v2',
        ]);

        Http::fake([
            'https://api.smsflow.com.au/v2/account/balance' => Http::response([
                'balance' => 3.21,
                'currency' => 'AUD',
            ], 200),
        ]);

        $balance = app(SmsFlowService::class)->getAccountBalance();

        $this->assertSame(3.21, (float) ($balance['balance'] ?? 0));
        $this->assertSame('AUD', $balance['currency'] ?? null);
    }
}
