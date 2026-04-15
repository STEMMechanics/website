<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Jobs\SendWorkshopTicketOrderEmail;
use App\Mail\TicketAttendeeUpdate;
use App\Mail\TicketOrderConfirmation;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Location;
use App\Models\Media;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\Workshop;
use App\Models\WorkshopTicketEmail;
use App\Services\SquareApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class WorkshopTicketEmailFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config(['security.altcha_enabled' => false]);
    }

    protected function tearDown(): void
    {
        Mockery::close();

        parent::tearDown();
    }

    public function test_paid_ticket_checkout_sends_the_combined_email_to_the_purchaser_and_the_ticket_email_to_a_different_holder(): void
    {
        Queue::fake();

        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('createPayment')->once()->with(Mockery::on(function (array $payload): bool {
            $idempotencyKey = (string) data_get($payload, 'idempotency_key', '');

            return (int) data_get($payload, 'amount_money.amount') === 1500
                && str_contains($idempotencyKey, '-amt-1500')
                && strlen($idempotencyKey) <= 45;
        }))->andReturn([
            'payment' => [
                'id' => 'sq-payment-1',
                'status' => 'COMPLETED',
                'reference_id' => 'payment:1',
                'order_id' => 'sq-order-1',
                'location_id' => 'L123',
                'receipt_url' => 'https://squareup.example/receipt',
                'amount_money' => ['amount' => 1500],
                'card_details' => [
                    'status' => 'CAPTURED',
                    'card' => [
                        'card_brand' => 'VISA',
                        'last_4' => '1111',
                    ],
                ],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('userFacingPaymentErrorMessage')->andReturnUsing(fn (string $message) => $message);
        $this->app->instance(SquareApiService::class, $squareApi);

        $workshop = $this->createTicketedWorkshop([
            'price' => '15.00',
        ]);

        $this->travelTo(now()->startOfMinute());

        $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1,
            'firstname' => 'Jamie',
            'surname' => 'Example',
            'email' => 'buyer@example.com',
            'phone' => '0400123456',
        ])->assertRedirect(route('workshop.ticket.flow.payment', $workshop));

        $this->post(route('workshop.ticket.flow.payment.process', $workshop), [
            'payment_method' => 'credit_card',
            'source_id' => 'cnon:card-nonce-ok',
        ])->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $ticket = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->sole();

        $this->post(route('workshop.ticket.flow.details.save', $workshop), [
            'tickets' => [
                [
                    'id' => $ticket->id,
                    'firstname' => 'Ticket',
                    'surname' => 'Holder',
                    'email' => 'holder@example.com',
                    'phone' => '0400123456',
                ],
            ],
        ])->assertRedirect(route('workshop.ticket.flow.complete', $workshop));

        $delivery = WorkshopTicketEmail::query()->sole();
        $this->assertSame(WorkshopTicketEmail::STATUS_QUEUED, $delivery->status);
        $this->assertNotNull($delivery->queued_at);
        $this->assertSame('buyer@example.com', $delivery->recipient_email);

        Queue::assertPushed(SendWorkshopTicketOrderEmail::class, function (SendWorkshopTicketOrderEmail $job) use ($delivery): bool {
            return $job->workshopTicketEmailId === $delivery->id;
        });

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            $mailable = $job->mailable;
            if (! $mailable instanceof TicketOrderConfirmation) {
                return false;
            }

            /** @var TicketOrderConfirmation $mailable */
            $mailable->build();

            return $job->to === 'buyer@example.com'
                && $mailable->hasInvoiceAttachment
                && $mailable->hasReceiptAttachment
                && $mailable->ticketAttachmentCount === 1
                && $this->mailableSubject($mailable) === 'Your ticket and receipt for Ticket Email Workshop';
        });

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            $mailable = $job->mailable;
            if (! $mailable instanceof TicketAttendeeUpdate) {
                return false;
            }

            /** @var TicketAttendeeUpdate $mailable */
            $mailable->build();

            return $job->to === 'holder@example.com'
                && $mailable->mode === 'new_holder'
                && $mailable->recipientName === 'Ticket Holder'
                && $mailable->purchaserName === 'Jamie Example'
                && $this->mailableSubject($mailable) === "You're in! Your workshop ticket for Ticket Email Workshop";
        });
    }

    public function test_ticket_checkout_can_apply_a_voucher_and_persists_the_discount_in_the_invoice(): void
    {
        Queue::fake();

        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('createPayment')->once()->with(Mockery::on(function (array $payload): bool {
            $idempotencyKey = (string) data_get($payload, 'idempotency_key', '');

            return (int) data_get($payload, 'amount_money.amount') === 1000
                && str_contains($idempotencyKey, '-amt-1000')
                && strlen($idempotencyKey) <= 45;
        }))->andReturn([
            'payment' => [
                'id' => 'sq-payment-2',
                'status' => 'COMPLETED',
                'reference_id' => 'payment:2',
                'order_id' => 'sq-order-2',
                'location_id' => 'L123',
                'receipt_url' => 'https://squareup.example/receipt',
                'amount_money' => ['amount' => 1000],
                'card_details' => [
                    'status' => 'CAPTURED',
                    'card' => [
                        'card_brand' => 'VISA',
                        'last_4' => '1111',
                    ],
                ],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('userFacingPaymentErrorMessage')->andReturnUsing(fn (string $message) => $message);
        $this->app->instance(SquareApiService::class, $squareApi);

        Coupon::factory()->create([
            'code' => 'SAVE5',
            'description' => 'Ticket voucher',
            'status' => Coupon::STATUS_ACTIVE,
            'discount_type' => Coupon::DISCOUNT_TYPE_FIXED_AMOUNT,
            'amount' => 5.00,
        ]);

        $workshop = $this->createTicketedWorkshop([
            'price' => '15.00',
        ]);

        $this->travelTo(now()->startOfMinute());

        $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1,
            'firstname' => 'Jamie',
            'surname' => 'Example',
            'email' => 'buyer@example.com',
            'phone' => '0400123456',
        ])->assertRedirect(route('workshop.ticket.flow.payment', $workshop));

        $invalidVoucherResponse = $this->postJson(route('workshop.ticket.flow.voucher', $workshop), [
            'voucher_code' => 'NOPE',
        ]);
        $invalidVoucherResponse
            ->assertStatus(422)
            ->assertJsonValidationErrors('voucher_code');

        $validVoucherResponse = $this->postJson(route('workshop.ticket.flow.voucher', $workshop), [
            'voucher_code' => 'SAVE5',
        ]);
        $validVoucherResponse
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('summary.voucher_code', 'SAVE5')
            ->assertJsonPath('summary.voucher_discount_amount', 5);

        $this->get(route('workshop.ticket.flow.payment', $workshop))
            ->assertOk()
            ->assertSee('$-5.00 (SAVE5)', false)
            ->assertSee('Change voucher', false);

        $this->post(route('workshop.ticket.flow.payment.process', $workshop), [
            'payment_method' => 'credit_card',
            'source_id' => 'cnon:card-nonce-ok',
        ])->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $ticket = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->sole();

        $this->post(route('workshop.ticket.flow.details.save', $workshop), [
            'tickets' => [
                [
                    'id' => $ticket->id,
                    'firstname' => 'Ticket',
                    'surname' => 'Holder',
                    'email' => 'holder@example.com',
                    'phone' => '0400123456',
                ],
            ],
        ])->assertRedirect(route('workshop.ticket.flow.complete', $workshop));

        $invoice = Invoice::query()->sole();
        $discountLine = InvoiceLine::query()
            ->where('invoice_id', $invoice->id)
            ->where('kind', 'discount')
            ->sole();
        $cardPayment = Payment::query()
            ->where('payment_method', Payment::PAYMENT_METHOD_CREDIT_CARD)
            ->sole();

        $this->assertSame(10.00, (float) $invoice->total_amount);
        $this->assertSame('Voucher SAVE5', (string) $discountLine->description);
        $this->assertSame(-5.00, (float) $discountLine->line_total_inc_tax);
        $this->assertSame(10.00, (float) $cardPayment->total_amount);
    }

    public function test_account_terms_ticket_checkout_uses_the_users_terms_for_the_invoice_due_date(): void
    {
        $this->travelTo(now()->setDate(2026, 4, 1)->setTime(10, 0));

        $buyer = User::factory()->create([
            'firstname' => 'Terms',
            'surname' => 'Buyer',
            'email' => 'terms-buyer@example.com',
            'account_terms_days' => 14,
        ]);

        $workshop = $this->createTicketedWorkshop([
            'price' => '15.00',
        ]);

        $this->actingAs($buyer);

        $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1,
            'firstname' => 'Terms',
            'surname' => 'Buyer',
            'email' => 'terms-buyer@example.com',
            'phone' => '0400123456',
        ])->assertRedirect(route('workshop.ticket.flow.payment', $workshop));

        $this->post(route('workshop.ticket.flow.payment.process', $workshop), [
            'payment_method' => 'account_terms',
        ])->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $ticket = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->sole();
        $invoice = Invoice::query()->sole();

        $this->assertSame(Ticket::STATUS_ACCOUNT, (int) $ticket->status);
        $this->assertSame('2026-04-15', optional($invoice->due_date)->toDateString());
    }

    public function test_logged_in_ticket_checkout_uses_account_credit_before_charging_the_remaining_card_amount(): void
    {
        Queue::fake();

        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $buyer = User::factory()->create([
            'firstname' => 'Jamie',
            'surname' => 'Example',
            'email' => 'buyer-credit@example.com',
        ]);

        Payment::factory()->create([
            'user_id' => $buyer->id,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT,
            'total_amount' => 5.00,
            'gst_amount' => 0,
            'reference' => 'Account credit grant',
        ]);

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('createPayment')->once()->with(Mockery::on(function (array $payload): bool {
            $idempotencyKey = (string) data_get($payload, 'idempotency_key', '');

            return (int) data_get($payload, 'amount_money.amount') === 1000
                && str_contains($idempotencyKey, '-amt-1000')
                && strlen($idempotencyKey) <= 45;
        }))->andReturn([
            'payment' => [
                'id' => 'sq-payment-1',
                'status' => 'COMPLETED',
                'reference_id' => 'payment:1',
                'order_id' => 'sq-order-1',
                'location_id' => 'L123',
                'receipt_url' => 'https://squareup.example/receipt',
                'amount_money' => ['amount' => 1000],
                'card_details' => [
                    'status' => 'CAPTURED',
                    'card' => [
                        'card_brand' => 'VISA',
                        'last_4' => '1111',
                    ],
                ],
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
        /** @phpstan-ignore-next-line */
        $squareApi->shouldReceive('userFacingPaymentErrorMessage')->andReturnUsing(fn (string $message) => $message);
        $this->app->instance(SquareApiService::class, $squareApi);

        $workshop = $this->createTicketedWorkshop([
            'price' => '15.00',
        ]);

        $this->actingAs($buyer);
        $this->travelTo(now()->startOfMinute());

        $this->post(route('workshop.ticket.flow.begin', $workshop), [
            'quantity' => 1,
            'firstname' => 'Jamie',
            'surname' => 'Example',
            'email' => 'buyer-credit@example.com',
            'phone' => '0400123456',
        ])->assertRedirect(route('workshop.ticket.flow.payment', $workshop));

        $this->get(route('workshop.ticket.flow.payment', $workshop))
            ->assertOk()
            ->assertSee('Apply account credit first', false)
            ->assertSee('Available credit:', false)
            ->assertSee('Remaining after credit:', false);

        $this->post(route('workshop.ticket.flow.payment.process', $workshop), [
            'payment_method' => 'credit_card',
            'source_id' => 'cnon:card-nonce-ok',
            'apply_account_credit' => '1',
        ])->assertRedirect(route('workshop.ticket.flow.details', $workshop));

        $ticket = Ticket::query()
            ->where('workshop_id', $workshop->id)
            ->sole();

        $this->post(route('workshop.ticket.flow.details.save', $workshop), [
            'tickets' => [
                [
                    'id' => $ticket->id,
                    'firstname' => 'Jamie',
                    'surname' => 'Example',
                    'email' => 'buyer-credit@example.com',
                    'phone' => '0400123456',
                ],
            ],
        ])->assertRedirect(route('workshop.ticket.flow.complete', $workshop));

        $creditPayment = Payment::query()
            ->where('user_id', $buyer->id)
            ->where('payment_method', Payment::PAYMENT_METHOD_CREDIT)
            ->sole();
        $cardPayment = Payment::query()
            ->where('payment_method', Payment::PAYMENT_METHOD_CREDIT_CARD)
            ->sole();

        $this->assertSame(5.00, (float) InvoicePaymentAllocation::query()->where('payment_id', $creditPayment->id)->sum('allocated_amount'));
        $this->assertSame(10.00, (float) $cardPayment->total_amount);
        $this->assertSame(10.00, (float) InvoicePaymentAllocation::query()->where('payment_id', $cardPayment->id)->sum('allocated_amount'));

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            $mailable = $job->mailable;
            if (! $mailable instanceof TicketOrderConfirmation) {
                return false;
            }

            $mailable->build();

            return $job->to === 'buyer-credit@example.com'
                && $mailable->hasInvoiceAttachment
                && $mailable->hasReceiptAttachment
                && $mailable->hasCreditReceiptAttachment
                && $mailable->receiptAttachmentCount === 1
                && $mailable->creditReceiptAttachmentCount === 1
                && $mailable->ticketAttachmentCount === 1
                && $mailable->paymentMethodLabel === 'Account Credit + Credit Card'
                && $mailable->creditAppliedAmount === 5.00
                && $mailable->paymentAmount === 10.00
                && is_string($mailable->creditReferenceSummary)
                && $mailable->creditReferenceSummary !== ''
                && $this->mailableSubject($mailable) === 'Your ticket and receipts for Ticket Email Workshop';
        });
    }

    public function test_missing_ticket_details_session_redirects_back_to_the_workshop_page_with_a_toast_message(): void
    {
        $workshop = $this->createTicketedWorkshop();

        $response = $this->post(route('workshop.ticket.flow.details.save', $workshop), []);

        $response->assertRedirect(route('workshop.show', $workshop));
        $response->assertSessionHas('message-title', 'Session expired');
        $response->assertSessionHas('message-type', 'warning');
        $response->assertSessionHas(
            'message',
            'Your checkout session expired while this page was open. Reload this page or restart checkout before saving ticket details.'
        );
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
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

    private function mailableSubject(object $mailable): string
    {
        $reflection = new \ReflectionClass($mailable);
        $property = $reflection->getProperty('subject');
        $property->setAccessible(true);

        return (string) $property->getValue($mailable);
    }
}
