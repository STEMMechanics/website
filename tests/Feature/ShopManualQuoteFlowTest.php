<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\FinanceDocumentPdf;
use App\Mail\InvoiceDocumentBundle;
use App\Mail\QuoteCustomerResponseAdminNotification;
use App\Mail\StoreQuoteRequestAdminNotification;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Quote;
use App\Models\SiteOption;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\QuoteWorkflowService;
use App\Support\ShopShippingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ShopManualQuoteFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_manual_quote_checkout_queues_admin_notification(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');
        SiteOption::ensureDefaultOptionsExist();
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::BOXED_AMOUNT_OPTION],
            ['value' => '']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::BOXED_LABEL_OPTION],
            ['value' => 'Manual quote']
        );

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Framed Poster',
            'price' => 24.95,
            'inventory_quantity' => 5,
            'shipping_units' => 0.0,
            'min_satchel_rank' => 1,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Large',
            'sku' => 'FRAMED-LARGE',
            'inventory_quantity' => 5,
            'sort_order' => 0,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
            'product_variant_id' => $variant->id,
        ]);

        $this->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Avery Example',
            'billing_email' => 'avery@example.com',
            'billing_phone' => '0400123456',
            'shipping_name' => 'Avery Example',
            'shipping_phone' => '0400123456',
            'shipping_address' => '123 Example Street',
            'shipping_city' => 'Brisbane',
            'shipping_state' => 'QLD',
            'shipping_postcode' => '4000',
            'shipping_country' => 'Australia',
            'shipping_method_code' => 'request_quote',
        ])->assertRedirect();

        $quote = Quote::query()->first();

        $this->assertInstanceOf(Quote::class, $quote);
        $this->assertSame(Quote::CONTEXT_STORE_MANUAL_SHIPPING, (string) $quote->context_type);
        $this->assertSame(Quote::STATUS_DRAFT, (string) $quote->status);
        $this->assertTrue((bool) $quote->acceptance_creates_order);
        $this->assertTrue((bool) $quote->acceptance_emails_invoice);
        $this->assertSame((int) $product->id, (int) data_get($quote->line_items, '0.source_id'));
        $this->assertSame((int) $variant->id, (int) data_get($quote->line_items, '0.source_variant_id'));
        $this->assertSame((int) $product->id, (int) data_get($quote->line_items, '0.store_context.product_id'));
        $this->assertSame((int) $variant->id, (int) data_get($quote->line_items, '0.store_context.variant_id'));
        $this->assertSame(0, StoreOrder::query()->count());

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'ops@example.com'
                && $job->mailable instanceof StoreQuoteRequestAdminNotification;
        });
    }

    public function test_quote_requested_order_page_hides_fulfilment_state_and_unknown_amounts(): void
    {
        $order = StoreOrder::factory()->create([
            'invoice_id' => null,
            'status' => StoreOrder::STATUS_QUOTE_REQUESTED,
            'contains_physical' => true,
            'contains_digital' => false,
            'shipping_method' => 'Regular shipping',
            'shipping_amount' => 0,
            'total_amount' => 49.90,
            'subtotal_amount' => 49.90,
            'gst_amount' => 4.54,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Framed Poster',
            'quantity' => 1,
            'line_price_amount' => 49.90,
            'line_total_amount' => 49.90,
            'line_gst_amount' => 4.54,
        ]);

        $this->get(route('shop.order.tracking', ['accessToken' => $order->access_token]))
            ->assertOk()
            ->assertSeeText('Quote requested')
            ->assertSeeText('To be quoted')
            ->assertSeeText('Shipping')
            ->assertSeeText('--')
            ->assertDontSeeText('A shipping quote has been requested for this order. We will review it and send the quote before payment is due.')
            ->assertDontSeeText('Awaiting shipping');
    }

    public function test_admin_can_create_and_email_a_quote_from_a_quote_requested_order(): void
    {
        Queue::fake();

        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);
        $customer = User::factory()->create([
            'email' => 'customer@example.com',
        ]);

        $order = StoreOrder::factory()->create([
            'user_id' => $customer->id,
            'invoice_id' => null,
            'status' => StoreOrder::STATUS_QUOTE_REQUESTED,
            'billing_name' => 'Customer Example',
            'billing_email' => 'customer@example.com',
            'billing_phone' => '0400123456',
            'shipping_method' => 'Manual quote',
            'shipping_amount' => 0,
            'gst_amount' => 4.54,
            'total_amount' => 49.90,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Framed Poster',
            'quantity' => 2,
            'unit_price' => 24.95,
            'line_price_amount' => 49.90,
            'line_total_amount' => 49.90,
            'line_gst_amount' => 4.54,
        ]);

        $response = $this->actingAs($admin)->post(route('admin.shop.order.quote.send', $order), [
            'shipping_amount' => '18.50',
            'email_message' => 'Attached is your shipping quote.',
        ]);

        $response->assertRedirect();

        $freshOrder = $order->fresh(['invoice.quote']);

        $this->assertNotNull($freshOrder->invoice);
        $this->assertNotNull($freshOrder->invoice?->quote);
        $this->assertSame(StoreOrder::STATUS_PENDING_PAYMENT, (string) $freshOrder->status);
        $this->assertSame(18.50, round((float) $freshOrder->shipping_amount, 2));

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'customer@example.com'
                && $job->mailable instanceof FinanceDocumentPdf
                && $job->mailable->documentType === 'quote';
        });
    }

    public function test_accepting_store_manual_quote_creates_order_and_emails_invoice(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $customer = User::factory()->create([
            'firstname' => 'Avery',
            'surname' => 'Example',
            'email' => 'avery@example.com',
        ]);
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Marbles',
            'sku' => 'MARBLES',
            'price' => 5.50,
            'inventory_quantity' => 8,
            'shipping_units' => 0.1,
            'weight_grams' => 100,
            'tax_rate' => 0.1,
        ]);

        $quote = Quote::factory()->create([
            'user_id' => $customer->id,
            'status' => Quote::STATUS_OPEN,
            'context_type' => Quote::CONTEXT_STORE_MANUAL_SHIPPING,
            'quote_date' => now()->toDateString(),
            'line_items' => [[
                'kind' => 'product',
                'description' => 'Marbles',
                'notes' => '',
                'quantity' => 2,
                'unit_price' => 5.00,
                'line_total' => 10.00,
                'gst_applicable' => true,
                'store_context' => [
                    'product_id' => $product->id,
                    'variant_id' => null,
                    'product_title' => 'Marbles',
                    'product_slug' => $product->slug,
                    'variant_name' => '',
                    'product_sku' => 'MARBLES',
                    'variant_sku' => '',
                    'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                    'box_only' => false,
                    'available_now_quantity' => 2,
                    'delayed_quantity' => 0,
                    'delayed_fulfilment_type' => null,
                    'delayed_shipping_estimate' => null,
                    'unit_shipping_units' => 0.1,
                    'unit_min_satchel_rank' => 1,
                    'unit_weight_grams' => 100,
                    'tax_rate' => 0.1,
                    'unit_price_inc_tax' => 5.50,
                    'line_price_inc_tax' => 11.00,
                ],
            ], [
                'kind' => 'shipping',
                'description' => 'Quoted shipping',
                'notes' => '',
                'quantity' => 1,
                'unit_price' => 9.09,
                'line_total' => 9.09,
                'gst_applicable' => true,
            ]],
            'subtotal_amount' => 19.09,
            'gst_amount' => 1.91,
            'total_amount' => 21.00,
            'context_payload' => [
                'acceptance' => [
                    'creates_order' => true,
                    'emails_invoice' => true,
                ],
                'customer' => [
                    'billing_name' => 'Avery Example',
                    'billing_email' => 'avery@example.com',
                    'billing_phone' => '0400123456',
                    'billing_company' => '',
                    'shipping_name' => 'Avery Example',
                    'shipping_phone' => '0400123456',
                    'shipping_address' => '123 Example Street',
                    'shipping_address2' => '',
                    'shipping_city' => 'Brisbane',
                    'shipping_state' => 'QLD',
                    'shipping_postcode' => '4000',
                    'shipping_country' => 'Australia',
                    'notes' => '',
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

        $freshQuote = $quote->fresh(['invoices']);
        $this->assertSame(Quote::STATUS_ACCEPTED, (string) $freshQuote?->status);
        $this->assertNotNull($freshQuote?->accepted_at);
        $this->assertCount(1, $freshQuote?->invoices ?? []);
        $freshOrder = StoreOrder::query()->first();
        $this->assertInstanceOf(StoreOrder::class, $freshOrder);
        $this->assertSame((int) $freshQuote->id, (int) $freshOrder->quote_id);
        $this->assertSame(1, StoreOrder::query()->count());

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'avery@example.com'
                && $job->mailable instanceof InvoiceDocumentBundle;
        });
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'ops@example.com'
                && $job->mailable instanceof QuoteCustomerResponseAdminNotification;
        });
    }
}
