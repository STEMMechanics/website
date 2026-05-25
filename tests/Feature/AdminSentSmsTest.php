<?php

namespace Tests\Feature;

use App\Models\InboundSms;
use App\Models\SentSms;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminSentSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_sent_sms_page_shows_balance_and_message_history(): void
    {
        config([
            'services.smsflow.api_key' => 'secret-key',
            'services.smsflow.base_url' => 'https://api.smsflow.com.au/v2',
        ]);

        Http::fake([
            'https://api.smsflow.com.au/v2/account/balance' => Http::response([
                'balance' => 12.34,
                'currency' => 'AUD',
            ], 200),
        ]);

        $admin = $this->createAdminUser();

        SentSms::query()->create([
            'recipient' => '+61400111222',
            'recipient_name' => 'SMS Recipient',
            'message' => 'Reminder message',
            'status' => SentSms::STATUS_SENT,
            'from_number' => '+61444123456',
            'origin' => 'admin.server.sent-sms',
            'reference' => 'test-ref-123',
            'provider_message_id' => 'message-123',
            'response_status' => 200,
            'response_payload' => ['data' => ['message_id' => 'message-123']],
            'context' => ['source' => 'admin.server.sent-sms'],
            'initiated_by_user_id' => $admin->id,
            'initiated_by_name' => $admin->getName(),
            'sent_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.server.sent-sms'));

        $response->assertOk();
        $response->assertSee('Remaining messages');
        $response->assertSee('12');
        $response->assertDontSee('12.34');
        $response->assertSee('SMS Recipient');
        $response->assertSee('test-ref-123');
        $response->assertSee('Reminder message');
        $response->assertSee('200 - OK');
        $response->assertSee('Send SMS');
        $response->assertDontSee('SMSFlow Account');
        $response->assertDontSee('Live account balance');
        $response->assertSee('data-sent-sms-mobile-thread-list', false);
        $response->assertSee('data-sent-sms-desktop-table', false);
    }

    public function test_admin_sent_sms_page_looks_up_user_names_including_anonymized_users(): void
    {
        config([
            'services.smsflow.api_key' => 'secret-key',
            'services.smsflow.base_url' => 'https://api.smsflow.com.au/v2',
        ]);

        Http::fake([
            'https://api.smsflow.com.au/v2/account/balance' => Http::response([
                'balance' => 12.34,
                'currency' => 'AUD',
            ], 200),
        ]);

        $admin = $this->createAdminUser();

        $lookupUser = User::factory()->create([
            'firstname' => 'Lookup',
            'surname' => 'User',
            'email' => 'lookup@example.com',
            'phone' => '+61400111222',
        ]);

        User::factory()->create([
            'firstname' => 'Ghost',
            'surname' => 'User',
            'email' => 'ghost@example.com',
            'phone' => '+61400333444',
            'anonymized_at' => now(),
        ]);

        SentSms::query()->create([
            'recipient' => '0400 111 222',
            'message' => 'Active lookup message',
            'status' => SentSms::STATUS_SENT,
            'origin' => 'admin.server.sent-sms',
            'response_status' => 202,
            'sent_at' => now(),
        ]);

        SentSms::query()->create([
            'recipient' => '0400 333 444',
            'message' => 'Ghost lookup message',
            'status' => SentSms::STATUS_FAILED,
            'origin' => 'admin.server.sent-sms',
            'response_status' => 500,
            'error_message' => 'Gateway error',
            'failed_at' => now(),
        ]);

        $response = $this->actingAs($admin)->get(route('admin.server.sent-sms'));

        $response->assertOk();
        $response->assertSee('Lookup User');
        $response->assertSee(route('admin.user.edit', $lookupUser), false);
        $response->assertSee('deleted');
        $response->assertSee('202 - Accepted');
        $response->assertSee('500 - Internal Server Error');
    }

    public function test_admin_sent_sms_page_shows_recent_inbound_replies(): void
    {
        config([
            'services.smsflow.api_key' => 'secret-key',
            'services.smsflow.base_url' => 'https://api.smsflow.com.au/v2',
        ]);

        Http::fake([
            'https://api.smsflow.com.au/v2/account/balance' => Http::response([
                'balance' => 12.34,
                'currency' => 'AUD',
            ], 200),
        ]);

        $admin = $this->createAdminUser();

        $sentSms = SentSms::query()->create([
            'recipient' => '0400 111 222',
            'recipient_name' => 'Reply Recipient',
            'message' => 'Test outbound message',
            'status' => SentSms::STATUS_SENT,
            'provider_message_id' => '288496612d1a495698255d31ad28746f',
            'sent_at' => now(),
        ]);

        InboundSms::query()->create([
            'provider' => 'smsflow',
            'topic' => 'sms.incoming',
            'incoming_id' => '2e8834a8f3794b4fb159abbf27765612',
            'original_message_id' => '288496612d1a495698255d31ad28746f',
            'sent_sms_id' => $sentSms->id,
            'originator' => '+61400130190',
            'destination' => '+61485968632',
            'message' => 'Hello?',
            'received_at' => now(),
            'opted_out' => false,
            'payload' => [
                'topic' => 'sms.incoming',
                'originator' => '+61400130190',
                'message' => 'Hello?',
            ],
        ]);

        $response = $this->actingAs($admin)->get(route('admin.server.sent-sms'));

        $response->assertOk();
        $response->assertSee('Acknowledge');
        $response->assertSee('↳ Reply', false);
        $response->assertSee('Hello?');
        $response->assertSee('Reply Recipient');
        $response->assertSee('highlight-row');
    }

    public function test_admin_can_acknowledge_an_inbound_reply(): void
    {
        $admin = $this->createAdminUser();

        $reply = InboundSms::query()->create([
            'provider' => 'smsflow',
            'topic' => 'sms.incoming',
            'incoming_id' => 'incoming-123',
            'originator' => '+61400130190',
            'destination' => '+61485968632',
            'message' => 'Please call me',
            'received_at' => now(),
            'opted_out' => false,
            'payload' => [
                'topic' => 'sms.incoming',
                'originator' => '+61400130190',
                'message' => 'Please call me',
            ],
        ]);

        $response = $this->actingAs($admin)
            ->patch(route('admin.server.sent-sms.replies.acknowledge', $reply));

        $response->assertRedirect();
        $response->assertSessionHas('message-title', 'Reply updated');

        $this->assertDatabaseHas('inbound_sms', [
            'incoming_id' => 'incoming-123',
            'acknowledged_by_user_id' => $admin->id,
        ]);

        $this->assertNotNull($reply->fresh()->acknowledged_at);
    }

    public function test_admin_can_acknowledge_an_inbound_reply_via_ajax(): void
    {
        $admin = $this->createAdminUser();

        $reply = InboundSms::query()->create([
            'provider' => 'smsflow',
            'topic' => 'sms.incoming',
            'incoming_id' => 'incoming-ajax-123',
            'originator' => '+61400130190',
            'destination' => '+61485968632',
            'message' => 'Need help',
            'received_at' => now(),
            'opted_out' => false,
            'payload' => [
                'topic' => 'sms.incoming',
                'originator' => '+61400130190',
                'message' => 'Need help',
            ],
        ]);

        $response = $this->actingAs($admin)
            ->patchJson(route('admin.server.sent-sms.replies.acknowledge', $reply));

        $response->assertOk()
            ->assertJson([
                'ok' => true,
                'inbound_sms' => [
                    'id' => $reply->id,
                ],
            ]);

        $this->assertDatabaseHas('inbound_sms', [
            'incoming_id' => 'incoming-ajax-123',
            'acknowledged_by_user_id' => $admin->id,
        ]);

        $this->assertNotNull($reply->fresh()->acknowledged_at);
    }

    public function test_admin_can_send_custom_sms_and_it_is_logged(): void
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
                        'message_id' => 'message-123',
                    ],
                ],
            ], 200),
        ]);

        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->post(route('admin.server.sent-sms.store'), [
            'recipient' => '0400 123 456',
            'message' => 'Custom admin SMS message.',
            'reference' => 'admin-manual-1',
        ]);

        $response->assertRedirect();
        $response->assertSessionHas('message-title', 'SMS queued');

        Http::assertSent(function ($request): bool {
            $decoded = json_decode($request->body(), true);

            return $request->url() === 'https://api.smsflow.com.au/v2/sms/send'
                && $request->method() === 'POST'
                && $request->header('Authorization')[0] === 'Bearer secret-key'
                && is_array($decoded)
                && ($decoded['to'] ?? null) === '+61400123456'
                && ($decoded['body'] ?? null) === 'Custom admin SMS message.'
                && ($decoded['from'] ?? null) === '+61444123456'
                && ($decoded['reference'] ?? null) === 'admin-manual-1';
        });

        $this->assertDatabaseHas('sent_sms', [
            'recipient' => '0400 123 456',
            'message' => 'Custom admin SMS message.',
            'status' => SentSms::STATUS_SENT,
            'reference' => 'admin-manual-1',
            'provider_message_id' => 'message-123',
            'origin' => 'admin.server.sent-sms',
        ]);
    }

    public function test_admin_can_send_custom_sms_to_multiple_numbers_separated_by_semicolon(): void
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
                        'message_id' => 'message-123',
                    ],
                ],
            ], 200),
        ]);

        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->post(route('admin.server.sent-sms.store'), [
                'recipient' => '0400 123 456 ; 0400 987 654',
                'message' => 'Custom admin SMS message.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('message-title', 'SMS queued');

        Http::assertSentCount(2);
        Http::assertSent(function ($request): bool {
            $decoded = json_decode($request->body(), true);

            return $request->url() === 'https://api.smsflow.com.au/v2/sms/send'
                && $request->method() === 'POST'
                && is_array($decoded)
                && ($decoded['to'] ?? null) === '+61400123456';
        });
        Http::assertSent(function ($request): bool {
            $decoded = json_decode($request->body(), true);

            return $request->url() === 'https://api.smsflow.com.au/v2/sms/send'
                && $request->method() === 'POST'
                && is_array($decoded)
                && ($decoded['to'] ?? null) === '+61400987654';
        });

        $this->assertDatabaseHas('sent_sms', [
            'recipient' => '0400 123 456',
            'message' => 'Custom admin SMS message.',
            'status' => SentSms::STATUS_SENT,
            'origin' => 'admin.server.sent-sms',
        ]);

        $this->assertDatabaseHas('sent_sms', [
            'recipient' => '0400 987 654',
            'message' => 'Custom admin SMS message.',
            'status' => SentSms::STATUS_SENT,
            'origin' => 'admin.server.sent-sms',
        ]);
    }

    public function test_admin_cannot_send_custom_sms_with_invalid_recipient_numbers(): void
    {
        config([
            'services.smsflow.api_key' => 'secret-key',
            'services.smsflow.base_url' => 'https://api.smsflow.com.au/v2',
            'services.smsflow.from' => '+61444123456',
        ]);

        Http::fake();

        $admin = $this->createAdminUser();

        $response = $this->actingAs($admin)
            ->post(route('admin.server.sent-sms.store'), [
                'recipient' => '0400 123 456; not-a-number',
                'message' => 'Custom admin SMS message.',
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('message-title', 'SMS not sent');
        $response->assertSessionHas('message', 'Invalid recipient number: not-a-number.');

        Http::assertNothingSent();
        $this->assertDatabaseCount('sent_sms', 0);
    }

    private function createAdminUser(): User
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }
}
