<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Jobs\SendScheduledInvoiceEmail;
use App\Mail\FinanceDocumentPdf;
use App\Mail\ScheduledInvoiceReview;
use App\Models\Invoice;
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

}
