<?php

namespace Tests\Feature;

use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkshopRegistrationGroupAssignmentTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.altcha_enabled' => false]);
    }

    public function test_free_ticket_checkout_assigns_the_workshop_group_to_the_purchaser_user(): void
    {
        $workshop = $this->createTicketedWorkshop([
            'price' => 'Free',
            'ticket_group_slug' => 'minecraft',
        ]);

        $response = $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1,
            'firstname' => 'Alex',
            'surname' => 'Builder',
            'email' => 'alex@example.com',
            'phone' => '0400000000',
        ]);

        $response->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $ghostUser = User::query()->where('email', 'alex@example.com')->first();

        $this->assertNotNull($ghostUser);
        $this->assertNull($ghostUser->email_verified_at);
        $this->assertDatabaseHas('user_groups', [
            'user_id' => (string) $ghostUser->id,
            'slug' => 'minecraft',
        ]);
    }

    public function test_bank_transfer_checkout_assigns_the_workshop_group_before_payment_is_settled(): void
    {
        $workshop = $this->createTicketedWorkshop([
            'price' => '25.00',
            'ticket_group_slug' => 'minecraft',
        ]);

        $beginResponse = $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1,
            'firstname' => 'Pending',
            'surname' => 'Person',
            'email' => 'pending@example.com',
            'phone' => '0400111222',
        ]);
        $beginResponse->assertRedirect(route('workshop.ticket.flow.payment', $workshop));

        $paymentResponse = $this->post(route('workshop.ticket.flow.payment.process', $workshop), [
            'payment_method' => 'bank_transfer',
        ]);

        $paymentResponse->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $purchaser = User::query()->where('email', 'pending@example.com')->first();

        $this->assertNotNull($purchaser);
        $this->assertDatabaseHas('user_groups', [
            'user_id' => (string) $purchaser->id,
            'slug' => 'minecraft',
        ]);
    }

    public function test_checkout_does_not_add_the_same_group_twice_for_the_same_account(): void
    {
        $existingUser = User::factory()->unverified()->create([
            'email' => 'repeat@example.com',
        ]);
        $existingUser->groups()->create(['slug' => 'minecraft']);

        $workshop = $this->createTicketedWorkshop([
            'price' => '15.00',
            'ticket_group_slug' => 'minecraft',
        ]);

        $response = $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 2,
            'firstname' => 'Repeat',
            'surname' => 'Person',
            'email' => 'repeat@example.com',
            'phone' => '0400333444',
        ]);

        $response->assertRedirect(route('workshop.ticket.flow.payment', $workshop));

        $paymentResponse = $this->post(route('workshop.ticket.flow.payment.process', $workshop), [
            'payment_method' => 'bank_transfer',
        ]);

        $paymentResponse->assertRedirect(route('workshop.ticket.flow.details', $workshop));
        $this->assertSame(1, $existingUser->groups()->where('slug', 'minecraft')->count());
    }

    /**
     * @param array<string, mixed> $overrides
     */
    private function createTicketedWorkshop(array $overrides = []): Workshop
    {
        $author = User::factory()->create();
        $location = Location::factory()->create();
        $hero = Media::factory()->create([
            'name' => 'hero-'.strtolower((string) fake()->unique()->bothify('######')).'.png',
            'mime_type' => 'image/png',
            'user_id' => (string) $author->id,
        ]);
        $startsAt = now()->addDays(7);

        return Workshop::query()->create(array_merge([
            'title' => 'Minecraft Workshop',
            'content' => '<p>Build things.</p>',
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => $startsAt->copy()->subHour(),
            'status' => 'open',
            'price' => '10.00',
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
            'user_id' => (string) $author->id,
            'hero_media_name' => (string) $hero->name,
        ], $overrides));
    }
}
