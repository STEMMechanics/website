<?php

namespace Tests\Feature;

use App\Models\SquareWebhookEvent;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

class SquareEventPresentationTest extends TestCase
{
    use RefreshDatabase;

    public function test_declined_payment_and_cvv_result_are_visible_without_opening_payload(): void
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
        $event = SquareWebhookEvent::factory()->create([
            'event_type' => 'payment.created',
            'payload' => ['data' => ['object' => ['payment' => [
                'id' => 'example-payment', 'status' => 'FAILED',
                'amount_money' => ['amount' => 2390, 'currency' => 'AUD'],
                'card_details' => ['cvv_status' => 'CVV_REJECTED', 'errors' => [
                    ['code' => 'GENERIC_DECLINE', 'detail' => "Authorization error: 'GENERIC_DECLINE'"],
                ]],
            ]]]],
        ]);
        $child = SquareWebhookEvent::factory()->create(['event_type' => 'payment.updated', 'payload' => $event->payload]);
        view()->share('errors', new ViewErrorBag);
        request()->setRouteResolver(fn () => app('router')->getRoutes()->getByName('admin.server.square-events')->bind(request()));
        // Render the listing directly: its grouping query uses MySQL JSON functions.
        $this->view('admin.server.square-events', [
            'groupedEvents' => collect([collect([$event, $child])]),
            'groupPage' => new LengthAwarePaginator([$event], 1, 20),
            'eventTypes' => ['payment.created'],
            'ignoreReasonOptions' => [],
            'errors' => new ViewErrorBag,
        ])->assertSee('Payment declined')->assertSee('CVV rejected')
            ->assertDontSee('GENERIC_DECLINE')->assertDontSee('Generic Decline')
            ->assertSee('1 related event')->assertSee('aria-expanded="false"', false)
            ->assertSee('x-show="!!expandedEvents['.$event->id.']"', false);
        $this->get(route('admin.server.square-events.show', $event))->assertOk()
            ->assertSee('Payment declined')->assertSee('CVV rejected')
            ->assertSee('Authorization error:')->assertSee('Copy payload')
            ->assertSee('x-ref="payload"', false);
    }

    public function test_payment_outcome_is_distinct_from_event_type_and_non_payment_events(): void
    {
        $event = new SquareWebhookEvent(['event_type' => 'payment.created', 'payload' => ['data' => ['object' => ['payment' => ['status' => 'COMPLETED']]]]]);
        $this->assertSame('Payment completed', $event->paymentOutcome()['label']);
        $this->assertFalse($event->paymentOutcome()['cvv_rejected']);
        $event->payload = ['data' => ['object' => ['payment' => ['status' => 'FAILED']]]];
        $this->assertSame('Payment failed', $event->paymentOutcome()['label']);
        $event->payload = ['data' => ['object' => ['refund' => ['status' => 'FAILED']]]];
        $this->assertNull($event->paymentOutcome());
    }
}
