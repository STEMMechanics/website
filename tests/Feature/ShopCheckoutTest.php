<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\SiteOption;
use App\Models\ProductVariant;
use App\Models\Quote;
use App\Models\StoreOrder;
use App\Models\User;
use App\Support\ShopShippingSettings;
use App\Services\SquareApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ShopCheckoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_paid_shop_checkout_stays_on_checkout_until_card_details_are_provided(): void
    {
        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 19.95,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 2,
        ])->assertRedirect(route('shop.cart.show'));

        $response = $this->from(route('shop.checkout'))->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Avery Example',
            'billing_email' => 'avery@example.com',
            'billing_phone' => '0400123456',
            'notes' => 'Please email me once it is ready.',
        ]);

        $response->assertRedirect(route('shop.checkout'));
        $response->assertSessionHasErrors('source_id');
        $this->assertSame(0, StoreOrder::query()->count());

        $this->get(route('shop.checkout'))
            ->assertOk()
            ->assertSee('Order Details')
            ->assertSee('Step 2 of 2')
            ->assertSee('Payment Details')
            ->assertSee('Add voucher')
            ->assertSee('GST Included')
            ->assertSee('Place Order');

        $this->get(route('shop.checkout.payment'))
            ->assertRedirect(route('shop.checkout'));
    }

    public function test_failed_shop_checkout_payment_keeps_the_cart_and_does_not_create_an_order(): void
    {
        Queue::fake();

        config()->set('services.square.enabled', true);
        config()->set('services.square.location_id', 'L123');
        config()->set('services.square.application_id', 'A123');

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        $squareApi->shouldReceive('createPayment')->once()->andThrow(new \RuntimeException('Card was declined.'));
        $squareApi->shouldReceive('userFacingPaymentErrorMessage')->andReturnUsing(fn (string $message) => $message);
        $this->app->instance(SquareApiService::class, $squareApi);

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 19.95,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $response = $this->from(route('shop.checkout.payment'))->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Avery Example',
            'billing_email' => 'avery@example.com',
            'billing_phone' => '0400123456',
            'shipping_country' => 'Australia',
            'source_id' => 'cnon:card-nonce-ok',
        ]);

        $response->assertRedirect(route('shop.checkout'));
        $response->assertSessionHasErrors('source_id');

        $errors = session('errors');
        $this->assertNotNull($errors);
        $this->assertStringContainsString('Reference:', (string) $errors->getBag('default')->first('source_id'));
        $this->assertSame(0, StoreOrder::query()->count());

        $this->getJson(route('shop.cart.show', ['shipping_country' => 'Australia']))
            ->assertOk()
            ->assertJsonPath('cart.summary.item_count', 1);
    }

    public function test_logged_in_shop_checkout_uses_account_credit_without_card_details_when_credit_covers_the_total(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'firstname' => 'Credit',
            'surname' => 'Customer',
            'email' => 'credit-customer@example.com',
        ]);

        Payment::factory()->create([
            'user_id' => $user->id,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT,
            'total_amount' => 30.00,
            'gst_amount' => 0.00,
            'reference' => 'Account credit grant',
        ]);

        /** @var Product $product */
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 19.95,
        ]);

        $this->actingAs($user)
            ->post(route('shop.cart.add', $product), [
                'quantity' => 1,
            ])->assertRedirect(route('shop.cart.show'));

        $response = $this->actingAs($user)->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Credit Customer',
            'billing_email' => 'credit-customer@example.com',
            'billing_phone' => '0400111222',
            'shipping_country' => 'Australia',
        ]);

        $order = StoreOrder::query()->sole();

        $response->assertRedirect(route('account.order.show', $order));
        $response->assertSessionHas(
            'message',
            'Payment completed successfully. Your order email and receipt have been emailed.'
        );
        $this->assertTrue($order->isPaid());
        $this->assertSame(1, InvoicePaymentAllocation::query()->where('invoice_id', $order->invoice_id)->count());
        $this->assertSame(19.95, (float) InvoicePaymentAllocation::query()->where('invoice_id', $order->invoice_id)->sum('allocated_amount'));

    }

    public function test_logged_in_shop_checkout_can_place_the_order_on_account_terms(): void
    {
        Queue::fake();

        $user = User::factory()->create([
            'firstname' => 'Terms',
            'surname' => 'Customer',
            'email' => 'terms-customer@example.com',
            'account_terms_days' => 14,
        ]);

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 19.95,
        ]);

        $this->actingAs($user)
            ->post(route('shop.cart.add', $product), [
                'quantity' => 1,
            ])->assertRedirect(route('shop.cart.show'));

        $this->travelTo(now()->setDate(2026, 4, 1)->setTime(10, 0));

        $response = $this->actingAs($user)->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Terms Customer',
            'billing_email' => 'terms-customer@example.com',
            'billing_phone' => '0400111222',
            'payment_method' => 'account_terms',
        ]);

        $order = StoreOrder::query()->sole();
        $order->load('invoice');

        $response->assertRedirect(route('account.order.show', $order));
        $this->assertSame(StoreOrder::STATUS_PENDING_PAYMENT, (string) $order->status);
        $this->assertSame('2026-04-15', optional($order->invoice?->due_date)->toDateString());
        $this->assertSame(0, Payment::query()->count());
    }

    public function test_digital_products_can_be_added_in_multiple_quantities(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 19.95,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 5,
        ])->assertRedirect(route('shop.cart.show'));

        $this->getJson(route('shop.cart.show', ['shipping_country' => 'Australia']))
            ->assertOk()
            ->assertJsonPath('cart.lines.0.quantity', 5)
            ->assertJsonPath('cart.lines.0.max_quantity', 99)
            ->assertJsonPath('cart.lines.0.is_digital', true);

        $this->get(route('shop.cart.show'))
            ->assertOk()
            ->assertSee('Qty')
            ->assertDontSee('1 licence');
    }

    public function test_digital_product_page_shows_licence_tiers(): void
    {
        /** @var Product $product */
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'title' => 'Digital Project Pack',
            'price' => 12.00,
            'base_variant_name' => 'Home Licence',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Classroom Licence',
            'description' => 'For one classroom.',
            'price' => 60.00,
            'sort_order' => 0,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Organisation Licence',
            'description' => 'For one school.',
            'price' => 240.00,
            'sort_order' => 1,
        ]);

        $this->get(route('shop.product.show', $product))
            ->assertOk()
            ->assertSeeText('Choose a licence')
            ->assertSeeText('Home Licence')
            ->assertSeeText('Classroom Licence')
            ->assertSeeText('Organisation Licence')
            ->assertSeeText('For one classroom.')
            ->assertSeeText('For one school.')
            ->assertSeeText('Add to Cart')
            ->assertSeeText('Instant download after checkout')
            ->assertDontSee('type="radio"', false)
            ->assertDontSee('id="product-variant-select"', false);
    }

    public function test_physical_variant_without_inventory_shows_sold_out_on_product_page(): void
    {
        /** @var Product $product */
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Class Kit',
            'price' => 24.95,
            'inventory_quantity' => 5,
            'base_variant_name' => 'Starter Kit',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Extended Kit',
            'price' => 29.95,
            'inventory_quantity' => null,
            'sort_order' => 0,
        ]);

        $this->get(route('shop.product.show', $product))
            ->assertOk()
            ->assertSeeText('Extended Kit')
            ->assertSeeText('Out of stock')
            ->assertSeeText('Sold out')
            ->assertSee('fa-circle-xmark', false)
            ->assertSee('text-red-700', false);
    }

    public function test_product_page_shows_variant_specific_backorder_dates(): void
    {
        /** @var Product $product */
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Class Kit',
            'price' => 24.95,
            'inventory_quantity' => 5,
            'base_variant_name' => 'Starter Kit',
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Legacy Blue',
            'price' => 29.95,
            'inventory_quantity' => null,
            'is_preorder' => true,
            'preorder_shipping_estimate' => '2026-05-15',
            'sort_order' => 0,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Backorder Red',
            'price' => 27.95,
            'inventory_quantity' => 0,
            'allow_backorder' => true,
            'backorder_shipping_estimate' => '2026-05-20',
            'sort_order' => 1,
        ]);

        $this->get(route('shop.product.show', $product))
            ->assertOk()
            ->assertSeeText('Legacy Blue')
            ->assertSeeText('Available to order. More expected May 15th')
            ->assertSeeText('Backorder Red')
            ->assertSeeText('Available to order. More expected May 20th')
            ->assertDontSeeText('Pre-order');
    }

    public function test_single_option_product_page_shows_price_and_listing_style_cart_control(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Microbit Base',
            'price' => 24.95,
            'inventory_quantity' => 10,
        ]);

        $this->get(route('shop.product.show', $product))
            ->assertOk()
            ->assertSeeText('Microbit Base')
            ->assertSeeText('$24.95')
            ->assertSeeText('In stock')
            ->assertSeeText('Add to Cart')
            ->assertDontSee('id="product-quantity"', false);
    }

    public function test_product_page_hides_backorder_dates_when_stock_is_above_the_low_stock_threshold(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Robotics Pack',
            'price' => 49.95,
            'inventory_quantity' => 10,
            'allow_backorder' => true,
            'backorder_shipping_estimate' => '2026-04-13',
        ]);

        $this->get(route('shop.product.show', $product))
            ->assertOk()
            ->assertSeeText('In stock')
            ->assertDontSeeText('More expected')
            ->assertDontSeeText('Available to order.');
    }

    public function test_cart_payload_marks_physical_items_for_checkout_state(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 24.95,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->getJson(route('shop.cart.show', ['shipping_country' => 'Australia']))
            ->assertOk()
            ->assertJsonPath('cart.summary.contains_physical', true)
            ->assertJsonPath('cart.summary.contains_digital', false);
    }

    public function test_checkout_does_not_show_internal_shipping_packaging_labels_in_item_list(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Microbit Base',
            'price' => 24.95,
            'inventory_quantity' => 8,
            'shipping_units' => 1.0,
            'min_satchel_rank' => 1,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->get(route('shop.checkout'))
            ->assertOk()
            ->assertDontSeeText('Fits Small package or larger')
            ->assertDontSeeText('Final shipping is based on the whole cart.');
    }

    public function test_manual_quote_checkout_hides_payment_and_marks_triggering_items(): void
    {
        SiteOption::ensureDefaultOptionsExist();
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::BOXED_AMOUNT_OPTION],
            ['value' => '']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::BOXED_LABEL_OPTION],
            ['value' => 'Manual quote']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::BOXED_MESSAGE_OPTION],
            ['value' => 'Manual shipping quote required.']
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

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->get(route('shop.checkout'))
            ->assertOk()
            ->assertSeeText('Request quote')
            ->assertSeeText('Pickup')
            ->assertSeeText('Step 1 of 1')
            ->assertSeeText('Request Quote')
            ->assertSeeText('Requires pickup or a manual shipping quote')
            ->assertSeeText('This item requires pickup or a manual shipping quote.')
            ->assertDontSeeText('Regular shipping')
            ->assertDontSeeText('Some physical products do not have package units configured.')
            ->assertSeeText('--');
    }

    public function test_manual_quote_checkout_creates_a_store_quote_without_creating_an_order(): void
    {
        SiteOption::ensureDefaultOptionsExist();
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::BOXED_AMOUNT_OPTION],
            ['value' => '']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::BOXED_LABEL_OPTION],
            ['value' => 'Manual quote']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::BOXED_MESSAGE_OPTION],
            ['value' => 'Manual shipping quote required.']
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

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $response = $this->post(route('shop.checkout.place-order'), [
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
        ]);

        $quote = Quote::query()->firstOrFail();

        $response->assertRedirect(route('shop.index'));

        $this->assertSame(\App\Models\Quote::CONTEXT_STORE_MANUAL_SHIPPING, (string) $quote->context_type);
        $this->assertSame(\App\Models\Quote::STATUS_DRAFT, (string) $quote->status);
        $this->assertTrue((bool) $quote->acceptance_creates_order);
        $this->assertTrue((bool) $quote->acceptance_emails_invoice);
        $this->assertSame('avery@example.com', (string) data_get($quote->context_payload, 'customer.billing_email'));
        $this->assertSame(0, StoreOrder::query()->count());
        $this->getJson(route('shop.cart.show', ['shipping_country' => 'Australia']))
            ->assertOk()
            ->assertJsonPath('cart.summary.item_count', 0);
    }

    public function test_manual_quote_notice_only_appears_on_the_triggering_item_in_a_mixed_cart(): void
    {
        SiteOption::ensureDefaultOptionsExist();
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::BOXED_AMOUNT_OPTION],
            ['value' => '']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::BOXED_LABEL_OPTION],
            ['value' => 'Manual quote']
        );
        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::BOXED_MESSAGE_OPTION],
            ['value' => 'Manual shipping quote required.']
        );

        $manualQuoteItem = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Framed Poster',
            'price' => 24.95,
            'inventory_quantity' => 5,
            'shipping_units' => 0.0,
            'min_satchel_rank' => 1,
        ]);
        $regularItem = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'title' => 'Microbit Base',
            'price' => 24.95,
            'inventory_quantity' => 5,
            'shipping_units' => 1.0,
            'min_satchel_rank' => 1,
        ]);

        $this->post(route('shop.cart.add', $manualQuoteItem), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));
        $this->post(route('shop.cart.add', $regularItem), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $response = $this->get(route('shop.checkout'));

        $response->assertOk()
            ->assertSeeText('Framed Poster')
            ->assertSeeText('Microbit Base')
            ->assertSeeText('Requires pickup or a manual shipping quote');

        $this->assertSame(
            2,
            substr_count($response->getContent(), 'Requires pickup or a manual shipping quote')
        );
    }

    public function test_invalid_discount_code_is_not_kept_as_applied(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 24.95,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->from(route('shop.checkout'))->post(route('shop.cart.coupon.apply'), [
            'coupon_code' => 'NOTREAL',
            'shipping_country' => 'Australia',
            'return_to' => route('shop.checkout'),
        ])->assertRedirect(route('shop.checkout'));

        $this->getJson(route('shop.cart.show', ['shipping_country' => 'Australia']))
            ->assertOk()
            ->assertJsonPath('cart.summary.coupon_code', null)
            ->assertJsonPath('cart.summary.discount', 0);

        $this->get(route('shop.checkout'))
            ->assertOk()
            ->assertSee('Add voucher')
            ->assertDontSee('Applied voucher:');
    }

    public function test_physical_checkout_only_accepts_australian_shipping_addresses(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 24.95,
            'shipping_units' => 0.5,
            'min_satchel_rank' => 1,
            'weight_grams' => 250,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->from(route('shop.checkout'))->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Avery Example',
            'billing_email' => 'avery@example.com',
            'billing_phone' => '0400123456',
            'shipping_name' => 'Avery Example',
            'shipping_phone' => '0400123456',
            'shipping_address' => '123 Example Street',
            'shipping_city' => 'Brisbane',
            'shipping_state' => 'QLD',
            'shipping_postcode' => '4000',
            'shipping_country' => 'New Zealand',
            'shipping_method_code' => 'regular',
        ])
            ->assertRedirect(route('shop.checkout'))
            ->assertSessionHasErrors('shipping_country');

        $this->assertSame(0, StoreOrder::query()->count());
    }

    public function test_checkout_does_not_prefill_non_australian_profile_address_details(): void
    {
        $user = User::factory()->create([
            'shipping_country' => 'New Zealand',
            'shipping_address' => '1 Queen Street',
            'shipping_address2' => 'Unit 4',
            'shipping_city' => 'Auckland',
            'shipping_state' => 'AUK',
            'shipping_postcode' => '1010',
        ]);

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 24.95,
            'shipping_units' => 0.5,
            'min_satchel_rank' => 1,
            'weight_grams' => 250,
        ]);

        $this->actingAs($user)
            ->post(route('shop.cart.add', $product), [
                'quantity' => 1,
            ])->assertRedirect(route('shop.cart.show'));

        $this->actingAs($user)
            ->get(route('shop.checkout'))
            ->assertOk()
            ->assertSee('Select state')
            ->assertDontSee('1 Queen Street')
            ->assertDontSee('Unit 4')
            ->assertDontSee('Auckland')
            ->assertDontSee('1010');
    }

    public function test_physical_checkout_requires_valid_australian_state_and_four_digit_postcode(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 24.95,
            'shipping_units' => 0.5,
            'min_satchel_rank' => 1,
            'weight_grams' => 250,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->from(route('shop.checkout'))->post(route('shop.checkout.place-order'), [
            'billing_name' => 'Avery Example',
            'billing_email' => 'avery@example.com',
            'billing_phone' => '0400123456',
            'shipping_name' => 'Avery Example',
            'shipping_phone' => '0400123456',
            'shipping_address' => '123 Example Street',
            'shipping_city' => 'Brisbane',
            'shipping_state' => 'Atlantis',
            'shipping_postcode' => '40000',
            'shipping_country' => 'Australia',
            'shipping_method_code' => 'regular',
        ])
            ->assertRedirect(route('shop.checkout'))
            ->assertSessionHasErrors(['shipping_state', 'shipping_postcode']);

        $this->assertSame(0, StoreOrder::query()->count());
    }

    public function test_checkout_renders_contact_to_recipient_prefill_hooks_for_shipping_fields(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 24.95,
            'shipping_units' => 0.5,
            'min_satchel_rank' => 1,
            'weight_grams' => 250,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->get(route('shop.checkout'))
            ->assertOk()
            ->assertSee("syncRecipientField('billing_name', 'shipping_name')", false)
            ->assertSee("syncRecipientField('billing_phone', 'shipping_phone')", false)
            ->assertSee("markRecipientFieldEdited('shipping_name', 'billing_name')", false)
            ->assertSee("markRecipientFieldEdited('shipping_phone', 'billing_phone')", false);
    }

    public function test_checkout_disables_cart_and_voucher_actions_while_payment_submit_is_running(): void
    {
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 24.95,
            'shipping_units' => 0.5,
            'min_satchel_rank' => 1,
            'weight_grams' => 250,
        ]);

        $this->post(route('shop.cart.add', $product), [
            'quantity' => 1,
        ])->assertRedirect(route('shop.cart.show'));

        $this->get(route('shop.checkout'))
            ->assertOk()
            ->assertSee(':disabled="busyLineKey === line.key || isSubmitting"', false)
            ->assertSee(':disabled="couponBusy || isSubmitting"', false)
            ->assertSee('if (this.isSubmitting || this.busyLineKey || !window.SM?.shopCart)', false)
            ->assertSee('if (this.isSubmitting || this.couponBusy || !(form instanceof HTMLFormElement) || !window.SM?.shopCart)', false);
    }
}
