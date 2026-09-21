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
use App\Services\WorkshopCheckoutCart;
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

    private function reviewAll(Workshop $workshop): TestResponse
    {
        $session = session('ticket_checkout_flow.'.$workshop->id);

        return $this->post(route('workshop.ticket.flow.review.save', $workshop), ['participants' => [
            ['firstname' => 'Alex', 'surname' => 'Example', 'workshops' => $session['workshop_ids']],
            ['firstname' => 'Sam', 'surname' => 'Example', 'workshops' => $session['workshop_ids']],
        ]])->assertSessionHasNoErrors();
    }

    public function test_review_selections_match_reserved_tickets_including_newly_added_workshops(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => '15']);
        $other = $this->createTicketedWorkshop(['price' => '20']);
        $this->begin($anchor, 3);
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        $response = $this->get(route('workshop.ticket.flow.review', $anchor))->assertOk();
        $participants = $response->viewData('participants');
        $this->assertSame([$anchor->id, $other->id], $participants[0]['workshops']);
        $this->assertSame([$anchor->id, $other->id], $participants[1]['workshops']);
        $this->assertSame([$anchor->id, $other->id], $participants[2]['workshops']);
        $this->assertSame(app(WorkshopCheckoutCart::class)->bookings()[0]['count'], collect($participants)->sum(fn ($person) => count($person['workshops'])));

        $this->reviewAll($anchor);
        $third = $this->createTicketedWorkshop(['price' => '25']);
        $this->cartAction($anchor, 'add', $third)->assertSessionHasNoErrors();
        $people = $this->get(route('workshop.ticket.flow.review', $anchor))->assertOk()->viewData('participants');
        $this->assertSame('Alex', $people[0]['firstname']);
        $this->assertContains($third->id, $people[0]['workshops']);
        $this->assertContains($third->id, $people[1]['workshops']);
        $this->assertSame(app(WorkshopCheckoutCart::class)->bookings()[0]['count'], collect($people)->sum(fn ($person) => count($person['workshops'])));
    }

    public function test_incomplete_participant_drafts_survive_leaving_to_add_workshops(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => '15']);
        $other = $this->createTicketedWorkshop(['price' => '20']);
        $this->begin($anchor);
        $before = session('ticket_checkout_flow.'.$anchor->id);
        $people = [
            ['firstname' => 'Alex', 'surname' => 'Example', 'workshops' => [$anchor->id]],
            ['firstname' => 'Sam', 'surname' => '', 'workshops' => []],
        ];
        $this->postJson(route('workshop.ticket.flow.review.draft', $anchor), ['participants' => $people])->assertOk();
        $after = session('ticket_checkout_flow.'.$anchor->id);
        $this->assertSame($before['hold_ids'], $after['hold_ids']);
        $this->assertSame($before['expires_at'], $after['expires_at']);
        $this->assertSame(1, app(WorkshopCheckoutCart::class)->bookings()[0]['count']);
        $this->get(route('workshop.index'))->assertOk();
        $this->post(route('workshop.ticket.flow.join', $other))->assertRedirect(route('workshop.ticket.flow.cart', $anchor));
        $people[0]['workshops'][] = $other->id;
        $people[1]['workshops'][] = $other->id;
        $this->assertSame($people, $this->get(route('workshop.ticket.flow.review', $anchor))->assertOk()->viewData('participants'));
        $this->assertSame(3, app(WorkshopCheckoutCart::class)->bookings()[0]['count']);
        $this->reviewAll($anchor);
        $this->assertArrayNotHasKey('review_draft', session('ticket_checkout_flow.'.$anchor->id));
        $this->assertSame(4, app(WorkshopCheckoutCart::class)->bookings()[0]['count']);
    }

    public function test_participant_additions_removals_and_attendance_are_saved_and_used_for_new_workshops(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => '15']);
        $other = $this->createTicketedWorkshop(['price' => '20']);
        $this->begin($anchor);
        $people = [
            ['firstname' => 'Alex', 'surname' => 'Example', 'workshops' => [$anchor->id]],
            ['firstname' => 'Sam', 'surname' => 'Example', 'workshops' => []],
            ['firstname' => 'Chris', 'surname' => 'Example', 'workshops' => [$anchor->id]],
        ];
        $draftUrl = route('workshop.ticket.flow.review.draft', $anchor);
        $reviewUrl = route('workshop.ticket.flow.review', $anchor);
        $this->postJson($draftUrl, ['participants' => $people])->assertOk();
        $this->assertSame($people, $this->get($reviewUrl)->assertOk()->viewData('participants'));
        array_shift($people);
        $this->postJson($draftUrl, ['participants' => $people])->assertOk();
        $this->assertSame($people, $this->get($reviewUrl)->assertOk()->viewData('participants'));
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        foreach ($people as &$person) {
            $person['workshops'][] = $other->id;
        }
        unset($person);
        $this->assertSame($people, $this->get($reviewUrl)->assertOk()->viewData('participants'));
        $this->assertSame(2, $other->tickets()->count());
        $this->cartAction($anchor, 'remove', $other)->assertSessionHasNoErrors();
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        $this->assertSame($people, $this->get($reviewUrl)->assertOk()->viewData('participants'));
    }

    public function test_draft_cannot_include_foreign_workshops_or_revive_expired_holds(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => '15']);
        $other = $this->createTicketedWorkshop(['price' => '20']);
        $this->begin($anchor);
        $people = [['firstname' => '', 'surname' => '', 'workshops' => [$other->id]]];
        $this->postJson(route('workshop.ticket.flow.review.draft', $anchor), ['participants' => $people])->assertUnprocessable();
        $people[0]['workshops'] = [$anchor->id];
        $this->travel(11)->minutes();
        $this->postJson(route('workshop.ticket.flow.review.draft', $anchor), ['participants' => $people])->assertStatus(409);
        $this->assertArrayNotHasKey('review_draft', session('ticket_checkout_flow.'.$anchor->id));
    }

    public function test_workshops_with_optional_products_join_existing_booking_and_keep_equipment_checkout(): void
    {
        $product = \App\Models\Product::factory()->create(['status' => 'active', 'product_type' => 'physical', 'price' => 32, 'inventory_quantity' => 10]);
        $anchor = $this->createTicketedWorkshop(['price' => '15']);
        $other = $this->createTicketedWorkshop(['title' => 'Butterfly Trainers', 'price' => '20', 'optional_product_ids' => [$product->id]]);
        $this->begin($anchor);
        $this->get(route('workshop.show', $other))->assertOk()->assertSee('Add to booking');
        $this->post(route('workshop.ticket.flow.join', $other))->assertRedirect(route('workshop.ticket.flow.cart', $anchor));
        $this->assertSame(2, $other->tickets()->count());
        $this->assertSame(4, app(WorkshopCheckoutCart::class)->bookings()[0]['count']);
        $this->travel(9)->minutes();
        $this->reviewAll($anchor)->assertRedirect(route('workshop.ticket.flow.equipment', $anchor));
        $equipmentPage = $this->get(route('workshop.ticket.flow.equipment', $anchor))->assertOk()->assertSee($product->title);
        $this->assertSame('Butterfly Trainers', $equipmentPage->viewData('equipmentTitle'));
        $this->assertSame([$other->id], $equipmentPage->viewData('equipmentWorkshops')->pluck('id')->all());
        $this->assertSame(70.0, $equipmentPage->viewData('ticketAmount'));
        $this->post(route('workshop.ticket.flow.equipment.save', $anchor), ['action' => 'select', 'quantities' => [$product->id => 1]])
            ->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.delivery', $anchor));
        $this->assertCount(1, app(\App\Services\WorkshopEquipmentService::class)->cart($anchor)->contents()['lines']);
        $deliveryPage = $this->get(route('workshop.ticket.flow.delivery', $anchor))->assertOk();
        $this->assertSame('Butterfly Trainers', $deliveryPage->viewData('equipmentTitle'));
        $this->assertSame(70.0, $deliveryPage->viewData('ticketAmount'));
        $this->post(route('workshop.ticket.flow.equipment.save', $anchor), ['action' => 'skip'])->assertRedirect(route('workshop.ticket.flow.payment', $anchor));
        $this->get(route('workshop.ticket.flow.payment', $anchor))->assertOk();
        $this->post(route('workshop.ticket.flow.payment.process', $anchor), ['payment_method' => 'bank_transfer'])
            ->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.complete', $anchor));
        $this->assertSame(4, Ticket::where('status', Ticket::STATUS_PENDING_XFER)->count());
    }

    public function test_adding_standard_workshop_to_equipment_booking_preserves_its_longer_deadline(): void
    {
        $product = \App\Models\Product::factory()->create(['status' => 'active']);
        $anchor = $this->createTicketedWorkshop(['price' => '15', 'optional_product_ids' => [$product->id]]);
        $other = $this->createTicketedWorkshop(['price' => '20']);
        $this->begin($anchor);
        $deadline = session('ticket_checkout_flow.'.$anchor->id.'.expires_at');
        $this->post(route('workshop.ticket.flow.join', $other))->assertRedirect(route('workshop.ticket.flow.cart', $anchor));
        $this->assertSame($deadline, session('ticket_checkout_flow.'.$anchor->id.'.expires_at'));
        $this->travel(11)->minutes();
        $this->assertSame(4, app(WorkshopCheckoutCart::class)->bookings()[0]['count']);
        $this->reviewAll($anchor)->assertRedirect(route('workshop.ticket.flow.equipment', $anchor));
        $this->travel(10)->minutes();
        $this->assertSame([], app(WorkshopCheckoutCart::class)->bookings());
        $this->get(route('workshop.ticket.flow.review', $anchor))->assertRedirect(route('workshop.ticket.flow.start', $anchor));
    }

    public function test_free_combined_booking_offers_optional_products_and_removing_workshop_removes_its_equipment(): void
    {
        $product = \App\Models\Product::factory()->create(['status' => 'active', 'product_type' => 'physical', 'price' => 32, 'inventory_quantity' => 10]);
        $anchor = $this->createTicketedWorkshop(['price' => '0']);
        $other = $this->createTicketedWorkshop(['price' => '0', 'optional_product_ids' => [$product->id]]);
        $this->begin($anchor);
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        $this->reviewAll($anchor)->assertRedirect(route('workshop.ticket.flow.equipment', $anchor));
        $equipment = app(\App\Services\WorkshopEquipmentService::class);
        $equipment->select($anchor, [$product->id => 1], []);
        $this->cartAction($anchor, 'remove', $other)->assertSessionHasNoErrors();
        $this->assertEmpty($equipment->cart($anchor)->contents()['lines']);
        $this->reviewAll($anchor)->assertRedirect(route('workshop.ticket.flow.complete', $anchor));
    }

    public function test_limited_workshop_places_select_first_participants_and_show_a_themed_notice(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => '15']);
        $other = $this->createTicketedWorkshop(['title' => 'Butterfly Trainers', 'price' => '20', 'max_tickets' => 2]);
        $this->begin($anchor, 3);
        $this->get(route('workshop.show', $other))->assertOk()->assertSee('Add to booking')->assertDontSee('available for your 3 participants');
        $people = collect(['Alex', 'Sam', 'Chris'])->map(fn ($name) => ['firstname' => $name, 'surname' => 'Example', 'workshops' => [$anchor->id]])->all();
        $this->postJson(route('workshop.ticket.flow.review.draft', $anchor), ['participants' => $people])->assertOk();
        $this->post(route('workshop.ticket.flow.join', $other))->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.review', $anchor))
            ->assertSessionHas('message-type', 'warning')->assertSessionHas('message', 'Butterfly Trainers: only 2 spots are available. The first 2 participants have been selected. You can change who attends below.');
        $participants = $this->get(route('workshop.ticket.flow.review', $anchor))->assertOk()->assertSee('only 2 spots are available')->viewData('participants');
        $this->assertContains($other->id, $participants[0]['workshops']);
        $this->assertContains($other->id, $participants[1]['workshops']);
        $this->assertNotContains($other->id, $participants[2]['workshops']);
        $this->assertSame(2, $other->tickets()->count());
        $this->assertSame(5, app(WorkshopCheckoutCart::class)->bookings()[0]['count']);
    }

    public function test_review_displays_capacity_including_own_holds_and_disables_extra_selections(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => '15', 'max_tickets' => 3]);
        $other = $this->createTicketedWorkshop(['price' => '20', 'max_tickets' => 2]);
        $this->begin($anchor, 3);
        $this->post(route('workshop.ticket.flow.join', $other))->assertRedirect(route('workshop.ticket.flow.review', $anchor));
        $response = $this->get(route('workshop.ticket.flow.review', $anchor))->assertOk()
            ->assertSee('Only 2 spots available. Untick someone to swap.')->assertSee('Only 3 spots available. Untick someone to swap.')
            ->assertSee('selectionFull(person, $el.value)', false)->assertDontSee('@js(', false);
        $this->assertSame(3, $response->viewData('pricing')[$anchor->id]['capacity']);
        $this->assertSame(2, $response->viewData('pricing')[$other->id]['capacity']);
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
        $this->get(route('workshop.ticket.flow.cart', $anchor))->assertOk()->assertSee('Add workshop')->assertSee('Online Coding')->assertDontSee('@js(', false);
        foreach ($workshops->skip(1) as $other) {
            $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        }
        $this->get(route('workshop.ticket.flow.cart', $anchor))->assertOk()->assertSee('Online Coding')->assertDontSee('Your booking ·');
        $this->cartAction($anchor, 'continue')->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.review', $anchor));
        $this->get(route('workshop.ticket.flow.review', $anchor))->assertOk()->assertSee('Who’s coming?');
        $this->reviewAll($anchor)->assertRedirect(route('workshop.ticket.flow.payment', $anchor));
        $this->get(route('workshop.ticket.flow.payment', $anchor))->assertOk()->assertSee('Community Rockets')->assertDontSee('<option value="pay_at_door">', false);
        $gateway = Mockery::mock(SquareApiService::class);
        $gateway->shouldReceive('isEnabled')->andReturn(true);
        $gateway->shouldReceive('createPayment')->once()->with(Mockery::on(fn ($data) => $data['amount_money']['amount'] === 14000))
            ->andReturn(['payment' => ['id' => 'multi-payment', 'status' => 'COMPLETED', 'amount_money' => ['amount' => 14000]]]);
        $this->app->instance(SquareApiService::class, $gateway);
        $this->post(route('workshop.ticket.flow.payment.process', $anchor), ['payment_method' => 'credit_card', 'source_id' => 'cnon:test'])
            ->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.complete', $anchor));
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
        Ticket::factory()->create(['workshop_id' => $other->id, 'status' => Ticket::STATUS_PAID]);
        $before = session('ticket_checkout_flow.'.$anchor->id);
        $this->travel(2)->minutes();
        $this->cartAction($anchor, 'add', $other)->assertSessionHasErrors('workshop_id');
        $this->assertSame($before, session('ticket_checkout_flow.'.$anchor->id));
        $this->assertSame(1, $other->tickets()->count());
        $this->assertSame(2, $anchor->tickets()->count());
    }

    public function test_free_anchor_can_add_paid_session_and_cancellation_releases_both(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => 'Free']);
        $other = $this->createTicketedWorkshop(['price' => '30']);
        $this->begin($anchor)->assertRedirect(route('workshop.ticket.flow.cart', $anchor));
        $this->assertSame(0, Ticket::where('status', Ticket::STATUS_PAID)->count());
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        $this->cartAction($anchor, 'continue')->assertRedirect(route('workshop.ticket.flow.review', $anchor));
        $this->reviewAll($anchor)->assertRedirect(route('workshop.ticket.flow.payment', $anchor));
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
        $this->cartAction($anchor, 'continue')->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.review', $anchor));
        $this->reviewAll($anchor)->assertRedirect(route('workshop.ticket.flow.complete', $anchor));
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
        $this->reviewAll($anchor);
        $this->postJson(route('workshop.ticket.flow.voucher', $anchor), ['voucher_code' => 'SESSION5'])
            ->assertStatus(422)->assertJsonValidationErrors('voucher_code');
        $coupon->restrictedWorkshops()->attach($other);
        $this->postJson(route('workshop.ticket.flow.voucher', $anchor), ['voucher_code' => 'SESSION5'])
            ->assertOk()->assertJsonPath('summary.voucher_discount_amount', 5);
        $this->post(route('workshop.ticket.flow.payment.process', $anchor), ['payment_method' => 'bank_transfer'])
            ->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.complete', $anchor));
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
            ->assertSee('Workshop booking');
        $bookings = app(WorkshopCheckoutCart::class)->bookings();
        $this->assertCount(1, $bookings);
        $this->assertSame(4, $bookings[0]['count']);
        $this->get($bookings[0]['url'])->assertOk()->assertSee('More workshops');
        $this->assertSame($ids, Ticket::orderBy('id')->pluck('id')->all());
        $this->travel(11)->minutes();
        $this->assertSame([], app(WorkshopCheckoutCart::class)->bookings());
        $this->get(route('workshop.index'))->assertOk()->assertDontSee('Continue booking');
    }

    public function test_completed_bookings_are_removed_from_cart_navigation(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => 'Free']);
        $other = $this->createTicketedWorkshop(['price' => 'Free']);
        $this->begin($anchor);
        $this->assertCount(1, app(WorkshopCheckoutCart::class)->bookings());
        $this->reviewAll($anchor);
        $this->assertSame([], app(WorkshopCheckoutCart::class)->bookings());
    }

    public function test_different_children_can_attend_different_sessions_and_unselected_sessions_are_released(): void
    {
        $anchor = $this->createTicketedWorkshop(['price' => '12', 'ages' => '5–8']);
        $older = $this->createTicketedWorkshop(['price' => '20', 'ages' => '9–12', 'max_tickets' => 2]);
        $unused = $this->createTicketedWorkshop(['price' => '30']);
        $this->begin($anchor);
        $this->postJson(route('workshop.ticket.flow.cart.update', $anchor), ['action' => 'add', 'workshop_id' => $older->id])
            ->assertOk()->assertJsonPath('selected.1', $older->id)->assertJsonPath('bookings.0.count', 4);
        $this->cartAction($anchor, 'add', $unused)->assertSessionHasNoErrors();
        $this->get(route('workshop.ticket.flow.payment', $anchor))->assertRedirect(route('workshop.ticket.flow.review', $anchor));
        $this->post(route('workshop.ticket.flow.review.save', $anchor), ['participants' => [
            ['firstname' => 'Younger', 'surname' => 'Child', 'workshops' => [$anchor->id]],
            ['firstname' => 'Older', 'surname' => 'Child', 'workshops' => [$older->id]],
        ]])->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.payment', $anchor));
        $this->assertSame(['Younger'], $anchor->tickets()->pluck('firstname')->all());
        $this->assertSame(['Older'], $older->tickets()->pluck('firstname')->all());
        $this->assertSame(0, $unused->tickets()->count());
        $this->post(route('workshop.ticket.flow.payment.process', $anchor), ['payment_method' => 'bank_transfer'])
            ->assertSessionHasNoErrors()->assertRedirect(route('workshop.ticket.flow.complete', $anchor));
        $this->assertSame(32.0, (float) Invoice::sole()->total_amount);
        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_review_capacity_failure_is_atomic_and_never_accepts_foreign_workshops(): void
    {
        $anchor = $this->createTicketedWorkshop();
        $other = $this->createTicketedWorkshop(['max_tickets' => 2]);
        $foreign = $this->createTicketedWorkshop();
        $this->begin($anchor);
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        $before = Ticket::orderBy('id')->get()->toArray();
        $people = [
            ['firstname' => 'First', 'surname' => 'Child', 'workshops' => [$anchor->id, $other->id]],
            ['firstname' => 'Second', 'surname' => 'Child', 'workshops' => [$other->id]],
            ['firstname' => 'Third', 'surname' => 'Child', 'workshops' => [$other->id]],
        ];
        $this->post(route('workshop.ticket.flow.review.save', $anchor), ['participants' => $people])->assertSessionHasErrors('participants');
        $this->assertSame($before, Ticket::orderBy('id')->get()->toArray());
        $people[1]['workshops'] = [$foreign->id];
        $this->post(route('workshop.ticket.flow.review.save', $anchor), ['participants' => $people])->assertSessionHasErrors('participants.1.workshops.0');
        $this->assertSame($before, Ticket::orderBy('id')->get()->toArray());
        $this->assertDatabaseCount('invoices', 0);
    }

    public function test_ajax_failures_and_removing_a_session_do_not_silently_change_the_booking(): void
    {
        $anchor = $this->createTicketedWorkshop();
        $other = $this->createTicketedWorkshop();
        $private = $this->createTicketedWorkshop(['is_private' => true]);
        $this->begin($anchor);
        $this->postJson(route('workshop.ticket.flow.cart.update', $anchor), ['action' => 'add', 'workshop_id' => $private->id])->assertStatus(422);
        $this->assertSame(0, $private->tickets()->count());
        $this->cartAction($anchor, 'add', $other)->assertSessionHasNoErrors();
        $this->reviewAll($anchor);
        $this->postJson(route('workshop.ticket.flow.cart.update', $anchor), ['action' => 'remove', 'workshop_id' => $other->id])
            ->assertOk()->assertJsonCount(1, 'selected')->assertJsonPath('bookings.0.count', 2);
        $this->post(route('workshop.ticket.flow.payment.process', $anchor), ['payment_method' => 'bank_transfer'])
            ->assertRedirect(route('workshop.ticket.flow.review', $anchor));
        $this->travel(11)->minutes();
        $this->postJson(route('workshop.ticket.flow.cart.update', $anchor), ['action' => 'add', 'workshop_id' => $other->id])
            ->assertStatus(409)->assertJsonPath('redirect', route('workshop.ticket.flow.start', $anchor));
    }

    public function test_workshop_page_adds_to_the_existing_booking_without_restarting_participant_details(): void
    {
        $anchor = $this->createTicketedWorkshop();
        $other = $this->createTicketedWorkshop(['title' => 'Next workshop']);
        $this->begin($anchor);
        $this->reviewAll($anchor);
        $before = session('ticket_checkout_flow.'.$anchor->id);
        $this->travel(3)->minutes();
        $this->get(route('workshop.show', $other))->assertOk()->assertSee('Add to booking')
            ->assertSee(route('workshop.ticket.flow.join', $other), false);
        $this->post(route('workshop.ticket.flow.join', $other))->assertSessionHasNoErrors()
            ->assertRedirect(route('workshop.ticket.flow.cart', $anchor));
        $after = session('ticket_checkout_flow.'.$anchor->id);
        $this->assertSame($before['participants'], $after['participants']);
        $this->assertSame($before['purchaser'], $after['purchaser']);
        $this->assertSame($before['hold_ids'], $anchor->tickets()->orderBy('id')->pluck('id')->all());
        $this->assertSame(2, $other->tickets()->count());
        $this->assertFalse($after['reviewed']);
        $this->assertSame(now()->addMinutes(10)->toIso8601String(), $after['expires_at']);
        $this->assertNull(session('ticket_checkout_flow.'.$other->id));
        $this->post(route('workshop.ticket.flow.join', $other))->assertRedirect(route('workshop.ticket.flow.cart', $anchor));
        $this->assertSame($after, session('ticket_checkout_flow.'.$anchor->id));
        $this->assertSame(2, $other->tickets()->count());
    }

    public function test_already_reserved_full_workshop_resumes_instead_of_creating_duplicate_tickets(): void
    {
        $anchor = $this->createTicketedWorkshop(['max_tickets' => 2]);
        $this->begin($anchor);
        $before = session('ticket_checkout_flow.'.$anchor->id);
        $this->get(route('workshop.show', $anchor))->assertOk()->assertSee('Continue booking');
        $this->post(route('workshop.ticket.flow.join', $anchor))->assertRedirect(route('workshop.ticket.flow.cart', $anchor));
        $this->assertSame($before, session('ticket_checkout_flow.'.$anchor->id));
        $this->assertDatabaseCount('tickets', 2);
    }

    public function test_expired_and_special_bookings_start_their_normal_flow_instead_of_being_combined(): void
    {
        $anchor = $this->createTicketedWorkshop();
        $other = $this->createTicketedWorkshop();
        $special = $this->createTicketedWorkshop(['is_private' => true, 'private_code' => 'secret']);
        $this->begin($anchor);
        $before = session('ticket_checkout_flow.'.$anchor->id);
        $this->post(route('workshop.ticket.flow.join', $special))->assertRedirect(route('workshop.ticket.flow.start', $special));
        $this->assertSame($before, session('ticket_checkout_flow.'.$anchor->id));
        $this->travel(11)->minutes();
        $this->post(route('workshop.ticket.flow.join', $other))->assertRedirect(route('workshop.ticket.flow.start', $other));
        $this->assertSame(0, $other->tickets()->count());
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
