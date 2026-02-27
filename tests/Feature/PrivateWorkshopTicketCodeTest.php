<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\User;
use App\Models\Workshop;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrivateWorkshopTicketCodeTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_ticket_workshop_start_page_requires_private_code(): void
    {
        $workshop = $this->createPrivateTicketWorkshop();

        $this->get(route('workshop.ticket.flow.start', $workshop))
            ->assertOk()
            ->assertSee('Access Code')
            ->assertSee('private workshop', false);
    }

    public function test_private_ticket_workshop_begin_rejects_invalid_code(): void
    {
        config(['security.altcha_enabled' => false]);

        $workshop = $this->createPrivateTicketWorkshop();

        $response = $this->from(route('workshop.ticket.flow.start', $workshop))
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('workshop.ticket.flow.begin', $workshop), [
                '_token' => 'test-csrf-token',
                'quantity' => 1,
                'firstname' => 'Casey',
                'surname' => 'Buyer',
                'email' => 'casey@example.com',
                'phone' => '0400000000',
                'private_code' => 'WRONG-CODE',
            ]);

        $response->assertRedirect(route('workshop.ticket.flow.start', $workshop));
        $response->assertSessionHasErrors('private_code');
    }

    public function test_private_ticket_workshop_begin_accepts_mixed_case_code(): void
    {
        config(['security.altcha_enabled' => false]);

        $workshop = $this->createPrivateTicketWorkshop();

        $response = $this->from(route('workshop.ticket.flow.start', $workshop))
            ->withSession(['_token' => 'test-csrf-token'])
            ->post(route('workshop.ticket.flow.begin', $workshop), [
                '_token' => 'test-csrf-token',
                'quantity' => 1,
                'firstname' => 'Casey',
                'surname' => 'Buyer',
                'email' => 'casey@example.com',
                'phone' => '0400000000',
                'private_code' => 'code-123',
            ]);

        $response->assertRedirect(route('workshop.ticket.flow.details', $workshop));
        $response->assertSessionHasNoErrors();
    }

    private function createPrivateTicketWorkshop(): Workshop
    {
        $owner = User::factory()->create();
        Media::create([
            'name' => 'hero.png',
            'title' => 'Hero',
            'hash' => str_repeat('c', 64),
            'mime_type' => 'image/png',
            'size' => 2048,
            'user_id' => $owner->id,
        ]);

        return Workshop::create([
            'title' => 'Private Ticket Workshop',
            'content' => '<p>Private workshop</p>',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(2),
            'status' => 'open',
            'is_private' => true,
            'registration' => 'tickets',
            'private_code' => 'CODE-123',
            'max_tickets' => 10,
            'hero_media_name' => 'hero.png',
            'user_id' => $owner->id,
        ]);
    }
}
