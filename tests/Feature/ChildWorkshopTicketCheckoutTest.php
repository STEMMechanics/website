<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ChildWorkshopTicketCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.altcha_enabled' => false]);
    }

    public function test_child_account_can_access_workshop_ticket_checkout_without_full_account_redirect(): void
    {
        $parent = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'firstname' => 'Kid',
            'surname' => 'Tester',
            'phone' => '0499000000',
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
        ]);
        $workshop = $this->createTicketedWorkshop();

        $response = $this->actingAs($child)
            ->get(route('workshop.ticket.flow.start', $workshop));

        $response->assertOk();
        $response->assertSee('You are logged in as a child account.');
        $response->assertSee('The details from this account will not be used for this ticket purchase.');
        $response->assertSee('Log out');
        $response->assertDontSee('value="Kid"', false);
        $response->assertDontSee('value="Tester"', false);
        $response->assertDontSee('value="0499000000"', false);
    }

    public function test_child_account_checkout_uses_entered_purchaser_details_instead_of_child_account(): void
    {
        $parent = User::factory()->create();
        $child = User::factory()->create([
            'parent_user_id' => $parent->id,
            'firstname' => 'Kid',
            'surname' => 'Checkout',
            'phone' => '0400000000',
            'email' => null,
            'email_verified_at' => null,
            'password' => 'secret1234',
        ]);
        $purchaser = User::factory()->create([
            'email' => 'buyer@example.com',
            'email_verified_at' => now(),
        ]);
        $workshop = $this->createTicketedWorkshop([
            'price' => 'Free',
        ]);

        $response = $this->actingAs($child)
            ->post(route('workshop.ticket.flow.begin', $workshop), [
                'quantity' => 1,
                'firstname' => 'Jordan',
                'surname' => 'Buyer',
                'email' => 'buyer@example.com',
                'phone' => '0411222333',
            ]);

        $response->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $ticket = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->sole();

        $this->assertSame((string) $purchaser->id, (string) $ticket->user_id);
        $this->assertNotSame((string) $child->id, (string) $ticket->user_id);
        $this->assertSame('Jordan', (string) $ticket->firstname);
        $this->assertSame('Buyer', (string) $ticket->surname);
        $this->assertSame('buyer@example.com', (string) $ticket->email);
        $this->assertSame('0411222333', (string) $ticket->phone);
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createTicketedWorkshop(array $overrides = []): Workshop
    {
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $hero = Media::factory()->create([
            'name' => 'hero-'.strtolower((string) fake()->unique()->bothify('######')).'.png',
            'mime_type' => 'image/png',
            'user_id' => (string) $owner->id,
        ]);
        $startsAt = now()->addDays(7);

        return Workshop::query()->create(array_merge([
            'title' => 'Child Friendly Workshop',
            'content' => '<p>Hands-on session.</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => $startsAt->copy()->subHour(),
            'status' => 'open',
            'price' => '15.00',
            'ages' => '8+',
            'registration' => 'tickets',
            'registration_data' => null,
            'private_code' => null,
            'hosted_for' => null,
            'is_private' => false,
            'is_hidden' => false,
            'max_tickets' => 20,
            'ticket_group_slug' => null,
            'location_id' => (string) $location->id,
            'user_id' => (string) $owner->id,
            'hero_media_name' => (string) $hero->name,
        ], $overrides));
    }
}
