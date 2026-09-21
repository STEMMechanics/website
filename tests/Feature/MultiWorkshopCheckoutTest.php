<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\TicketOrderConfirmation;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\Location;
use App\Models\Media;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workshop;
use App\Services\SquareApiService;
use App\Services\WorkshopTicketService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Mockery;
use Tests\TestCase;

class MultiWorkshopCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.altcha_enabled' => false, 'services.square.location_id' => 'L123', 'services.square.application_id' => 'A123']);
        Queue::fake();
        $this->freezeTime();
    }

    private function begin(Workshop $workshop, int $quantity = 2): TestResponse
    {
        return $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => $quantity, 'firstname' => 'Parent', 'surname' => 'Example',
            'email' => 'parent@example.com', 'phone' => '0400123456',
        ])->assertSessionHasNoErrors();
    }

    private function cartAction(Workshop $workshop, string $action, ?Workshop $other = null): TestResponse
    {
        return $this->post(route('workshop.ticket.flow.cart.update', $workshop), [
            'action' => $action, 'workshop_id' => $other?->id,
        ]);
    }

    public function test_four_sessions_across_venues_and_online_share_payment_and_participant_details(): void
    {
        $workshops = collect([
            $this->createTicketedWorkshop(['title' => 'Julia Creek Towers', 'price' => '15']),
            $this->createTicketedWorkshop(['title' => 'Library Robots', 'price' => '20']),
            $this->createTicketedWorkshop(['title' => 'Online Coding', 'type' => 'online', 'price' => '10']),
            $this->createTicketedWorkshop(['title' => 'Community Rockets', 'price' => '25', 'participant_information' => '<p>Bring a hat.</p>']),
        ]);
        $anchor = $workshops->first();
        $this->get(route('workshop.ticket.flow.start', $anchor))->assertOk()->assertDontSee('Book more workshops');
        $this->begin($anchor)->assertRedirect(route('workshop.ticket.flow.cart', $anchor));
        $this->get(route('workshop.ticket.flow.cart', $anchor))->assertOk()->assertSee('Add workshop')->assertSee('Online Coding');
        foreach ($workshops->skip(1) as $other) {
            $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        }
        $this->get(route('workshop.ticket.flow.cart', $anchor))->assertOk()->assertSee('Online Coding')->assertSee('$140.00');
        $this->cartAction($anchor, 'continue')->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.payment', $anchor));
        $this->get(route('workshop.ticket.flow.payment', $anchor))->assertOk()->assertSee('Community Rockets')->assertDontSee('<option value="pay_at_door">', false);
        $gateway = Mockery::mock(SquareApiService::class);
        $gateway->shouldReceive('isEnabled')->andReturn(true);
        $gateway->shouldReceive('createPayment')->once()->with(Mockery::on(fn ($data) => $data['amount_money']['amount'] === 14000))
            ->andReturn(['payment' => ['id' => 'multi-payment', 'status' => 'COMPLETED', 'amount_money' => ['amount' => 14000]]]);
        $this->app->instance(SquareApiService::class, $gateway);
        $this->post(route('workshop.ticket.flow.payment.process', $anchor), ['payment_method' => 'credit_card', 'source_id' => 'cnon:test'])
            ->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.details', $anchor));
        $this->post(route('workshop.ticket.flow.payment.process', $anchor), ['payment_method' => 'credit_card', 'source_id' => 'cnon:test'])
            ->assertRedirect(route('workshop.ticket.flow.details', $anchor));
        $this->assertDatabaseCount('invoices', 1);
        $this->assertDatabaseCount('payments', 1);
        $this->assertSame(140.0, (float) Invoice::sole()->total_amount);
        $this->assertSame(8, Ticket::where('status', Ticket::STATUS_PAID)->count());
        foreach ($workshops as $selected) {
            foreach ($selected->tickets()->get() as $ticket) {
                $this->assertSame($selected->id, $ticket->invoiceLine->details_json['workshop_id']);
                $this->assertSame((float) $selected->price, (float) $ticket->invoiceLine->line_total_inc_tax);
            }
        }
        $this->get(route('workshop.ticket.flow.details', $anchor))->assertOk()->assertViewHas('participantTickets', fn ($tickets) => $tickets->count() === 2)->assertSee('Bring a hat.');
        $ids = $anchor->tickets()->orderBy('id')->pluck('id');
        $this->post(route('workshop.ticket.flow.details.save', $anchor), ['tickets' => [
            ['id' => $ids[0], 'firstname' => 'Alex', 'surname' => 'Example', 'email' => 'parent@example.com', 'phone' => '0400123456'],
            ['id' => $ids[1], 'firstname' => 'Sam', 'surname' => 'Example', 'email' => 'parent@example.com', 'phone' => '0400123456'],
        ]])->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.complete', $anchor));
        $this->assertSame(4, Ticket::where('firstname', 'Alex')->count());
        $this->assertSame(4, Ticket::where('firstname', 'Sam')->count());
        $this->get(route('workshop.ticket.flow.complete', $anchor))->assertOk()->assertSee('Online Coding');
        Queue::assertPushed(SendEmail::class, function ($job) {
            if (! $job->mailable instanceof TicketOrderConfirmation) {
                return false;
            }
            $this->assertCount(4, $job->mailable->workshop['bookedSessions']);
            $html = $job->mailable->render();
            $this->assertStringContainsString('Library Robots', $html);
            $this->assertStringContainsString('Bring a hat.', $html);
            $this->assertSame(8, $job->mailable->ticketCount);

            return true;
        });
    }

    public function test_adding_refreshes_all_holds_without_replacing_tickets_and_is_capped(): void
    {
        $anchor = $this->createTicketedWorkshop(['max_tickets' => 2, 'early_bird_price' => 5, 'early_bird_ticket_limit' => 1, 'early_bird_ends_at' => now()->addDay()]);
        $others = collect(range(1, 4))->map(fn () => $this->createTicketedWorkshop());
        $started = now()->copy();
        $this->begin($anchor);
        $ids = $anchor->tickets()->pluck('id')->all();
        foreach ([8, 8, 8, 4] as $index => $minutes) {
            $this->travel($minutes)->minutes();
            $this->cartAction($anchor, 'add', $others[$index])->assertSessionHasNoErrors();
        }
        $session = session('ticket_checkout_flow.'.$anchor->id);
        $this->assertSame($started->addMinutes(30)->toIso8601String(), $session['expires_at']);
        $this->assertSame($ids, $anchor->tickets()->pluck('id')->all());
        $this->assertSame(1, $anchor->tickets()->where('is_early_bird', true)->count());
        $this->cartAction($anchor, 'remove', $others[0])->assertSessionHasNoErrors();
        $this->assertSame(0, $others[0]->tickets()->count());
        $this->travel(3)->minutes();
        $this->cartAction($anchor, 'continue')->assertRedirect(route('workshop.ticket.flow.start', $anchor));
        $this->assertSame(2, app(WorkshopTicketService::class)->availableTickets($anchor->fresh()));
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_unavailable_addition_does_not_change_existing_booking_or_timer(): void
    {
        $anchor = $this->createTicketedWorkshop();
        $other = $this->createTicketedWorkshop(['max_tickets' => 1]);
        $this->begin($anchor);
        $before = session('ticket_checkout_flow.'.$anchor->id);
        $this->travel(2)->minutes();
        $this->cartAction($anchor, 'add', $other)->assertSessionHasErrors('workshop_id');
        $this->assertSame($before, session('ticket_checkout_flow.'.$anchor->id));
        $this->assertSame(0, $other->tickets()->count());
        $this->assertSame(2, $anchor->tickets()->count());
    }

    public function test_free_anchor_can_add_paid_session_and_cancellation_releases_both(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => 'Free']);
        $other = $this->createTicketedWorkshop(['price' => '30']);
        $this->begin($anchor)->assertRedirect(route('workshop.ticket.flow.cart', $anchor));
        $this->assertSame(0, Ticket::where('status', Ticket::STATUS_PAID)->count());
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        $this->cartAction($anchor, 'continue')->assertRedirect(route('workshop.ticket.flow.payment', $anchor));
        $this->get(route('workshop.ticket.flow.payment', $anchor))->assertOk()->assertSee('$60.00');
        $this->post(route('workshop.ticket.flow.cancel', $anchor))->assertRedirect();
        $this->assertDatabaseCount('tickets', 0);
    }

    public function test_free_combined_booking_requires_confirmation_and_shares_details(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => 'Free']);
        $other = $this->createTicketedWorkshop(['price' => 'Free', 'type' => 'online']);
        $this->begin($anchor);
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        $this->cartAction($anchor, 'continue')->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.details', $anchor));
        $this->assertSame(4, Ticket::where('status', Ticket::STATUS_PAID)->count());
        $this->assertDatabaseCount('invoices', 0);
        $this->cartAction($anchor, 'remove', $other)->assertRedirect(route('workshop.ticket.flow.details', $anchor));
        $this->assertDatabaseCount('tickets', 4);
    }

    public function test_private_or_closed_sessions_cannot_be_added_and_closed_cart_blocks_payment(): void
    {
        $anchor = $this->createTicketedWorkshop();
        $other = $this->createTicketedWorkshop();
        $private = $this->createTicketedWorkshop(['is_private' => true]);
        $this->begin($anchor);
        $this->cartAction($anchor, 'add', $private)->assertSessionHasErrors('workshop_id');
        session()->forget('errors');
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        $other->update(['status' => 'closed']);
        $this->post(route('workshop.ticket.flow.payment.process', $anchor), ['payment_method' => 'bank_transfer'])->assertRedirect(route('workshop.ticket.flow.start', $anchor));
        $this->assertDatabaseCount('invoices', 0);
        $this->assertDatabaseCount('payments', 0);
    }

    public function test_voucher_must_cover_every_session_and_fixed_discount_is_applied_once(): void
    {
        $anchor = $this->createTicketedWorkshop();
        $other = $this->createTicketedWorkshop(['price' => '20']);
        $coupon = Coupon::factory()->create([
            'code' => 'SESSION5', 'status' => Coupon::STATUS_ACTIVE,
            'discount_type' => Coupon::DISCOUNT_TYPE_FIXED_AMOUNT, 'amount' => 5,
            'applies_to_workshops' => true, 'applies_to_products' => false,
        ]);
        $coupon->restrictedWorkshops()->attach($anchor);
        $this->begin($anchor);
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        $this->postJson(route('workshop.ticket.flow.voucher', $anchor), ['voucher_code' => 'SESSION5'])
            ->assertStatus(422)->assertJsonValidationErrors('voucher_code');
        $coupon->restrictedWorkshops()->attach($other);
        $this->postJson(route('workshop.ticket.flow.voucher', $anchor), ['voucher_code' => 'SESSION5'])
            ->assertOk()->assertJsonPath('summary.voucher_discount_amount', 5);
        $this->post(route('workshop.ticket.flow.payment.process', $anchor), ['payment_method' => 'bank_transfer'])
            ->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.details', $anchor));
        $this->assertSame(65.0, (float) Invoice::sole()->total_amount);
        $this->assertSame(4, Ticket::where('status', Ticket::STATUS_PENDING_XFER)->count());
    }

    public function test_guest_can_leave_checkout_and_resume_from_the_cart_until_the_hold_expires(): void
    {
        $anchor = $this->createTicketedWorkshop();
        $other = $this->createTicketedWorkshop();
        $this->begin($anchor);
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        $ids = Ticket::orderBy('id')->pluck('id')->all();
        $this->get(route('workshop.index'))->assertOk()->assertSee('Continue booking')
            ->assertSee(route('workshop.ticket.flow.cart', $anchor), false)->assertSee('4 tickets reserved');
        $bookings = app(\App\Services\WorkshopCheckoutCart::class)->bookings();
        $this->assertCount(1, $bookings);
        $this->assertSame(4, $bookings[0]['count']);
        $this->get($bookings[0]['url'])->assertOk()->assertSee('Review your workshops');
        $this->assertSame($ids, Ticket::orderBy('id')->pluck('id')->all());
        $this->travel(11)->minutes();
        $this->assertSame([], app(\App\Services\WorkshopCheckoutCart::class)->bookings());
        $this->get(route('workshop.index'))->assertOk()->assertDontSee('Continue booking');
    }

    public function test_completed_bookings_are_removed_from_cart_navigation(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => 'Free']);
        $other = $this->createTicketedWorkshop(['price' => 'Free']);
        $this->begin($anchor);
        $this->assertCount(1, app(\App\Services\WorkshopCheckoutCart::class)->bookings());
        $this->cartAction($anchor, 'continue')->assertSessionHasNoErrors();
        $this->assertSame([], app(\App\Services\WorkshopCheckoutCart::class)->bookings());
    }

    private function createTicketedWorkshop(array $overrides = []): Workshop
    {
        $author = User::factory()->create();
        $location = Location::factory()->create();
        /** @var Media $hero */
        $hero = Media::factory()->create([
            'name' => 'hero-'.strtolower((string) fake()->unique()->bothify('######')).'.png',
            'mime_type' => 'image/png',
            'user_id' => (string) $author->id,
        ]);
        $startsAt = now()->addDays(7);

        return Workshop::query()->create(array_merge([
            'title' => 'Ticket Email Workshop',
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
