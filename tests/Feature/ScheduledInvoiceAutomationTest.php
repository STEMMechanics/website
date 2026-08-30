<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Jobs\SendScheduledInvoiceEmail;
use App\Mail\FinanceDocumentPdf;
use App\Mail\ScheduledInvoiceReview;
use App\Models\Invoice;
use App\Models\Organisation;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\ScheduledInvoiceDeliveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ScheduledInvoiceAutomationTest extends TestCase
{
    use RefreshDatabase;

    public function test_scheduled_invoice_sends_an_admin_review_notice_the_day_before(): void
    {
        Queue::fake();
        $admin = User::factory()->create(['email' => 'admin@example.com']);
        UserGroup::factory()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $invoice = Invoice::factory()->create([
            'issue_date' => today()->addDay(),
            'scheduled_email' => true,
            'scheduled_review_sent_at' => null,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $this->artisan('invoices:process-scheduled')->assertSuccessful();

        Queue::assertPushed(SendEmail::class, fn (SendEmail $job): bool => $job->to === 'admin@example.com' && $job->mailable instanceof ScheduledInvoiceReview);
        $this->assertNotNull($invoice->fresh()->scheduled_review_sent_at);
    }

    public function test_scheduled_invoice_is_finalised_and_queued_on_its_issue_date(): void
    {
        Queue::fake();
        $customer = User::factory()->create(['email' => 'customer@example.com']);
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_email' => 'customer@example.com',
            'issue_date' => today(),
            'due_date' => today()->addDays(14),
            'scheduled_email' => true,
            'scheduled_email_queued_at' => null,
            'status' => Invoice::STATUS_DRAFT,
        ]);

        $this->artisan('invoices:process-scheduled')->assertSuccessful();

        Queue::assertPushed(SendScheduledInvoiceEmail::class, fn (SendScheduledInvoiceEmail $job): bool => $job->invoiceId === (int) $invoice->id);
        $invoice->refresh();
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertNotNull($invoice->scheduled_email_queued_at);

        $this->artisan('invoices:process-scheduled')->assertSuccessful();
        Queue::assertPushed(SendScheduledInvoiceEmail::class, 1);
    }

    public function test_due_scheduled_invoice_can_be_finalised_and_queued_immediately_when_saved(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        UserGroup::factory()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $customer = User::factory()->create(['email' => 'customer@example.com']);

        $response = $this->actingAs($admin)->post(route('admin.invoice.store'), [
            'invoice_number' => 'INV-SEND-NOW',
            'user_id' => $customer->id,
            'issue_date' => today()->toDateString(),
            'due_date' => today()->addDays(28)->toDateString(),
            'scheduled_email' => '1',
            'send_scheduled_now' => '1',
            'email_template_pending' => '1',
            'recipient_emails' => 'accounts@example.com',
            'cc_emails' => '{{email}}',
            'subject_line' => 'Scheduled {{id}}',
            'email_message' => 'Scheduled message for {{id}}.',
            'line_items_json' => '[]',
        ]);

        $response->assertSessionHasNoErrors();
        $invoice = Invoice::query()->where('invoice_number', 'INV-SEND-NOW')->firstOrFail();
        $this->assertSame(Invoice::STATUS_ISSUED, $invoice->status);
        $this->assertNotNull($invoice->issued_at);
        $this->assertNotNull($invoice->scheduled_email_queued_at);
        $this->assertTrue($invoice->email_template_set);
        $this->assertSame('accounts@example.com', $invoice->email_template_to);
        $this->assertSame('{{email}}', $invoice->email_template_cc);
        Queue::assertPushed(SendScheduledInvoiceEmail::class, fn (SendScheduledInvoiceEmail $job): bool => $job->invoiceId === (int) $invoice->id);
    }

    public function test_due_scheduled_invoice_can_remain_a_draft_until_the_next_scheduler_run(): void
    {
        Queue::fake();
        $admin = User::factory()->create();
        UserGroup::factory()->create(['user_id' => $admin->id, 'slug' => 'admin']);
        $customer = User::factory()->create(['email' => 'customer@example.com']);

        $response = $this->actingAs($admin)->post(route('admin.invoice.store'), [
            'invoice_number' => 'INV-SEND-LATER',
            'user_id' => $customer->id,
            'issue_date' => today()->toDateString(),
            'due_date' => today()->addDays(28)->toDateString(),
            'scheduled_email' => '1',
            'send_scheduled_now' => '0',
            'line_items_json' => '[]',
        ]);

        $response->assertSessionHasNoErrors();
        $invoice = Invoice::query()->where('invoice_number', 'INV-SEND-LATER')->firstOrFail();
        $this->assertSame(Invoice::STATUS_DRAFT, $invoice->status);
        $this->assertNull($invoice->scheduled_email_queued_at);
        Queue::assertNotPushed(SendScheduledInvoiceEmail::class);
    }

    public function test_scheduled_invoice_job_marks_the_invoice_sent_after_delivery(): void
    {
        Mail::fake();
        $customer = User::factory()->create(['email' => 'customer@example.com']);
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_email' => 'customer@example.com',
            'issue_date' => today(),
            'due_date' => today()->addDays(14),
            'scheduled_email' => true,
            'scheduled_email_queued_at' => now(),
            'status' => Invoice::STATUS_ISSUED,
        ]);

        (new SendScheduledInvoiceEmail((int) $invoice->id))->handle(app(ScheduledInvoiceDeliveryService::class));

        Mail::assertSent(FinanceDocumentPdf::class, fn (FinanceDocumentPdf $mail): bool => $mail->hasTo('customer@example.com'));
        $this->assertSame(Invoice::STATUS_SENT, $invoice->fresh()->status);
        $this->assertNotNull($invoice->fresh()->scheduled_email_sent_at);
    }

    public function test_scheduled_invoice_uses_organisation_email_defaults_and_contact_placeholder(): void
    {
        Mail::fake();
        $organisation = Organisation::factory()->create([
            'invoice_email_to' => 'invoices@cairns.qld.gov.au',
            'invoice_email_cc' => '{{email}}',
            'invoice_email_subject' => 'Your Invoice {{id}} from STEMMechanics - PO {{po}}',
            'invoice_email_message' => 'Hi Accounts Payable. Please don&#039;t hesitate to contact us about invoice {{id}} due {{due}} for {{outstanding}}.',
        ]);
        $customer = User::factory()->create([
            'email' => 'jemima@example.com',
            'primary_organisation_id' => $organisation->id,
        ]);
        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'billing_email' => 'jemima@example.com',
            'invoice_number' => 'INV-CRC-1',
            'purchase_order_number' => 'PO-123',
            'issue_date' => today(),
            'due_date' => today()->addDays(14),
            'scheduled_email' => true,
            'scheduled_email_queued_at' => now(),
            'status' => Invoice::STATUS_ISSUED,
        ]);

        (new SendScheduledInvoiceEmail((int) $invoice->id))->handle(app(ScheduledInvoiceDeliveryService::class));

        Mail::assertSent(FinanceDocumentPdf::class, function (FinanceDocumentPdf $mail): bool {
            $this->assertTrue($mail->hasTo('invoices@cairns.qld.gov.au'));
            $this->assertTrue($mail->hasCc('jemima@example.com'));
            $this->assertSame('Your Invoice INV-CRC-1 from STEMMechanics - PO PO-123', $mail->build()->subject);
            $this->assertStringContainsString("Please don't hesitate", (string) $mail->resolvedFullMessage);
            $this->assertStringNotContainsString('&#039;', (string) $mail->resolvedFullMessage);

            return true;
        });
    }

}
