<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Location;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AdminTicketIndexTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_admin_ticket_index_links_invoice_numbers_to_the_invoice_edit_page(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createTicketWorkshop();
        $invoice = Invoice::factory()->create([
            'status' => Invoice::STATUS_ISSUED,
        ]);

        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'invoice_id' => $invoice->id,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.ticket.index'));

        $response->assertOk();
        $response->assertSee(route('admin.invoice.edit', $invoice), false);
        $response->assertSee($invoice->invoice_number, false);
        $response->assertDontSee('Invoice #'.$invoice->invoice_number, false);
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
            'title' => 'Ticket Index Workshop',
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
