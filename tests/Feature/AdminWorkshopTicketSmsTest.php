<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\SentSms;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AdminWorkshopTicketSmsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_workshop_ticket_screen_shows_sms_controls_and_recipient_list(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createTicketWorkshop();

        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_CANCELLED,
            'firstname' => 'SMS',
            'surname' => 'Recipient',
            'email' => 'sms@example.com',
            'phone' => '0400 111 222',
        ]);

        $missingPhoneUser = User::factory()->create([
            'phone' => null,
        ]);

        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'firstname' => 'No',
            'surname' => 'Mobile',
            'email' => 'nomobile@example.com',
            'phone' => null,
            'user_id' => $missingPhoneUser->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.workshop.tickets', $workshop));

        $response->assertOk();
        $response->assertSee('Text Ticket Contacts');
        $response->assertDontSee('No ticket contacts with mobile numbers are available');
        $response->assertSee('sms_recipient_ids[]', false);
        $response->assertSee('0400 111 222');
        $response->assertSee('No mobile number on file');
    }

    public function test_admin_workshop_ticket_sms_recipient_list_deduplicates_phone_numbers(): void
    {
        $this->createAdminUser();
        $workshop = $this->createTicketWorkshop();

        $primaryTicket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_CANCELLED,
            'firstname' => 'Primary',
            'surname' => 'Recipient',
            'email' => 'primary@example.com',
            'phone' => '0400 111 222',
        ]);

        $duplicateTicket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'firstname' => 'Duplicate',
            'surname' => 'Recipient',
            'email' => 'duplicate@example.com',
            'phone' => '+61 400 111 222',
        ]);

        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'firstname' => 'Missing',
            'surname' => 'Mobile',
            'email' => 'nomobile@example.com',
            'phone' => null,
            'user_id' => null,
        ]);

        $controller = app(\App\Http\Controllers\WorkshopController::class);
        $method = (new \ReflectionClass($controller))->getMethod('resolveWorkshopTicketSmsRecipients');
        $method->setAccessible(true);

        $recipients = $method->invoke($controller, $workshop);
        $messageableRecipients = array_values(array_filter($recipients, fn (array $recipient): bool => (bool) ($recipient['can_message'] ?? false)));

        $this->assertCount(1, $messageableRecipients);
        $actualTicketIds = array_values($messageableRecipients[0]['ticket_ids']);
        sort($actualTicketIds);
        $expectedTicketIds = [(int) $primaryTicket->id, (int) $duplicateTicket->id];
        sort($expectedTicketIds);
        $this->assertSame($expectedTicketIds, $actualTicketIds);
        $this->assertSame('+61400111222', app(\App\Services\SmsFlowService::class)->normalizePhoneNumber((string) $messageableRecipients[0]['phone']));
        $this->assertNotEmpty($messageableRecipients[0]['formatted_phone']);
    }

    public function test_admin_can_send_sms_to_selected_ticket_holders(): void
    {
        config([
            'services.smsflow.api_key' => 'secret-key',
            'services.smsflow.base_url' => 'https://api.smsflow.com.au/v2',
            'services.smsflow.from' => '+61444123456',
        ]);

        $admin = $this->createAdminUser();
        $workshop = $this->createTicketWorkshop();
        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_REISSUED,
            'firstname' => 'SMS',
            'surname' => 'Recipient',
            'email' => 'sms@example.com',
            'phone' => '0400 123 456',
        ]);

        Http::fake([
            'https://api.smsflow.com.au/v2/sms/send' => Http::response([
                'meta' => [
                    'timezone' => 'UTC',
                ],
                'data' => [
                    [
                        'status' => 'queued',
                        'message_id' => 'message-123',
                    ],
                ],
            ], 200),
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.workshop.tickets.sms', $workshop), [
                'sms_message' => 'Reminder: please arrive at 9am.',
                'sms_recipient_ids' => [(string) $ticket->id],
            ]);

        $response->assertRedirect();
        $response->assertSessionHas('message-title', 'SMS queued');

        $this->assertDatabaseHas('sent_sms', [
            'recipient' => '0400 123 456',
            'message' => 'Reminder: please arrive at 9am.',
            'status' => SentSms::STATUS_SENT,
            'reference' => 'workshop-'.$workshop->id.'-ticket-'.$ticket->id,
            'provider_message_id' => 'message-123',
            'origin' => 'admin.workshop.tickets',
        ]);

        Http::assertSent(function ($request) use ($workshop, $ticket): bool {
            $decoded = json_decode($request->body(), true);

            return $request->url() === 'https://api.smsflow.com.au/v2/sms/send'
                && $request->method() === 'POST'
                && $request->header('Authorization')[0] === 'Bearer secret-key'
                && is_array($decoded)
                && ($decoded['to'] ?? null) === '+61400123456'
                && ($decoded['body'] ?? null) === 'Reminder: please arrive at 9am.'
                && ($decoded['from'] ?? null) === '+61444123456'
                && ($decoded['reference'] ?? null) === 'workshop-'.$workshop->id.'-ticket-'.$ticket->id;
        });
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

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function createTicketWorkshop(array $overrides = []): Workshop
    {
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = 'hero-'.strtolower(bin2hex(random_bytes(4))).'.png';

        \App\Models\Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('c', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);

        return Workshop::query()->create(array_merge([
            'title' => 'SMS Workshop',
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(4),
            'status' => 'open',
            'registration' => 'tickets',
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
            'price' => '$25.00',
            'max_tickets' => 10,
            'ticket_group_slug' => null,
        ], $overrides));
    }
}
