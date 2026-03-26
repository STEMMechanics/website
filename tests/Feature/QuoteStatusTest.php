<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Mail\FinanceDocumentPdf;
use App\Mail\QuoteCustomerResponseAdminNotification;
use App\Models\Product;
use App\Models\Quote;
use App\Models\StoreOrder;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\QuoteWorkflowService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class QuoteStatusTest extends TestCase
{
    use RefreshDatabase;

    public function test_account_quotes_hide_drafts_and_expire_old_open_quotes(): void
    {
        $owner = User::factory()->create();

        /** @var Quote $draftQuote */
        $draftQuote = Quote::factory()->create([
            'user_id' => $owner->id,
            'quote_number' => 'Q-DRAFT-1',
            'status' => Quote::STATUS_DRAFT,
            'quote_date' => now()->toDateString(),
        ]);
        /** @var Quote $openQuote */
        $openQuote = Quote::factory()->create([
            'user_id' => $owner->id,
            'quote_number' => 'Q-OPEN-1',
            'status' => Quote::STATUS_OPEN,
            'quote_date' => now()->toDateString(),
        ]);
        /** @var Quote $cancelledQuote */
        $cancelledQuote = Quote::factory()->create([
            'user_id' => $owner->id,
            'quote_number' => 'Q-CANCELLED-1',
            'status' => Quote::STATUS_CANCELLED,
            'quote_date' => now()->toDateString(),
        ]);
        /** @var Quote $acceptedQuote */
        $acceptedQuote = Quote::factory()->create([
            'user_id' => $owner->id,
            'quote_number' => 'Q-ACCEPTED-1',
            'status' => Quote::STATUS_ACCEPTED,
            'quote_date' => now()->toDateString(),
        ]);
        /** @var Quote $expiredQuote */
        $expiredQuote = Quote::factory()->create([
            'user_id' => $owner->id,
            'quote_number' => 'Q-OLD-1',
            'status' => Quote::STATUS_OPEN,
            'quote_date' => now()->subDays(28)->toDateString(),
        ]);

        $this->actingAs($owner)
            ->get(route('account.quote.index'))
            ->assertOk()
            ->assertDontSeeText($draftQuote->quote_number)
            ->assertSeeText($openQuote->quote_number)
            ->assertSeeText($cancelledQuote->quote_number)
            ->assertSeeText($acceptedQuote->quote_number)
            ->assertSeeText('Q-OLD-1')
            ->assertSeeText('Open')
            ->assertSeeText('Cancelled')
            ->assertSeeText('Accepted')
            ->assertSeeText('Expired');

        /** @var Quote $freshExpiredQuote */
        $freshExpiredQuote = Quote::query()->findOrFail($expiredQuote->id);
        $this->assertSame(Quote::STATUS_EXPIRED, (string) $freshExpiredQuote->status);
    }

    public function test_customer_cannot_open_a_draft_quote_pdf(): void
    {
        $owner = User::factory()->create();
        /** @var Quote $quote */
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'status' => Quote::STATUS_DRAFT,
        ]);

        $this->actingAs($owner)
            ->get(route('account.quote.pdf', $quote))
            ->assertForbidden();
    }

    public function test_emailing_a_draft_quote_marks_it_open(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);
        /** @var Quote $quote */
        $quote = Quote::factory()->create([
            'status' => Quote::STATUS_DRAFT,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.quote.email', $quote), [
                'recipient_emails' => 'customer@example.com',
                'email_message' => 'Attached is your quote.',
            ])
            ->assertRedirect();

        /** @var Quote $freshQuote */
        $freshQuote = Quote::query()->findOrFail($quote->id);
        $this->assertSame(Quote::STATUS_OPEN, (string) $freshQuote->status);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            if (! $job->mailable instanceof FinanceDocumentPdf) {
                return false;
            }

            $this->assertSame('Review Quote', $job->mailable->actionLabel);

            return true;
        });
    }

    public function test_customer_can_accept_quote_from_magic_link(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $owner = User::factory()->create([
            'firstname' => 'Avery',
            'surname' => 'Example',
            'email' => 'avery@example.com',
        ]);
        /** @var Quote $quote */
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'status' => Quote::STATUS_OPEN,
            'quote_date' => now()->toDateString(),
            'line_items' => [[
                'kind' => 'custom',
                'description' => 'Workshop quote',
                'notes' => '',
                'quantity' => 1,
                'unit_price' => 100,
                'line_total' => 100,
                'gst_applicable' => true,
            ]],
            'subtotal_amount' => 100,
            'gst_amount' => 10,
            'total_amount' => 110,
            'context_payload' => [
                'customer' => [
                    'billing_name' => 'Avery Example',
                    'billing_email' => 'avery@example.com',
                ],
            ],
        ]);

        $workflow = app(QuoteWorkflowService::class);
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'test.stemmechanics.com.au';
        $reviewUrl = $workflow->quoteReviewUrl($quote);
        $acceptUrl = $workflow->quoteMagicActionUrl($quote, 'quote.magic.accept');
        $acceptPath = parse_url($acceptUrl, PHP_URL_PATH).'?'.parse_url($acceptUrl, PHP_URL_QUERY);

        $this->withServerVariables(['HTTP_HOST' => $host, 'HTTPS' => 'on'])
            ->post($acceptPath)
            ->assertRedirect($reviewUrl);

        /** @var Quote $freshQuote */
        $freshQuote = Quote::query()->findOrFail($quote->id);
        $this->assertSame(Quote::STATUS_ACCEPTED, (string) $freshQuote->status);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'ops@example.com'
                && $job->mailable instanceof QuoteCustomerResponseAdminNotification;
        });
    }

    public function test_customer_can_accept_and_pay_quote_from_magic_link(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $owner = User::factory()->create([
            'firstname' => 'Avery',
            'surname' => 'Example',
            'email' => 'avery@example.com',
        ]);
        /** @var Product $product */
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Microbit',
            'sku' => 'MICROBIT',
            'price' => 50.00,
            'inventory_quantity' => 10,
            'shipping_units' => 0.1,
            'weight_grams' => 100,
            'tax_rate' => 0.1,
        ]);
        /** @var Quote $quote */
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'status' => Quote::STATUS_OPEN,
            'context_type' => Quote::CONTEXT_STORE_MANUAL_SHIPPING,
            'quote_date' => now()->toDateString(),
            'line_items' => [[
                'kind' => 'product',
                'description' => 'Microbit',
                'notes' => '',
                'quantity' => 1,
                'unit_price' => 50,
                'line_total' => 50,
                'gst_applicable' => true,
                'store_context' => [
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'product_title' => 'Microbit',
                    'product_slug' => $product->slug,
                    'variant_name' => '',
                    'product_sku' => 'MICROBIT',
                    'variant_sku' => '',
                    'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                    'box_only' => false,
                    'available_now_quantity' => 1,
                    'delayed_quantity' => 0,
                    'delayed_fulfilment_type' => null,
                    'delayed_shipping_estimate' => null,
                    'unit_shipping_units' => 0.1,
                    'unit_min_satchel_rank' => 1,
                    'unit_weight_grams' => 100,
                    'tax_rate' => 0.1,
                ],
            ]],
            'subtotal_amount' => 50,
            'gst_amount' => 5,
            'total_amount' => 55,
            'context_payload' => [
                'acceptance' => [
                    'creates_order' => true,
                    'emails_invoice' => true,
                ],
                'customer' => [
                    'billing_name' => 'Avery Example',
                    'billing_email' => 'avery@example.com',
                ],
            ],
        ]);

        $workflow = app(QuoteWorkflowService::class);
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'test.stemmechanics.com.au';
        $reviewUrl = $workflow->quoteReviewUrl($quote);
        $acceptUrl = $workflow->quoteMagicActionUrl($quote, 'quote.magic.accept');
        $acceptPath = parse_url($acceptUrl, PHP_URL_PATH).'?'.parse_url($acceptUrl, PHP_URL_QUERY);

        $this->withServerVariables(['HTTP_HOST' => $host, 'HTTPS' => 'on'])
            ->get($reviewUrl)
            ->assertOk()
            ->assertSeeText('Accept & Pay Invoice')
            ->assertDontSeeText('Accept Quote');

        $response = $this->withServerVariables(['HTTP_HOST' => $host, 'HTTPS' => 'on'])
            ->post($acceptPath, [
                'accept_and_pay' => 1,
            ]);

        /** @var Quote $freshQuote */
        $freshQuote = Quote::query()->findOrFail($quote->id);
        $invoice = $freshQuote->invoices()->latest('id')->first();
        $this->assertNotNull($invoice);
        $this->assertSame(Quote::STATUS_ACCEPTED, (string) $freshQuote->status);
        $this->assertSame(1, StoreOrder::query()->where('invoice_id', $invoice->id)->count());
        $this->assertSame(Invoice::STATUS_ISSUED, (string) $invoice->fresh()?->status);

        $this->assertStringContainsString('/invoices/magic?token=', (string) $response->headers->get('Location'));
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'ops@example.com'
                && $job->mailable instanceof QuoteCustomerResponseAdminNotification;
        });
    }

    public function test_account_quote_page_labels_gst_basis_and_shows_linked_invoice_actions(): void
    {
        $owner = User::factory()->create();
        /** @var Quote $quote */
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'status' => Quote::STATUS_ACCEPTED,
            'quote_date' => now()->toDateString(),
            'line_items' => [[
                'kind' => 'custom',
                'description' => 'Workshop quote',
                'notes' => '',
                'quantity' => 2,
                'unit_price' => 50,
                'line_total' => 100,
                'gst_applicable' => true,
            ]],
            'subtotal_amount' => 100,
            'gst_amount' => 10,
            'total_amount' => 110,
        ]);
        /** @var Invoice $invoice */
        $invoice = Invoice::factory()->create([
            'quote_id' => $quote->id,
            'user_id' => $owner->id,
            'status' => Invoice::STATUS_ISSUED,
            'total_amount' => 110,
            'gst_amount' => 10,
            'subtotal_amount' => 100,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'kind' => 'generic',
        ]);

        $this->actingAs($owner)
            ->get(route('account.quote.show', $quote))
            ->assertOk()
            ->assertSeeText('This quote has been accepted and an invoice')
            ->assertSeeText('There is currently $110.00 outstanding.')
            ->assertSeeText('Unit (ex GST)')
            ->assertSeeText('Subtotal (ex GST)')
            ->assertSeeText('Total (inc GST)')
            ->assertSeeText('View Invoice')
            ->assertDontSeeText('already accepted');
    }

    public function test_customer_can_accept_and_go_to_the_linked_invoice(): void
    {
        $owner = User::factory()->create();
        /** @var Quote $quote */
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'status' => Quote::STATUS_OPEN,
            'quote_date' => now()->toDateString(),
            'line_items' => [[
                'kind' => 'custom',
                'description' => 'Workshop quote',
                'notes' => '',
                'quantity' => 1,
                'unit_price' => 100,
                'line_total' => 100,
                'gst_applicable' => true,
            ]],
            'subtotal_amount' => 100,
            'gst_amount' => 10,
            'total_amount' => 110,
            'context_payload' => [
                'acceptance' => [
                    'creates_order' => false,
                    'emails_invoice' => true,
                ],
            ],
        ]);

        $response = $this->actingAs($owner)
            ->post(route('account.quote.accept', $quote), [
                'accept_and_pay' => 1,
            ]);

        /** @var Quote $freshQuote */
        $freshQuote = Quote::query()->findOrFail($quote->id);
        $invoice = $freshQuote->invoices()->latest('id')->first();

        $response->assertRedirect(route('account.invoice.show', $invoice));
        $this->assertSame(Quote::STATUS_ACCEPTED, (string) $freshQuote->status);
        $this->assertNotNull($invoice);
        $this->assertSame($quote->id, (int) $invoice->quote_id);
    }

    public function test_expired_quote_review_page_shows_due_date_and_hides_response_actions(): void
    {
        $owner = User::factory()->create();
        /** @var Quote $quote */
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'status' => Quote::STATUS_OPEN,
            'quote_date' => now()->subDays(28)->toDateString(),
            'line_items' => [[
                'kind' => 'custom',
                'description' => 'Expired workshop quote',
                'notes' => '',
                'quantity' => 1,
                'unit_price' => 100,
                'line_total' => 100,
                'gst_applicable' => true,
            ]],
            'subtotal_amount' => 100,
            'gst_amount' => 10,
            'total_amount' => 110,
        ]);

        $workflow = app(QuoteWorkflowService::class);
        $reviewUrl = $workflow->quoteReviewUrl($quote);
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'test.stemmechanics.com.au';

        $this->withServerVariables(['HTTP_HOST' => $host, 'HTTPS' => 'on'])
            ->get($reviewUrl)
            ->assertOk()
            ->assertSeeText('Due date')
            ->assertSeeText(now()->subDays(28)->format('M j, Y'))
            ->assertSeeText('This quote has expired and can no longer be accepted. Please contact us to review your options.')
            ->assertDontSeeText('Accept Quote')
            ->assertDontSeeText('Cancel Quote');

        /** @var Quote $freshQuote */
        $freshQuote = Quote::query()->findOrFail($quote->id);
        $this->assertSame(Quote::STATUS_EXPIRED, (string) $freshQuote->status);
    }

    public function test_cancelled_quote_review_page_shows_cancelled_message_and_hides_response_actions(): void
    {
        $owner = User::factory()->create();
        /** @var Quote $quote */
        $quote = Quote::factory()->create([
            'user_id' => $owner->id,
            'status' => Quote::STATUS_CANCELLED,
            'quote_date' => now()->subDays(3)->toDateString(),
            'line_items' => [[
                'kind' => 'custom',
                'description' => 'Cancelled workshop quote',
                'notes' => '',
                'quantity' => 1,
                'unit_price' => 100,
                'line_total' => 100,
                'gst_applicable' => true,
            ]],
            'subtotal_amount' => 100,
            'gst_amount' => 10,
            'total_amount' => 110,
        ]);

        $workflow = app(QuoteWorkflowService::class);
        $reviewUrl = $workflow->quoteReviewUrl($quote);
        $host = parse_url((string) config('app.url'), PHP_URL_HOST) ?: 'test.stemmechanics.com.au';

        $this->withServerVariables(['HTTP_HOST' => $host, 'HTTPS' => 'on'])
            ->get($reviewUrl)
            ->assertOk()
            ->assertSeeText('This quote has been cancelled and is no longer available. Please contact us if you need to discuss it.')
            ->assertDontSeeText('Accept Quote')
            ->assertDontSeeText('Cancel Quote');

        /** @var Quote $freshQuote */
        $freshQuote = Quote::query()->findOrFail($quote->id);
        $this->assertSame(Quote::STATUS_CANCELLED, (string) $freshQuote->status);
    }
}
