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

    public function test_today_onwards_includes_all_of_today_and_future_but_not_past_or_cancelled_tickets(): void
    {
        $this->travelTo(now()->startOfDay()->addHours(15));
        $admin = $this->createAdminUser();
        $past = $this->createTicketWorkshop(['starts_at' => today()->subDay()]);
        $todayWorkshop = $this->createTicketWorkshop(['starts_at' => today()->addHours(8)]);
        $future = $this->createTicketWorkshop(['starts_at' => today()->addDay()]);
        $oldTicket = Ticket::factory()->create(['workshop_id' => $past->id, 'status' => Ticket::STATUS_PAID]);
        $todayTicket = Ticket::factory()->create(['workshop_id' => $todayWorkshop->id, 'status' => Ticket::STATUS_PAID]);
        $futureTicket = Ticket::factory()->create(['workshop_id' => $future->id, 'status' => Ticket::STATUS_PAID]);
        $cancelled = Ticket::factory()->create(['workshop_id' => $future->id, 'status' => Ticket::STATUS_CANCELLED]);

        $this->actingAs($admin)->get(route('admin.ticket.index'))->assertOk()
            ->assertSee('Current tickets')
            ->assertViewHas('tickets', fn ($tickets) => $tickets->total() === 2
                && $tickets->contains('id', $todayTicket->id) && $tickets->contains('id', $futureTicket->id));
        $this->get(route('admin.ticket.index', ['ticket_scope' => 'all']))->assertOk()
            ->assertViewHas('tickets', fn ($tickets) => $tickets->total() === 4 && $tickets->contains('id', $oldTicket->id));
        $this->get(route('admin.ticket.index', ['ticket_scope' => 'cancelled']))->assertOk()
            ->assertViewHas('tickets', fn ($tickets) => $tickets->total() === 1 && $tickets->contains('id', $cancelled->id));
    }

    public function test_status_multiselect_combines_with_workshop_filters_and_can_be_cleared(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createTicketWorkshop(['title' => 'Robot cats', 'starts_at' => today()->addDay()->addHours(18)]);
        $other = $this->createTicketWorkshop(['title' => 'Other workshop', 'starts_at' => today()->addDay()]);
        foreach ([Ticket::STATUS_PAID, Ticket::STATUS_CANCELLED, Ticket::STATUS_REISSUED] as $status) {
            Ticket::factory()->create(['workshop_id' => $workshop->id, 'status' => $status]);
        }
        Ticket::factory()->create(['workshop_id' => $other->id, 'status' => Ticket::STATUS_PAID]);
        $params = ['ticket_status' => ['active', 'reissued'], 'workshop_name' => '*cats', 'workshop_from' => today()->toDateString(), 'workshop_to' => today()->addDay()->toDateString()];
        $response = $this->actingAs($admin)->get(route('admin.ticket.index', $params))->assertOk()
            ->assertViewHas('tickets', fn ($tickets) => $tickets->total() === 2)
            ->assertSee('Status: Active, Reissued')->assertDontSee('Tickets: Current tickets');
        $dom = new \DOMDocument();
        @$dom->loadHTML($response->getContent());
        $xpath = new \DOMXPath($dom);
        $this->assertSame(2, $xpath->query('//input[@type="checkbox" and @name="ticket_status[]" and @checked]')->length);
        $this->assertSame(1, $xpath->query('//input[@name="workshop_from" and @type="date" and @value="'.today()->toDateString().'"]')->length);
        $this->get(route('admin.ticket.index', ['ticket_filters' => 1]))->assertOk()
            ->assertViewHas('tickets', fn ($tickets) => $tickets->total() === 4);
        $this->getJson(route('admin.ticket.index', ['ticket_status' => ['unknown']]))->assertUnprocessable();
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
