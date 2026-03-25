<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\TicketOrderConfirmation;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class AdminWorkshopManualTicketTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_can_create_free_ticket_from_workshop_ticket_screen(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $user = User::factory()->create([
            'email' => 'holder@example.com',
        ]);
        $workshop = $this->createTicketWorkshop([
            'price' => '$45.00',
            'ticket_group_slug' => 'ticket-holders',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.workshop.tickets.store', $workshop), [
                'manual_ticket_type' => 'free',
                'firstname' => 'Free',
                'surname' => 'Holder',
                'email' => 'holder@example.com',
                'phone' => '0400 111 222',
            ]);

        $response->assertRedirect(route('admin.workshop.tickets', $workshop));
        $response->assertSessionHas('message-title', 'Ticket created');

        $ticket = Ticket::query()->where('workshop_id', $workshop->id)->firstOrFail();

        $this->assertSame(Ticket::STATUS_PAID, (int) $ticket->status);
        $this->assertSame((string) $user->id, (string) $ticket->user_id);
        $this->assertNull($ticket->invoice_id);
        $this->assertNotSame('', trim((string) $ticket->reference_code));
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'holder@example.com'
                && $job->mailable instanceof TicketOrderConfirmation
                && $job->mailable->paymentMethodLabel === 'Free'
                && $job->mailable->ticketAttachmentCount === 1
                && $job->mailable->hasInvoiceAttachment === false;
        });
        $this->assertDatabaseHas('user_groups', [
            'user_id' => $user->id,
            'slug' => 'ticket-holders',
        ]);
    }

    public function test_admin_can_create_free_ticket_without_linked_user_account(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $workshop = $this->createTicketWorkshop([
            'price' => '$45.00',
            'ticket_group_slug' => 'ticket-holders',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.workshop.tickets.store', $workshop), [
                'manual_ticket_type' => 'free',
                'firstname' => 'Guest',
                'surname' => 'Holder',
                'email' => 'guest@example.com',
                'phone' => '0400 777 888',
            ]);

        $response->assertRedirect(route('admin.workshop.tickets', $workshop));
        $response->assertSessionHas('message-title', 'Ticket created');

        $ticket = Ticket::query()->where('workshop_id', $workshop->id)->firstOrFail();

        $this->assertSame(Ticket::STATUS_PAID, (int) $ticket->status);
        $this->assertNull($ticket->user_id);
        $this->assertNull($ticket->invoice_id);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'guest@example.com'
                && $job->mailable instanceof TicketOrderConfirmation
                && $job->mailable->paymentMethodLabel === 'Free';
        });
        $this->assertDatabaseMissing('user_groups', [
            'slug' => 'ticket-holders',
        ]);
    }

    public function test_admin_workshop_ticket_screen_shows_create_ticket_modal_trigger_and_email_option(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createTicketWorkshop();

        $response = $this->actingAs($admin)->get(route('admin.workshop.tickets', $workshop));

        $response->assertOk();
        $response->assertDontSee('@js(', false);
        $response->assertSee('x-on:click.prevent="createTicketOpen = true"', false);
        $response->assertSee('Email ticket to this email address');
        $response->assertSee('Email customer about this cancellation');
    }

    public function test_admin_can_create_reserved_ticket_with_invoice_from_workshop_ticket_screen(): void
    {
        $admin = $this->createAdminUser();
        $user = User::factory()->create([
            'email' => 'reserve@example.com',
        ]);
        $workshop = $this->createTicketWorkshop([
            'price' => '$30.00',
        ]);

        $response = $this->actingAs($admin)
            ->post(route('admin.workshop.tickets.store', $workshop), [
                'manual_ticket_type' => 'reserve',
                'firstname' => 'Reserve',
                'surname' => 'Holder',
                'email' => 'reserve@example.com',
                'phone' => '0400 222 333',
                'email_ticket' => '0',
            ]);

        $response->assertRedirect(route('admin.workshop.tickets', $workshop));

        $ticket = Ticket::query()
            ->with(['invoice.lines'])
            ->where('workshop_id', $workshop->id)
            ->firstOrFail();

        $this->assertSame(Ticket::STATUS_PENDING_DOOR, (int) $ticket->status);
        $this->assertSame((string) $user->id, (string) $ticket->user_id);
        $this->assertNotNull($ticket->invoice_id);
        $this->assertNotNull($ticket->invoice_line_id);
        $this->assertSame('issued', (string) $ticket->invoice?->status);
        $this->assertSame((string) $ticket->invoice_line_id, (string) $ticket->invoice?->lines->first()?->id);
    }

    public function test_admin_cannot_create_manual_ticket_when_workshop_is_full(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $workshop = $this->createTicketWorkshop([
            'max_tickets' => 1,
            'price' => '$20.00',
        ]);

        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.workshop.tickets', $workshop))
            ->post(route('admin.workshop.tickets.store', $workshop), [
                'manual_ticket_type' => 'free',
                'firstname' => 'Late',
                'surname' => 'Registrant',
                'email' => 'late@example.com',
                'phone' => '0400 333 444',
                'email_ticket' => '0',
            ]);

        $response->assertRedirect(route('admin.workshop.tickets', $workshop));
        $response->assertSessionHasErrors('manual_ticket_type');
        $this->assertSame(1, Ticket::query()->where('workshop_id', $workshop->id)->count());
        Queue::assertNotPushed(SendEmail::class);
    }

    public function test_admin_can_create_manual_ticket_without_email_when_checkbox_is_disabled(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $user = User::factory()->create([
            'email' => 'no-email@example.com',
        ]);
        $workshop = $this->createTicketWorkshop();

        $response = $this->actingAs($admin)
            ->post(route('admin.workshop.tickets.store', $workshop), [
                'manual_ticket_type' => 'free',
                'firstname' => 'No',
                'surname' => 'Email',
                'email' => 'no-email@example.com',
                'phone' => '0400 444 555',
                'email_ticket' => '0',
            ]);

        $response->assertRedirect(route('admin.workshop.tickets', $workshop));
        $this->assertDatabaseHas('tickets', [
            'workshop_id' => $workshop->id,
            'user_id' => $user->id,
            'email' => 'no-email@example.com',
        ]);
        Queue::assertNotPushed(SendEmail::class);
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
        $heroName = 'hero-'.Str::lower(Str::random(8)).'.png';

        \App\Models\Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('c', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);

        return Workshop::query()->create(array_merge([
            'title' => 'Manual Ticket Workshop',
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
