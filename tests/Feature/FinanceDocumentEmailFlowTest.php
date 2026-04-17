<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\FinanceDocumentPdf;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\Quote;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use ReflectionClass;
use Tests\TestCase;

class FinanceDocumentEmailFlowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(ValidateCsrfToken::class);
    }

    public function test_quote_email_parses_dedupes_recipients_and_filters_cc(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $quoteOwner = User::factory()->create();
        $quote = Quote::factory()->create([
            'user_id' => $quoteOwner->id,
            'line_items' => [],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.quote.edit', $quote))
            ->post(route('admin.quote.email', $quote), [
                'recipient_emails' => 'alpha@example.com; beta@example.com, alpha@example.com',
                'cc_emails' => 'beta@example.com; team@example.com; TEAM@example.com',
                'email_message' => 'Custom quote message',
            ]);

        $response->assertRedirect(route('admin.quote.edit', $quote));
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(SendEmail::class, 2);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($admin) {
            if ($job->to !== 'alpha@example.com') {
                return false;
            }

            $this->assertInstanceOf(FinanceDocumentPdf::class, $job->mailable);
            $cc = array_map('strtolower', $this->extractAddressList($job->mailable, 'cc'));
            $this->assertContains('team@example.com', $cc);
            $this->assertNotContains('alpha@example.com', $cc);
            $this->assertContains(strtolower((string) $admin->email), $cc);

            return true;
        });
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($admin) {
            if ($job->to !== 'beta@example.com') {
                return false;
            }

            $cc = array_map('strtolower', $this->extractAddressList($job->mailable, 'cc'));
            $this->assertContains('team@example.com', $cc);
            $this->assertNotContains('beta@example.com', $cc);
            $this->assertContains(strtolower((string) $admin->email), $cc);

            return true;
        });
    }

    public function test_quote_email_rejects_invalid_recipient_addresses(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $quote = Quote::factory()->create([
            'user_id' => User::factory()->create()->id,
            'line_items' => [],
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.quote.edit', $quote))
            ->post(route('admin.quote.email', $quote), [
                'recipient_emails' => 'valid@example.com; not-an-email',
                'email_message' => 'Hello',
            ]);

        $response->assertRedirect(route('admin.quote.edit', $quote));
        $response->assertSessionHasErrors('recipient_emails');
        Queue::assertNothingPushed();
    }

    public function test_invoice_email_rejects_invalid_cc_addresses(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'billing_email' => 'customer@example.com',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'kind' => 'generic',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.invoice.edit', $invoice))
            ->post(route('admin.invoice.email', $invoice), [
                'recipient_emails' => 'customer@example.com',
                'cc_emails' => 'team@example.com;bad-address',
            ]);

        $response->assertRedirect(route('admin.invoice.edit', $invoice));
        $response->assertSessionHasErrors('cc_emails');
        Queue::assertNothingPushed();
    }

    public function test_invoice_email_uses_placeholder_tokens_in_the_subject_line(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $owner = User::factory()->create([
            'firstname' => 'Parker',
            'surname' => 'Lee',
            'email' => 'parker.lee@example.com',
        ]);
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-9001',
            'user_id' => $owner->id,
            'billing_email' => 'billing@example.com',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'kind' => 'generic',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.invoice.edit', $invoice))
            ->post(route('admin.invoice.email', $invoice), [
                'recipient_emails' => 'billing@example.com',
                'subject_line' => 'Invoice {{id}} for {{name}}',
                'email_message' => 'Custom invoice message',
            ]);

        $response->assertRedirect(route('admin.invoice.edit', $invoice));
        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) {
            return $job->mailable instanceof FinanceDocumentPdf
                && $job->mailable->build()->subject === 'Invoice INV-9001 for Parker';
        });
    }

    public function test_invoice_email_uses_purchase_order_placeholder_tokens_in_subject_and_message(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $owner = User::factory()->create([
            'firstname' => 'Parker',
            'surname' => 'Lee',
            'email' => 'parker.lee@example.com',
        ]);
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'invoice_number' => 'INV-9002',
            'purchase_order_number' => 'PO-7788',
            'user_id' => $owner->id,
            'billing_email' => 'billing@example.com',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'kind' => 'generic',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.invoice.edit', $invoice))
            ->post(route('admin.invoice.email', $invoice), [
                'recipient_emails' => 'billing@example.com',
                'subject_line' => 'Invoice {{id}} PO {{po}}',
                'email_message' => 'PO: {{po}}',
            ]);

        $response->assertRedirect(route('admin.invoice.edit', $invoice));
        $response->assertSessionHasNoErrors();

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) {
            if (! $job->mailable instanceof FinanceDocumentPdf) {
                return false;
            }

            /** @var FinanceDocumentPdf $mailable */
            $mailable = $job->mailable;

            $this->assertSame('Invoice INV-9002 PO PO-7788', $mailable->build()->subject);
            $this->assertStringContainsString('PO: PO-7788', (string) $mailable->resolvedFullMessage);

            return true;
        });
    }

    public function test_invoice_save_and_email_reopens_email_modal_after_finalizing(): void
    {
        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'billing_email' => 'customer@example.com',
            'status' => Invoice::STATUS_DRAFT,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'kind' => 'generic',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.invoice.edit', $invoice))
            ->put(route('admin.invoice.update', $invoice), [
                'invoice_number' => $invoice->invoice_number,
                'user_id' => $owner->id,
                'issue_now' => 1,
                'issue_date' => now()->toDateString(),
                'due_date' => now()->addDays(14)->toDateString(),
                'purchase_order_number' => $invoice->purchase_order_number,
                'notes' => $invoice->notes,
                'line_items_json' => json_encode([[
                    'kind' => 'generic',
                    'description' => 'Workshop materials',
                    'notes' => '',
                    'quantity' => 1,
                    'unit_price' => 10,
                    'gst_applicable' => true,
                ]]),
                'save_and_email' => 1,
            ]);

        $response->assertRedirect(route('admin.invoice.edit', $invoice));
        $response->assertSessionHas('invoice-email-open', true);
        $this->assertSame(Invoice::STATUS_ISSUED, (string) $invoice->fresh()->status);
    }

    public function test_invoice_email_parses_dedupes_recipients_and_filters_cc(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $owner = User::factory()->create();
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'billing_email' => 'billing@example.com',
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'kind' => 'generic',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.invoice.edit', $invoice))
            ->post(route('admin.invoice.email', $invoice), [
                'recipient_emails' => 'first@example.com; second@example.com, FIRST@example.com',
                'cc_emails' => 'second@example.com; ops@example.com; OPS@example.com',
                'email_message' => 'Custom invoice message',
            ]);

        $response->assertRedirect(route('admin.invoice.edit', $invoice));
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(SendEmail::class, 2);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($admin) {
            if (strtolower((string) $job->to) !== 'first@example.com') {
                return false;
            }

            $cc = array_map('strtolower', $this->extractAddressList($job->mailable, 'cc'));
            $this->assertContains('ops@example.com', $cc);
            $this->assertNotContains(strtolower((string) $admin->email), $cc);
            $this->assertNotContains('first@example.com', $cc);

            return true;
        });
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($admin) {
            if ($job->to !== 'second@example.com') {
                return false;
            }

            $cc = array_map('strtolower', $this->extractAddressList($job->mailable, 'cc'));
            $this->assertContains('ops@example.com', $cc);
            $this->assertNotContains(strtolower((string) $admin->email), $cc);
            $this->assertNotContains('second@example.com', $cc);

            return true;
        });
    }

    public function test_invoice_email_custom_message_replaces_placeholders_and_keeps_pay_placeholder(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $owner = User::factory()->create([
            'firstname' => 'Casey',
            'surname' => 'Buyer',
        ]);
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'invoice_number' => 'INV-9988',
            'billing_name' => 'Casey Buyer',
            'billing_email' => 'casey@example.com',
            'total_amount' => 123.45,
            'due_date' => now()->addDays(14)->toDateString(),
            'status' => Invoice::STATUS_ISSUED,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'kind' => 'ticket',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.invoice.edit', $invoice))
            ->post(route('admin.invoice.email', $invoice), [
                'recipient_emails' => 'casey@example.com',
                'email_message' => "Hi {{name}},\nInvoice {{id}} total {{total}} outstanding {{outstanding}} due {{due}}\n\n{{pay}}",
            ]);

        $response->assertRedirect(route('admin.invoice.edit', $invoice));
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(SendEmail::class, 1);

        $capturedJob = null;
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use (&$capturedJob) {
            $capturedJob = $job;
            return true;
        });

        $this->assertNotNull($capturedJob);
        $mailable = $capturedJob->mailable;
        $this->assertInstanceOf(FinanceDocumentPdf::class, $mailable);
        /** @var FinanceDocumentPdf $mailable */
        $resolved = (string) $mailable->resolvedFullMessage;
        $this->assertStringContainsString('Hi Casey,', $resolved);
        $this->assertStringContainsString('Invoice INV-9988', $resolved);
        $this->assertStringContainsString('$123.45', $resolved);
        $this->assertStringNotContainsString('{{name}}', $resolved);
        $this->assertStringContainsString('{{pay}}', $resolved);

        $html = $mailable->render();
        $this->assertStringContainsString('View and Pay Invoice', $html);
    }

    public function test_invoice_email_default_message_for_paid_invoice_uses_paid_wording_without_pay_placeholder(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser();
        $owner = User::factory()->create([
            'firstname' => 'Jordan',
            'surname' => 'Smith',
        ]);
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'invoice_number' => 'INV-PAID-1',
            'billing_name' => 'Jordan Smith',
            'billing_email' => 'jordan@example.com',
            'total_amount' => 0.00,
            'status' => Invoice::STATUS_PAID,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'kind' => 'generic',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.invoice.edit', $invoice))
            ->post(route('admin.invoice.email', $invoice), [
                'recipient_emails' => 'jordan@example.com',
            ]);

        $response->assertRedirect(route('admin.invoice.edit', $invoice));
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(SendEmail::class, 1);

        $capturedJob = null;
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use (&$capturedJob) {
            $capturedJob = $job;
            return true;
        });

        $this->assertNotNull($capturedJob);
        $mailable = $capturedJob->mailable;
        $this->assertInstanceOf(FinanceDocumentPdf::class, $mailable);
        /** @var FinanceDocumentPdf $mailable */
        $resolved = (string) $mailable->resolvedFullMessage;
        $this->assertStringContainsString('paid in full', $resolved);
        $this->assertStringNotContainsString('{{pay}}', $resolved);
    }

    public function test_invoice_email_appends_private_note_when_sent_to_customer(): void
    {
        Queue::fake();

        $admin = $this->createAdminUser([
            'firstname' => 'James',
            'surname' => 'Collins',
            'email' => 'james@example.com',
        ]);
        $owner = User::factory()->create([
            'firstname' => 'Pat',
            'surname' => 'Customer',
            'email' => 'pat@example.com',
        ]);
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'user_id' => $owner->id,
            'billing_name' => 'Pat Customer',
            'billing_email' => 'pat@example.com',
            'status' => Invoice::STATUS_ISSUED,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'kind' => 'generic',
        ]);

        $response = $this->actingAs($admin)
            ->from(route('admin.invoice.edit', $invoice))
            ->post(route('admin.invoice.email', $invoice), [
                'recipient_emails' => 'pat@example.com',
                'email_message' => 'Invoice message',
            ]);

        $response->assertRedirect(route('admin.invoice.edit', $invoice));
        $response->assertSessionHasNoErrors();
        Queue::assertPushed(SendEmail::class, 1);

        $freshInvoice = $invoice->fresh();
        $this->assertInstanceOf(Invoice::class, $freshInvoice);
        $this->assertStringContainsString('Invoice emailed to pat@example.com by James // STEMMechanics', (string) $freshInvoice->notes);
    }

    private function createAdminUser(array $overrides = []): User
    {
        $admin = User::factory()->create($overrides);
        UserGroup::query()->create([
            'user_id' => $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }

    /**
     * @return array<int, string>
     */
    private function extractAddressList(object $mailable, string $property): array
    {
        $reflection = new ReflectionClass($mailable);
        $propertyReflection = $reflection->getProperty($property);
        $propertyReflection->setAccessible(true);
        $value = $propertyReflection->getValue($mailable);

        if (! is_array($value)) {
            return [];
        }

        $addresses = [];
        foreach ($value as $entry) {
            if (is_array($entry) && isset($entry['address']) && is_string($entry['address'])) {
                $addresses[] = $entry['address'];
            }
        }

        return $addresses;
    }
}
