<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\InvoiceDocumentBundle;
use App\Mail\PaymentReceiptPdf;
use App\Mail\TicketAttendeeUpdate;
use App\Mail\TicketCancelledNotice;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Mail\WorkshopTicketBroadcast;
use App\Models\Location;
use App\Models\Media;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use App\Services\SquareApiService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Mockery;
use ReflectionClass;
use Tests\TestCase;

class AdminWorkshopTicketEmailTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_bulk_ticket_email_dedupes_recipients_and_queues_single_job(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $holder = User::factory()->create(['email' => 'parent@example.com']);
        $linkedUser = User::factory()->create(['email' => 'guardian@example.com']);
        $workshop = $this->createTicketWorkshop();

        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $holder->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'parent@example.com',
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => null,
            'status' => Ticket::STATUS_PENDING_DOOR,
            'email' => 'other@example.com',
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $linkedUser->id,
            'status' => Ticket::STATUS_CANCELLED,
            'email' => '',
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => null,
            'status' => Ticket::STATUS_HOLD,
            'email' => 'hold@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.workshop.tickets', $workshop))
            ->post(route('admin.workshop.tickets.email', $workshop), [
                'email_subject' => 'Workshop update',
                'email_message' => "Line 1\nLine 2",
            ]);

        $response->assertRedirect(route('admin.workshop.tickets', $workshop));
        $response->assertSessionHas('message', 'Email sent to 3 recipients.');

        Queue::assertPushed(SendEmail::class, 1);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($admin) {
            $this->assertSame((string) $admin->email, (string) $job->to);
            $this->assertInstanceOf(WorkshopTicketBroadcast::class, $job->mailable);

            $recipients = $this->extractPrivateArrayProperty($job->mailable, 'bccRecipients');
            sort($recipients);
            $this->assertSame([
                'guardian@example.com',
                'other@example.com',
                'parent@example.com',
            ], $recipients);

            return true;
        });
    }

    public function test_bulk_ticket_email_warns_when_no_recipients_found(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $workshop = $this->createTicketWorkshop();

        $response = $this->actingAs($admin)
            ->from(route('admin.workshop.tickets', $workshop))
            ->post(route('admin.workshop.tickets.email', $workshop), [
                'email_subject' => 'Workshop update',
                'email_message' => 'Hello ticket holders',
            ]);

        $response->assertRedirect(route('admin.workshop.tickets', $workshop));
        $response->assertSessionHas('message-title', 'No recipients');
        Queue::assertNothingPushed();
    }

    public function test_bulk_ticket_email_falls_back_to_mail_from_when_admin_email_invalid(): void
    {
        Queue::fake();
        config(['mail.from.address' => 'fallback@example.com']);

        $admin = $this->createAdminUser();
        $admin->forceFill(['email' => ''])->saveQuietly();

        $workshop = $this->createTicketWorkshop();
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'recipient@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.workshop.tickets', $workshop))
            ->post(route('admin.workshop.tickets.email', $workshop), [
                'email_subject' => 'Workshop update',
                'email_message' => 'Hello',
            ]);

        $response->assertRedirect(route('admin.workshop.tickets', $workshop));
        Queue::assertPushed(SendEmail::class, fn (SendEmail $job) => $job->to === 'fallback@example.com');
    }

    public function test_admin_single_ticket_cancel_emails_customer_by_default(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $customer = User::factory()->create(['email' => 'cancelme@example.com']);
        $workshop = $this->createTicketWorkshop();
        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $customer->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'cancelme@example.com',
            'firstname' => 'Cancel',
            'surname' => 'Me',
            'invoice_id' => null,
            'invoice_line_id' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.ticket.cancel', $ticket), [
                'process_square_refund' => 0,
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $ticket->status);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'cancelme@example.com'
                && $job->mailable instanceof TicketCancelledNotice;
        });
    }

    public function test_admin_single_ticket_cancel_uses_custom_reason_in_email(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $customer = User::factory()->create(['email' => 'reason@example.com']);
        $workshop = $this->createTicketWorkshop();
        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $customer->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'reason@example.com',
            'firstname' => 'Reason',
            'surname' => 'Case',
            'invoice_id' => null,
            'invoice_line_id' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.ticket.cancel', $ticket), [
                'process_square_refund' => 0,
                'reason' => 'The workshop has been rescheduled.',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $ticket->status);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'reason@example.com'
                && $job->mailable instanceof TicketCancelledNotice
                && $job->mailable->introLine === 'The workshop has been rescheduled.';
        });
    }

    public function test_admin_single_ticket_cancel_uses_manual_copy_for_paid_invoice(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $customer = User::factory()->create(['email' => 'paid@example.com']);
        $workshop = $this->createTicketWorkshop();
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_name' => 'Paid Case',
            'billing_email' => 'paid@example.com',
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 25.00,
            'subtotal_amount' => 22.73,
            'gst_amount' => 2.27,
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'kind' => Payment::KIND_PAYMENT,
            'total_amount' => 25.00,
        ]);
        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 25.00,
        ]);

        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $customer->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'paid@example.com',
            'firstname' => 'Paid',
            'surname' => 'Case',
            'invoice_id' => $invoice->id,
            'invoice_line_id' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.ticket.cancel', $ticket), [
                'process_square_refund' => 0,
                'reason' => 'The workshop has been cancelled.',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $ticket->status);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'paid@example.com'
                && $job->mailable instanceof TicketCancelledNotice
                && str_contains($job->mailable->financialSummary, 'Credit will be applied to your account or a refund for the purchase will be processed manually.')
                && str_contains($job->mailable->documentSummary, 'will be sent once processed.')
                && count($this->extractPrivateArrayProperty($job->mailable, 'attachmentsPayload')) === 0;
        });
        Queue::assertNotPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->mailable instanceof InvoiceDocumentBundle;
        });
    }

    public function test_admin_single_ticket_cancel_emails_the_holder_separately_when_holder_email_differs(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $purchaser = User::factory()->create(['email' => 'purchaser@example.com']);
        $workshop = $this->createTicketWorkshop();
        $invoice = Invoice::factory()->create([
            'user_id' => $purchaser->id,
            'billing_name' => 'Purchaser Example',
            'billing_email' => 'purchaser@example.com',
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 25.00,
            'subtotal_amount' => 22.73,
            'gst_amount' => 2.27,
        ]);

        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $purchaser->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'holder@example.com',
            'firstname' => 'Holder',
            'surname' => 'Example',
            'invoice_id' => $invoice->id,
            'invoice_line_id' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.ticket.cancel', $ticket), [
                'process_square_refund' => 0,
                'reason' => 'The workshop has been cancelled.',
            ])
            ->assertRedirect();

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'purchaser@example.com'
                && $job->mailable instanceof TicketCancelledNotice;
        });

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'holder@example.com'
                && $job->mailable instanceof TicketAttendeeUpdate
                && $job->mailable->mode === 'cancelled';
        });
    }

    public function test_admin_single_ticket_cancel_combines_refund_documents_into_same_email_when_square_refund_succeeds(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $customer = User::factory()->create(['email' => 'square-paid@example.com']);
        $workshop = $this->createTicketWorkshop();
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_name' => 'Square Paid',
            'billing_email' => 'square-paid@example.com',
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 25.00,
            'subtotal_amount' => 22.73,
            'gst_amount' => 2.27,
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'gateway_provider' => 'square',
            'square_payment_id' => 'sq-test-payment',
            'square_paid_money_amount' => 2500,
            'square_refunded_money_amount' => 0,
            'total_amount' => 25.00,
        ]);
        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 25.00,
        ]);

        $squareApi = new class extends SquareApiService {
            public int $createRefundCalls = 0;

            public function isEnabled(): bool
            {
                return true;
            }

            public function createRefund(array $payload): array
            {
                $this->createRefundCalls++;

                return [
                    'refund' => [
                        'id' => 'sq-refund-1',
                        'status' => 'COMPLETED',
                        'amount_money' => ['amount' => 2500],
                    ],
                ];
            }
        };
        $this->app->instance(SquareApiService::class, $squareApi);

        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $customer->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'square-paid@example.com',
            'firstname' => 'Square',
            'surname' => 'Paid',
            'invoice_id' => $invoice->id,
            'invoice_line_id' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.ticket.cancel', $ticket), [
                'process_square_refund' => 1,
                'reason' => 'The workshop has been cancelled.',
            ])
            ->assertRedirect();

        $this->assertSame(1, $squareApi->createRefundCalls);
        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $ticket->status);

        $refundPayment = Payment::query()
            ->where('refund_of_payment_id', $payment->id)
            ->where('kind', Payment::KIND_REFUND)
            ->first();
        $this->assertNotNull($refundPayment);

        Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job): bool => $job->mailable instanceof PaymentReceiptPdf);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($refundPayment): bool {
            if (! $job->mailable instanceof TicketCancelledNotice) {
                return false;
            }

            $attachments = $this->extractPrivateArrayProperty($job->mailable, 'attachmentsPayload');
            $filenames = array_values(array_filter(array_map(
                fn (array $attachment): string => (string) ($attachment['filename'] ?? ''),
                $attachments
            )));

            $this->assertStringContainsString('has been processed automatically', $job->mailable->financialSummary);
            $this->assertStringContainsString('refund receipt documents are attached', $job->mailable->documentSummary);
            $this->assertContains('refund-receipt-'.((int) $refundPayment->id).'.pdf', $filenames);
            $this->assertGreaterThanOrEqual(3, count($attachments));

            return true;
        });
    }

    public function test_admin_single_ticket_cancel_uses_unpaid_invoice_copy_for_pay_at_door_ticket(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $customer = User::factory()->create(['email' => 'payatdoor@example.com']);
        $workshop = $this->createTicketWorkshop();
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_name' => 'Door Case',
            'billing_email' => 'payatdoor@example.com',
            'status' => Invoice::STATUS_SENT,
            'total_amount' => 25.00,
            'subtotal_amount' => 22.73,
            'gst_amount' => 2.27,
        ]);
        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $customer->id,
            'status' => Ticket::STATUS_PENDING_DOOR,
            'email' => 'payatdoor@example.com',
            'firstname' => 'Door',
            'surname' => 'Case',
            'invoice_id' => $invoice->id,
            'invoice_line_id' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.ticket.cancel', $ticket), [
                'process_square_refund' => 0,
                'reason' => 'The workshop has been cancelled.',
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $ticket->status);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'payatdoor@example.com'
                && $job->mailable instanceof TicketCancelledNotice
                && str_contains($job->mailable->financialSummary, 'Any unpaid invoices related to this ticket will be cancelled.')
                && str_contains($job->mailable->documentSummary, 'are attached to this email.')
                && count($this->extractPrivateArrayProperty($job->mailable, 'attachmentsPayload')) > 0;
        });
    }

    public function test_admin_single_ticket_cancel_can_skip_customer_email(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $customer = User::factory()->create(['email' => 'no-cancel-mail@example.com']);
        $workshop = $this->createTicketWorkshop();
        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $customer->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'no-cancel-mail@example.com',
            'firstname' => 'No',
            'surname' => 'Mail',
            'invoice_id' => null,
            'invoice_line_id' => null,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.ticket.cancel', $ticket), [
                'process_square_refund' => 0,
                'email_customer' => 0,
            ])
            ->assertRedirect();

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $ticket->status);
        Queue::assertNotPushed(SendEmail::class);
    }

    public function test_admin_workshop_ticket_page_shows_single_cancel_action_with_square_refund_checkbox_for_square_paid_ticket(): void
    {
        $admin = $this->createAdminUser();
        $customer = User::factory()->create(['email' => 'square@example.com']);
        $workshop = $this->createTicketWorkshop();
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_name' => 'Square Case',
            'billing_email' => 'square@example.com',
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 25.00,
            'subtotal_amount' => 22.73,
            'gst_amount' => 2.27,
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'gateway_provider' => 'square',
            'square_payment_id' => 'sq-test-payment',
            'square_paid_money_amount' => 2500,
            'square_refunded_money_amount' => 0,
            'total_amount' => 25.00,
        ]);
        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 25.00,
        ]);

        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $customer->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'square@example.com',
            'firstname' => 'Square',
            'surname' => 'Case',
            'invoice_id' => $invoice->id,
            'invoice_line_id' => null,
        ]);

        $response = $this->actingAs($admin)->get(route('admin.workshop.tickets', $workshop));

        $response->assertOk();
        $response->assertSee('Process Square refund');
        $response->assertDontSee('Refund now');
        $response->assertSee($ticket->reference_code);
    }

    public function test_workshop_update_can_queue_change_email_for_active_ticket_holders(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $currentLocation = Location::factory()->create(['name' => 'Old Hall']);
        $newLocation = Location::factory()->create(['name' => 'New Lab']);
        $workshop = $this->createTicketWorkshop($currentLocation);
        $linkedUser = User::factory()->create(['email' => 'linked@example.com']);

        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'user_id' => null,
            'email' => 'holder@example.com',
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PENDING_XFER,
            'user_id' => $linkedUser->id,
            'email' => '',
        ]);
        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_CANCELLED,
            'user_id' => null,
            'email' => 'cancelled@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), $this->workshopUpdatePayload($workshop, $newLocation, [
                'starts_at' => $workshop->starts_at?->copy()->addDay()->toDateTimeString(),
                'ends_at' => $workshop->ends_at?->copy()->addDay()->toDateTimeString(),
                'notify_ticket_holders' => '1',
                'ticket_change_email_notes' => "Please use the new entrance.\nParking has changed.",
            ]));

        $response->assertRedirect(route('admin.workshop.index'));
        $response->assertSessionHas('message', 'Workshop has been updated and an email was queued to 2 ticket holders.');

        Queue::assertPushed(SendEmail::class, 1);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($admin): bool {
            $this->assertSame((string) $admin->email, (string) $job->to);
            $this->assertInstanceOf(WorkshopTicketBroadcast::class, $job->mailable);

            $recipients = $this->extractPrivateArrayProperty($job->mailable, 'bccRecipients');
            sort($recipients);
            $this->assertSame([
                'holder@example.com',
                'linked@example.com',
            ], $recipients);

            $rendered = html_entity_decode(strip_tags($job->mailable->render()));
            $this->assertStringContainsString('The details for "Workshop Tickets" have changed.', $rendered);
            $this->assertStringContainsString('Updated details:', $rendered);
            $this->assertStringContainsString('Location: New Lab', $rendered);
            $this->assertStringContainsString('Previous details:', $rendered);
            $this->assertStringContainsString('Location: Old Hall', $rendered);
            $this->assertStringContainsString('Please use the new entrance.', $rendered);
            $this->assertStringContainsString('Parking has changed.', $rendered);

            return true;
        });
    }

    public function test_workshop_update_can_skip_change_email_when_admin_chooses_not_to_send(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $location = Location::factory()->create();
        $workshop = $this->createTicketWorkshop($location);

        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'user_id' => null,
            'email' => 'holder@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), $this->workshopUpdatePayload($workshop, $location, [
                'starts_at' => $workshop->starts_at?->copy()->addHours(3)->toDateTimeString(),
                'ends_at' => $workshop->ends_at?->copy()->addHours(3)->toDateTimeString(),
                'notify_ticket_holders' => '0',
                'ticket_change_email_notes' => 'No email should be sent.',
            ]));

        $response->assertRedirect(route('admin.workshop.index'));
        $response->assertSessionHas('message', 'Workshop has been updated.');
        Queue::assertNotPushed(SendEmail::class);
    }

    public function test_workshop_update_does_not_email_when_date_time_and_location_are_unchanged(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $location = Location::factory()->create();
        $workshop = $this->createTicketWorkshop($location);

        Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'status' => Ticket::STATUS_PAID,
            'user_id' => null,
            'email' => 'holder@example.com',
        ]);

        $response = $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), $this->workshopUpdatePayload($workshop, $location, [
                'title' => 'Workshop Tickets Updated Title',
                'notify_ticket_holders' => '1',
                'ticket_change_email_notes' => 'This should be ignored.',
            ]));

        $response->assertRedirect(route('admin.workshop.index'));
        $response->assertSessionHas('message', 'Workshop has been updated.');
        Queue::assertNotPushed(SendEmail::class);
    }

    public function test_workshop_cancellation_prompts_reason_and_cancels_active_tickets_with_square_refund(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $location = Location::factory()->create();
        $workshop = $this->createTicketWorkshop($location);
        $customer = User::factory()->create(['email' => 'cancelled-holder@example.com']);
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_name' => 'Cancelled Holder',
            'billing_email' => 'cancelled-holder@example.com',
            'status' => Invoice::STATUS_PAID,
            'total_amount' => 25.00,
            'subtotal_amount' => 22.73,
            'gst_amount' => 2.27,
        ]);
        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 25.00,
            'gateway_provider' => 'square',
            'square_payment_id' => 'sq-ticket-789',
            'square_paid_money_amount' => 2500,
            'square_refunded_money_amount' => 0,
        ]);
        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 25.00,
        ]);

        $ticket = Ticket::factory()->create([
            'workshop_id' => $workshop->id,
            'user_id' => $customer->id,
            'status' => Ticket::STATUS_PAID,
            'email' => 'cancelled-holder@example.com',
            'firstname' => 'Cancel',
            'surname' => 'Workshop',
            'invoice_id' => $invoice->id,
            'invoice_line_id' => null,
        ]);

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        $squareApi->shouldReceive('createRefund')->andReturn([
            'refund' => [
                'id' => 'refund-123',
                'amount_money' => ['amount' => 2500],
                'status' => 'COMPLETED',
                'created_at' => now()->toIso8601String(),
                'updated_at' => now()->toIso8601String(),
            ],
        ]);
        $this->app->instance(SquareApiService::class, $squareApi);

        $response = $this->actingAs($admin)
            ->put(route('admin.workshop.update', $workshop), $this->workshopUpdatePayload($workshop, $location, [
                'status' => 'cancelled',
                'workshop_cancel_reason' => 'The workshop has been cancelled.',
            ]));

        $response->assertRedirect(route('admin.workshop.index'));
        $response->assertSessionHas('message');

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $ticket->status);
        $this->assertDatabaseHas('payments', [
            'refund_of_payment_id' => $payment->id,
            'gateway_provider' => 'square',
            'gateway_reference_id' => 'refund-123',
        ]);

        Queue::assertPushed(SendEmail::class, fn (SendEmail $job) => $job->mailable instanceof TicketCancelledNotice);
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

    private function createTicketWorkshop(?Location $location = null): Workshop
    {
        $owner = User::factory()->create();
        $location ??= Location::factory()->create();
        $heroName = 'hero-'.Str::lower(Str::random(8)).'.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('b', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);

        return Workshop::query()->create([
            'title' => 'Workshop Tickets',
            'content' => '<p>Workshop content</p>',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addDays(2),
            'status' => 'open',
            'registration' => 'tickets',
            'max_tickets' => 24,
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function workshopUpdatePayload(Workshop $workshop, Location $location, array $overrides = []): array
    {
        return array_merge([
            'title' => (string) $workshop->title,
            'content' => (string) $workshop->content,
            'type' => 'physical',
            'location_id' => $location->id,
            'starts_at' => $workshop->starts_at?->toDateTimeString(),
            'ends_at' => $workshop->ends_at?->toDateTimeString(),
            'publish_at' => $workshop->publish_at?->toDateTimeString(),
            'closes_at' => $workshop->closes_at?->toDateTimeString(),
            'status' => (string) $workshop->status,
            'registration' => (string) $workshop->registration,
            'max_tickets' => $workshop->max_tickets,
            'hero_media_name' => (string) $workshop->hero_media_name,
        ], $overrides);
    }

    /**
     * @return array<int, string>
     */
    private function extractPrivateArrayProperty(object $object, string $property): array
    {
        $reflection = new ReflectionClass($object);
        $propertyReflection = $reflection->getProperty($property);
        $propertyReflection->setAccessible(true);
        $value = $propertyReflection->getValue($object);

        return is_array($value) ? array_values($value) : [];
    }
}
