<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\PaymentReceiptPdf;
use App\Mail\TicketCancelledNotice;
use App\Models\Invoice;
use App\Models\InvoicePaymentAllocation;
use App\Models\Location;
use App\Models\Media;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class WorkshopAttendanceKioskTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_non_ticketed_workshop_standard_kiosk_sign_in_redirects_back_to_blank_form(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('none');

        $this->actingAs($admin)
            ->get(route('admin.workshop.attendance', ['workshop' => $workshop, 'kiosk' => 1]))
            ->assertOk()
            ->assertSee('Sign-In Sheet')
            ->assertSee('Parent/Guardian Name');

        $response = $this->actingAs($admin)
            ->post(route('admin.workshop.attendance.dropin.store', $workshop), [
                'kiosk' => 1,
                'submit_action' => 'save',
                'child_name' => 'Ada Lovelace',
                'guardian_name' => 'Mary Lovelace',
                'email' => 'mary@example.com',
                'phone' => '0400000000',
                'media_consent' => 1,
            ]);

        $response->assertRedirect(route('admin.workshop.attendance', ['workshop' => $workshop, 'kiosk' => 1]));
        $response->assertSessionMissing('_old_input');

        $this->assertDatabaseHas('workshop_attendances', [
            'workshop_id' => $workshop->id,
            'child_name' => 'Ada Lovelace',
            'guardian_name' => 'Mary Lovelace',
            'email' => 'mary@example.com',
            'phone' => '0400000000',
            'media_consent' => 1,
            'source' => 'dropin',
        ]);
    }

    public function test_non_ticketed_workshop_can_sign_in_and_prefill_parent_details_for_another_child(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('none');

        $response = $this->actingAs($admin)
            ->post(route('admin.workshop.attendance.dropin.store', $workshop), [
                'kiosk' => 1,
                'submit_action' => 'save_and_add_another',
                'child_name' => 'Ada Lovelace',
                'guardian_name' => 'Mary Lovelace',
                'email' => 'mary@example.com',
                'phone' => '0400000000',
                'media_consent' => 1,
            ]);

        $response->assertRedirect(route('admin.workshop.attendance', ['workshop' => $workshop, 'kiosk' => 1]));
        $response->assertSessionHasInput('child_name', '');
        $response->assertSessionHasInput('guardian_name', 'Mary Lovelace');
        $response->assertSessionHasInput('email', 'mary@example.com');
        $response->assertSessionHasInput('phone', '0400000000');
        $response->assertSessionHasInput('media_consent', 1);

        $this->assertDatabaseHas('workshop_attendances', [
            'workshop_id' => $workshop->id,
            'child_name' => 'Ada Lovelace',
            'guardian_name' => 'Mary Lovelace',
            'email' => 'mary@example.com',
            'phone' => '0400000000',
            'media_consent' => 1,
            'source' => 'dropin',
        ]);
    }

    public function test_admin_can_bulk_update_attendance_entries_from_table_editor(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('none');

        $entry = \App\Models\WorkshopAttendance::query()->create([
            'workshop_id' => $workshop->id,
            'source' => 'dropin',
            'child_name' => 'Old Name',
            'guardian_name' => 'Old Guardian',
            'email' => 'old@example.com',
            'phone' => '0411111111',
            'media_consent' => false,
            'attended_at' => now(),
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.attendance', ['workshop' => $workshop]))
            ->assertOk()
            ->assertSee('Attendance Records');

        $response = $this->actingAs($admin)
            ->post(route('admin.workshop.attendance.dropin.sync', ['workshop' => $workshop]), [
                'entries' => [
                    [
                        'id' => $entry->id,
                        'child_name' => 'New Name',
                        'guardian_name' => 'New Guardian',
                        'email' => 'new@example.com',
                        'phone' => '0422222222',
                        'media_consent' => 1,
                    ],
                ],
            ]);

        $response->assertRedirect(route('admin.workshop.attendance', $workshop));

        $this->assertDatabaseHas('workshop_attendances', [
            'id' => $entry->id,
            'child_name' => 'New Name',
            'guardian_name' => 'New Guardian',
            'email' => 'new@example.com',
            'phone' => '0422222222',
            'media_consent' => 1,
        ]);
    }

    public function test_admin_can_export_attendance_as_csv(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('none');

        \App\Models\WorkshopAttendance::query()->create([
            'workshop_id' => $workshop->id,
            'source' => 'dropin',
            'child_name' => 'Taylor Example',
            'guardian_name' => 'Jordan Example',
            'email' => 'jordan@example.com',
            'phone' => '0400999888',
            'media_consent' => true,
            'attended_at' => now(),
            'created_by' => $admin->id,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.workshop.attendance.csv', $workshop));

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $content = $response->streamedContent();
        $this->assertStringContainsString('Taylor Example', $content);
        $this->assertStringContainsString('Jordan Example', $content);
    }

    public function test_ticketed_attendance_page_renders_payment_controls(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ATTEND-1002',
            'user_id' => $customer->id,
            'billing_name' => 'Ticketed Family',
            'billing_email' => 'ticketed@example.com',
            'billing_phone' => '0400111222',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => 13.64,
            'gst_amount' => 1.36,
            'total_amount' => 15.00,
            'notes' => null,
        ]);

        Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Ticketed',
            'surname' => 'Student',
            'email' => 'ticketed@example.com',
            'phone' => '0400111222',
            'attended_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.attendance', $workshop))
            ->assertOk()
            ->assertDontSee('Record Payment for Checked')
            ->assertSee('Cancel Ticket')
            ->assertSee('admin\\/tickets\\/cancel\\/bulk', false)
            ->assertSee('Record Ticket Payment');
    }

    public function test_ticketed_attendance_can_optionally_show_cancelled_tickets(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();

        Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'firstname' => 'Active',
            'surname' => 'Attendee',
            'email' => 'active@example.com',
            'phone' => '0400123000',
            'attended_at' => null,
        ]);

        Ticket::query()->create([
            'status' => Ticket::STATUS_CANCELLED,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'firstname' => 'Cancelled',
            'surname' => 'Attendee',
            'email' => 'cancelled@example.com',
            'phone' => '0400123999',
            'attended_at' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.attendance', $workshop))
            ->assertOk()
            ->assertSee('Show cancelled tickets')
            ->assertSee('Active Attendee')
            ->assertDontSee('Cancelled Attendee')
            ->assertDontSee('Cancelled Tickets');

        $this->actingAs($admin)
            ->get(route('admin.workshop.attendance', ['workshop' => $workshop, 'show_cancelled' => 1]))
            ->assertOk()
            ->assertSee('Cancelled Attendee');
    }

    public function test_admin_can_bulk_cancel_tickets_from_attendance(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();
        Queue::fake();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ATTEND-1006',
            'user_id' => $customer->id,
            'billing_name' => 'Bulk Cancel Family',
            'billing_email' => 'bulkcancel@example.com',
            'billing_phone' => '0400111555',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => 27.27,
            'gst_amount' => 2.73,
            'total_amount' => 30.00,
            'notes' => null,
        ]);

        $firstTicket = Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Bulk',
            'surname' => 'One',
            'email' => 'bulkcancel@example.com',
            'phone' => '0400111555',
            'attended_at' => null,
        ]);
        $secondTicket = Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Bulk',
            'surname' => 'Two',
            'email' => 'bulkcancel@example.com',
            'phone' => '0400111555',
            'attended_at' => null,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ticket.cancel.bulk'), [
                'ticket_ids' => [$firstTicket->id, $secondTicket->id],
                'process_square_refund' => 1,
            ])
            ->assertOk()
            ->assertJsonPath('cancelled_count', 2)
            ->assertJsonPath('failed_count', 0);

        $firstTicket->refresh();
        $secondTicket->refresh();
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $firstTicket->status);
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $secondTicket->status);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'bulkcancel@example.com'
                && $job->mailable instanceof TicketCancelledNotice;
        });
    }

    public function test_admin_can_bulk_cancel_tickets_from_attendance_without_emailing_customer(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();
        Queue::fake();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ATTEND-1006B',
            'user_id' => $customer->id,
            'billing_name' => 'Bulk Cancel Family',
            'billing_email' => 'bulkcancel@example.com',
            'billing_phone' => '0400111555',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => 13.64,
            'gst_amount' => 1.36,
            'total_amount' => 15.00,
            'notes' => null,
        ]);

        $ticket = Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Bulk',
            'surname' => 'Solo',
            'email' => 'bulkcancel@example.com',
            'phone' => '0400111555',
            'attended_at' => null,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ticket.cancel.bulk'), [
                'ticket_ids' => [$ticket->id],
                'process_square_refund' => 1,
                'email_customer' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('cancelled_count', 1)
            ->assertJsonPath('failed_count', 0);

        $ticket->refresh();
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $ticket->status);
        Queue::assertNotPushed(SendEmail::class);
    }

    public function test_admin_can_autosave_ticket_attendance_with_json(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ATTEND-1003',
            'user_id' => $customer->id,
            'billing_name' => 'Autosave Family',
            'billing_email' => 'autosave@example.com',
            'billing_phone' => '0400111333',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => 27.27,
            'gst_amount' => 2.73,
            'total_amount' => 30.00,
            'notes' => null,
        ]);

        $firstTicket = Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Auto',
            'surname' => 'One',
            'email' => 'autosave@example.com',
            'phone' => '0400111333',
            'attended_at' => null,
        ]);
        $secondTicket = Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Auto',
            'surname' => 'Two',
            'email' => 'autosave@example.com',
            'phone' => '0400111333',
            'attended_at' => null,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.workshop.attendance.tickets', $workshop), [
                'attended_ticket_ids' => [$firstTicket->id],
            ])
            ->assertOk()
            ->assertJsonPath('message', 'Ticket attendance has been updated.')
            ->assertJsonPath('attended_ticket_ids.0', $firstTicket->id);

        $firstTicket->refresh();
        $secondTicket->refresh();
        $this->assertNotNull($firstTicket->attended_at);
        $this->assertNull($secondTicket->attended_at);
    }

    public function test_admin_can_record_split_ticket_payments_from_attendance_without_emailing_receipts_by_default(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();
        Queue::fake();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ATTEND-1001',
            'user_id' => $customer->id,
            'billing_name' => 'Attendance Family',
            'billing_email' => 'family@example.com',
            'billing_phone' => '0400000000',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => 40.91,
            'gst_amount' => 4.09,
            'total_amount' => 45.00,
            'notes' => null,
        ]);

        $tickets = collect();
        foreach (['A', 'B', 'C'] as $suffix) {
            $tickets->push(Ticket::query()->create([
                'status' => Ticket::STATUS_PENDING_DOOR,
                'user_id' => $customer->id,
                'workshop_id' => $workshop->id,
                'invoice_id' => $invoice->id,
                'firstname' => 'Child',
                'surname' => 'Ticket'.$suffix,
                'email' => 'family@example.com',
                'phone' => '0400000000',
                'attended_at' => null,
            ]));
        }

        $response = $this->actingAs($admin)->post(route('admin.workshop.attendance.payments', $workshop), [
            'ticket_ids' => $tickets->pluck('id')->all(),
            'sync_attendance' => 1,
            'attended_ticket_ids' => $tickets->pluck('id')->all(),
            'payments' => [
                [
                    'method' => Payment::PAYMENT_METHOD_EFTPOS,
                    'amount' => 40.00,
                    'received_on' => now()->format('Y-m-d H:i:s'),
                    'reference' => 'EFTPOS-1',
                    'notes' => 'Counter payment',
                ],
                [
                    'method' => Payment::PAYMENT_METHOD_CASH,
                    'amount' => 5.00,
                    'received_on' => now()->format('Y-m-d H:i:s'),
                    'reference' => 'CASH-1',
                    'notes' => 'Cash top-up',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.workshop.attendance', $workshop));
        $response->assertSessionHasNoErrors();

        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_PAID, (string) $invoice->status);

        $payments = Payment::query()
            ->where('kind', Payment::KIND_PAYMENT)
            ->where('created_by', $admin->id)
            ->where('user_id', $customer->id)
            ->orderBy('id')
            ->get();
        $this->assertCount(2, $payments);
        $this->assertEqualsCanonicalizing(
            ['5.00', '40.00'],
            $payments->map(fn (Payment $payment): string => number_format((float) $payment->total_amount, 2, '.', ''))->all()
        );

        $allocatedTotal = (float) InvoicePaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->sum('allocated_amount');
        $this->assertSame(45.0, round($allocatedTotal, 2));

        foreach ($tickets as $ticket) {
            $ticket->refresh();
            $this->assertSame(Ticket::STATUS_DONE, (int) $ticket->status);
            $this->assertNotNull($ticket->attended_at);
        }

        Queue::assertNotPushed(SendEmail::class);
    }

    public function test_admin_can_opt_in_to_email_receipts_for_split_ticket_payments_from_attendance(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();
        Queue::fake();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ATTEND-1001A',
            'user_id' => $customer->id,
            'billing_name' => 'Attendance Family',
            'billing_email' => 'family@example.com',
            'billing_phone' => '0400000000',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => 40.91,
            'gst_amount' => 4.09,
            'total_amount' => 45.00,
            'notes' => null,
        ]);

        $tickets = collect();
        foreach (['A', 'B', 'C'] as $suffix) {
            $tickets->push(Ticket::query()->create([
                'status' => Ticket::STATUS_PENDING_DOOR,
                'user_id' => $customer->id,
                'workshop_id' => $workshop->id,
                'invoice_id' => $invoice->id,
                'firstname' => 'Child',
                'surname' => 'Ticket'.$suffix,
                'email' => 'family@example.com',
                'phone' => '0400000000',
                'attended_at' => null,
            ]));
        }

        $response = $this->actingAs($admin)->post(route('admin.workshop.attendance.payments', $workshop), [
            'ticket_ids' => $tickets->pluck('id')->all(),
            'sync_attendance' => 1,
            'attended_ticket_ids' => $tickets->pluck('id')->all(),
            'email_receipt' => 1,
            'payments' => [
                [
                    'method' => Payment::PAYMENT_METHOD_EFTPOS,
                    'amount' => 40.00,
                    'received_on' => now()->format('Y-m-d H:i:s'),
                    'reference' => 'EFTPOS-1',
                    'notes' => 'Counter payment',
                ],
                [
                    'method' => Payment::PAYMENT_METHOD_CASH,
                    'amount' => 5.00,
                    'received_on' => now()->format('Y-m-d H:i:s'),
                    'reference' => 'CASH-1',
                    'notes' => 'Cash top-up',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.workshop.attendance', $workshop));
        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendEmail::class, 2);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'family@example.com'
                && $job->mailable instanceof PaymentReceiptPdf
                && $job->mailable->invoiceSummary === '3 workshop tickets'
                && $job->mailable->paymentMethod === 'EFTPOS'
                && $job->mailable->statusSummary === 'There is $5.00 now remaining on this invoice.'
                && $job->mailable->creditSummary === null;
        });
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'family@example.com'
                && $job->mailable instanceof PaymentReceiptPdf
                && $job->mailable->invoiceSummary === '3 workshop tickets'
                && $job->mailable->paymentMethod === 'Cash'
                && $job->mailable->statusSummary === 'This invoice is now paid in full.'
                && $job->mailable->creditSummary === null;
        });
    }

    public function test_ticketed_attendance_page_surfaces_relevant_unallocated_eftpos_transactions(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();
        $otherCustomer = User::factory()->create();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ATTEND-1007',
            'user_id' => $customer->id,
            'billing_name' => 'Relevant EFTPOS Family',
            'billing_email' => 'relevant@example.com',
            'billing_phone' => '0400111666',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => 13.64,
            'gst_amount' => 1.36,
            'total_amount' => 15.00,
            'notes' => null,
        ]);

        Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Visible',
            'surname' => 'Student',
            'email' => 'relevant@example.com',
            'phone' => '0400111666',
            'attended_at' => null,
        ]);

        Payment::query()->create([
            'kind' => Payment::KIND_PAYMENT,
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'received_on' => now(),
            'payment_method' => Payment::PAYMENT_METHOD_EFTPOS,
            'reference' => 'EFTPOS-LINK-ME',
            'total_amount' => 15.00,
            'gst_amount' => 0,
            'notes' => null,
        ]);

        Payment::query()->create([
            'kind' => Payment::KIND_PAYMENT,
            'user_id' => $otherCustomer->id,
            'created_by' => $admin->id,
            'received_on' => now(),
            'payment_method' => Payment::PAYMENT_METHOD_EFTPOS,
            'reference' => 'EFTPOS-HIDE-ME',
            'total_amount' => 15.00,
            'gst_amount' => 0,
            'notes' => null,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.workshop.attendance', $workshop))
            ->assertOk()
            ->assertSee('EFTPOS-LINK-ME')
            ->assertDontSee('EFTPOS-HIDE-ME');
    }

    public function test_admin_can_link_existing_unallocated_eftpos_payment_from_attendance(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();
        Queue::fake();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ATTEND-1008',
            'user_id' => $customer->id,
            'billing_name' => 'Link EFTPOS Family',
            'billing_email' => 'linkeftpos@example.com',
            'billing_phone' => '0400111777',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => 13.64,
            'gst_amount' => 1.36,
            'total_amount' => 15.00,
            'notes' => null,
        ]);

        $ticket = Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Linked',
            'surname' => 'Student',
            'email' => 'linkeftpos@example.com',
            'phone' => '0400111777',
            'attended_at' => null,
        ]);

        $existingPayment = Payment::query()->create([
            'kind' => Payment::KIND_PAYMENT,
            'user_id' => null,
            'created_by' => $admin->id,
            'received_on' => now(),
            'payment_method' => Payment::PAYMENT_METHOD_EFTPOS,
            'reference' => 'EFTPOS-LINK-ONLY',
            'total_amount' => 20.00,
            'gst_amount' => 0,
            'notes' => 'Square terminal payment',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.workshop.attendance.payments', $workshop), [
            'ticket_ids' => [$ticket->id],
            'existing_payment_ids' => [$existingPayment->id],
            'sync_attendance' => 1,
            'attended_ticket_ids' => [$ticket->id],
            'email_receipt' => 1,
            'payments' => [
                [
                    'method' => Payment::PAYMENT_METHOD_EFTPOS,
                    'amount' => '',
                    'received_on' => now()->format('Y-m-d H:i:s'),
                    'reference' => '',
                    'notes' => '',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.workshop.attendance', $workshop));
        $response->assertSessionHasNoErrors();

        $invoice->refresh();
        $ticket->refresh();
        $existingPayment->refresh();

        $this->assertSame(Invoice::STATUS_PAID, (string) $invoice->status);
        $this->assertSame(Ticket::STATUS_DONE, (int) $ticket->status);
        $this->assertNotNull($ticket->attended_at);
        $this->assertSame((string) $customer->id, (string) $existingPayment->user_id);

        $this->assertDatabaseHas('invoice_payment_allocations', [
            'invoice_id' => $invoice->id,
            'payment_id' => $existingPayment->id,
            'allocated_amount' => 15.00,
        ]);

        $this->assertSame(15.0, round((float) InvoicePaymentAllocation::query()
            ->where('invoice_id', $invoice->id)
            ->where('payment_id', $existingPayment->id)
            ->sum('allocated_amount'), 2));

        Queue::assertPushed(SendEmail::class, 1);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'linkeftpos@example.com'
                && $job->mailable instanceof PaymentReceiptPdf
                && $job->mailable->invoiceSummary === '1 workshop ticket'
                && $job->mailable->paymentMethod === 'EFTPOS'
                && $job->mailable->statusSummary === 'This invoice is now paid in full.'
                && $job->mailable->creditSummary === 'You now have $5.00 sitting in credit on your account. Please contact us to discuss your options.';
        });
    }

    public function test_attendance_payment_marks_remaining_pay_at_door_ticket_paid_after_sibling_ticket_cancellation_adjustment(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();
        Queue::fake();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ATTEND-1008B',
            'user_id' => $customer->id,
            'billing_name' => 'Sibling Cancel Family',
            'billing_email' => 'siblingcancel@example.com',
            'billing_phone' => '0400111888',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => 27.27,
            'gst_amount' => 2.73,
            'total_amount' => 30.00,
            'notes' => null,
        ]);

        $firstTicket = Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Sibling',
            'surname' => 'One',
            'email' => 'siblingcancel@example.com',
            'phone' => '0400111888',
            'attended_at' => null,
        ]);
        $secondTicket = Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Sibling',
            'surname' => 'Two',
            'email' => 'siblingcancel@example.com',
            'phone' => '0400111888',
            'attended_at' => null,
        ]);

        $this->actingAs($admin)
            ->postJson(route('admin.ticket.cancel.bulk'), [
                'ticket_ids' => [$secondTicket->id],
                'process_square_refund' => 1,
                'email_customer' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('cancelled_count', 1)
            ->assertJsonPath('failed_count', 0);

        $existingPayment = Payment::query()->create([
            'kind' => Payment::KIND_PAYMENT,
            'user_id' => null,
            'created_by' => $admin->id,
            'received_on' => now(),
            'payment_method' => Payment::PAYMENT_METHOD_EFTPOS,
            'reference' => 'EFTPOS-SIBLING-CANCEL',
            'total_amount' => 15.00,
            'gst_amount' => 0,
            'notes' => 'Square terminal payment',
        ]);

        $response = $this->actingAs($admin)->post(route('admin.workshop.attendance.payments', $workshop), [
            'ticket_ids' => [$firstTicket->id],
            'existing_payment_ids' => [$existingPayment->id],
            'sync_attendance' => 1,
            'attended_ticket_ids' => [$firstTicket->id],
            'payments' => [
                [
                    'method' => Payment::PAYMENT_METHOD_EFTPOS,
                    'amount' => '',
                    'received_on' => now()->format('Y-m-d H:i:s'),
                    'reference' => '',
                    'notes' => '',
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.workshop.attendance', $workshop));
        $response->assertSessionHasNoErrors();

        $invoice->refresh();
        $firstTicket->refresh();
        $secondTicket->refresh();

        $this->assertSame(Invoice::STATUS_PAID, (string) $invoice->status);
        $this->assertSame(Ticket::STATUS_DONE, (int) $firstTicket->status);
        $this->assertSame('Paid', $firstTicket->customer_status_label);
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $secondTicket->status);
    }

    public function test_cancelling_second_pay_at_door_ticket_after_partial_attendance_payment_marks_remaining_ticket_paid(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();
        Queue::fake();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ATTEND-1008C',
            'user_id' => $customer->id,
            'billing_name' => 'Sibling Cancel Family',
            'billing_email' => 'siblingcancel@example.com',
            'billing_phone' => '0400111888',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => 27.27,
            'gst_amount' => 2.73,
            'total_amount' => 30.00,
            'notes' => null,
        ]);

        $firstTicket = Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Sibling',
            'surname' => 'One',
            'email' => 'siblingcancel@example.com',
            'phone' => '0400111888',
            'attended_at' => null,
        ]);
        $secondTicket = Ticket::query()->create([
            'status' => Ticket::STATUS_PENDING_DOOR,
            'user_id' => $customer->id,
            'workshop_id' => $workshop->id,
            'invoice_id' => $invoice->id,
            'firstname' => 'Sibling',
            'surname' => 'Two',
            'email' => 'siblingcancel@example.com',
            'phone' => '0400111888',
            'attended_at' => null,
        ]);

        $existingPayment = Payment::query()->create([
            'kind' => Payment::KIND_PAYMENT,
            'user_id' => null,
            'created_by' => $admin->id,
            'received_on' => now(),
            'payment_method' => Payment::PAYMENT_METHOD_EFTPOS,
            'reference' => 'EFTPOS-SIBLING-CANCEL-FIRST',
            'total_amount' => 15.00,
            'gst_amount' => 0,
            'notes' => 'Square terminal payment',
        ]);

        $paymentResponse = $this->actingAs($admin)->post(route('admin.workshop.attendance.payments', $workshop), [
            'ticket_ids' => [$firstTicket->id],
            'existing_payment_ids' => [$existingPayment->id],
            'sync_attendance' => 1,
            'attended_ticket_ids' => [$firstTicket->id],
            'payments' => [
                [
                    'method' => Payment::PAYMENT_METHOD_EFTPOS,
                    'amount' => '',
                    'received_on' => now()->format('Y-m-d H:i:s'),
                    'reference' => '',
                    'notes' => '',
                ],
            ],
        ]);

        $paymentResponse->assertRedirect(route('admin.workshop.attendance', $workshop));
        $paymentResponse->assertSessionHasNoErrors();

        $this->actingAs($admin)
            ->postJson(route('admin.ticket.cancel.bulk'), [
                'ticket_ids' => [$secondTicket->id],
                'process_square_refund' => 1,
                'email_customer' => 0,
            ])
            ->assertOk()
            ->assertJsonPath('cancelled_count', 1)
            ->assertJsonPath('failed_count', 0);

        $invoice->refresh();
        $firstTicket->refresh();
        $secondTicket->refresh();

        $this->assertSame(Invoice::STATUS_PAID, (string) $invoice->status);
        $this->assertSame(Ticket::STATUS_DONE, (int) $firstTicket->status);
        $this->assertSame('Paid', $firstTicket->customer_status_label);
        $this->assertSame(Ticket::STATUS_CANCELLED, (int) $secondTicket->status);
    }

    public function test_payment_modal_attendance_updates_only_checked_tickets(): void
    {
        $admin = $this->createAdminUser();
        $workshop = $this->createWorkshop('tickets');
        $customer = User::factory()->create();

        $invoice = Invoice::query()->create([
            'invoice_number' => 'INV-ATTEND-1004',
            'user_id' => $customer->id,
            'billing_name' => 'Partial Attendance Family',
            'billing_email' => 'partial@example.com',
            'billing_phone' => '0400444555',
            'status' => Invoice::STATUS_ISSUED,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
            'subtotal_amount' => 40.91,
            'gst_amount' => 4.09,
            'total_amount' => 45.00,
            'notes' => null,
        ]);

        $tickets = collect();
        foreach (['A', 'B', 'C'] as $suffix) {
            $tickets->push(Ticket::query()->create([
                'status' => Ticket::STATUS_PENDING_DOOR,
                'user_id' => $customer->id,
                'workshop_id' => $workshop->id,
                'invoice_id' => $invoice->id,
                'firstname' => 'Child',
                'surname' => 'Partial'.$suffix,
                'email' => 'partial@example.com',
                'phone' => '0400444555',
                'attended_at' => null,
            ]));
        }

        $attendedIds = [$tickets[0]->id, $tickets[1]->id];
        $response = $this->actingAs($admin)->post(route('admin.workshop.attendance.payments', $workshop), [
            'ticket_ids' => $tickets->pluck('id')->all(),
            'sync_attendance' => 1,
            'attended_ticket_ids' => $attendedIds,
            'payments' => [
                [
                    'method' => Payment::PAYMENT_METHOD_CASH,
                    'amount' => 30.00,
                    'received_on' => now()->format('Y-m-d H:i:s'),
                ],
            ],
        ]);

        $response->assertRedirect(route('admin.workshop.attendance', $workshop));
        $response->assertSessionHasNoErrors();

        $tickets[0]->refresh();
        $tickets[1]->refresh();
        $tickets[2]->refresh();
        $this->assertNotNull($tickets[0]->attended_at);
        $this->assertNotNull($tickets[1]->attended_at);
        $this->assertNull($tickets[2]->attended_at);
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

    private function createWorkshop(string $registration): Workshop
    {
        $owner = User::factory()->create();
        $location = Location::factory()->create();
        $heroName = 'hero-kiosk.png';

        Media::query()->create([
            'name' => $heroName,
            'title' => 'Hero',
            'hash' => str_repeat('b', 64),
            'mime_type' => 'image/png',
            'size' => 1024,
            'user_id' => $owner->id,
        ]);

        return Workshop::query()->create([
            'title' => 'Kiosk Workshop',
            'content' => '<p>Attendance</p>',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDay()->addHours(2),
            'publish_at' => now()->subDay(),
            'closes_at' => now()->addHours(12),
            'status' => 'open',
            'registration' => $registration,
            'location_id' => $location->id,
            'user_id' => $owner->id,
            'hero_media_name' => $heroName,
        ]);
    }
}
