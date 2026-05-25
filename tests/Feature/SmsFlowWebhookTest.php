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
                    && ($context['payload_text'] ?? null) === '{"topic":"sms.incoming","incoming_id":"2e8834a8f3794b4fb159abbf27765612","original_message_id":"288496612d1a495698255d31ad28746f","originator":"+61400130190","destination":"+61485968632","message":"Hello?","received_time":"2026-05-24 22:52:07","is_opted_out":false}'
                    && ($context['payload_json']['topic'] ?? null) === 'sms.incoming'
                    && ($context['payload_json']['originator'] ?? null) === '+61400130190'
                    && ($context['payload_json']['message'] ?? null) === 'Hello?';
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
