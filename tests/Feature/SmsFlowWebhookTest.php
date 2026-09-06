<?php

namespace Tests\Feature;

use App\Models\InboundSms;
use App\Models\SentSms;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class SmsFlowWebhookTest extends TestCase
{
    use RefreshDatabase;

    public function test_smsflow_webhook_logs_payload_and_returns_ok(): void
    {
        config(['services.smsflow.webhook_secret' => str_repeat('a', 40)]);
        $this->withHeader('Authorization', 'Bearer '.str_repeat('a', 40));
        $outbound = SentSms::query()->create([
            'recipient' => '+61400130190',
            'message' => 'Test message',
            'status' => SentSms::STATUS_SENT,
            'provider_message_id' => '288496612d1a495698255d31ad28746f',
            'sent_at' => now(),
        ]);

        Log::shouldReceive('info')
            ->once()
            ->withArgs(function (string $message, array $context): bool {
                return $message === 'SMSFlow webhook received.'
                    && ($context['method'] ?? null) === 'POST'
                    && ($context['path'] ?? null) === 'webhooks/smsflow'
                    && ($context['topic'] ?? null) === 'sms.incoming'
                    && ! isset($context['headers'], $context['payload_text'], $context['payload_json']);
            });

        $this->postJson(route('webhook.smsflow'), [
            'topic' => 'sms.incoming',
            'incoming_id' => '2e8834a8f3794b4fb159abbf27765612',
            'original_message_id' => '288496612d1a495698255d31ad28746f',
            'originator' => '+61400130190',
            'destination' => '+61485968632',
            'message' => 'Hello?',
            'received_time' => '2026-05-24 22:52:07',
            'is_opted_out' => false,
        ])->assertOk()
            ->assertJson(['ok' => true]);

        $this->assertDatabaseHas('inbound_sms', [
            'incoming_id' => '2e8834a8f3794b4fb159abbf27765612',
            'original_message_id' => '288496612d1a495698255d31ad28746f',
            'sent_sms_id' => $outbound->id,
            'originator' => '+61400130190',
            'destination' => '+61485968632',
            'message' => 'Hello?',
            'opted_out' => false,
        ]);

        $this->assertSame(1, InboundSms::query()->count());
    }
}
