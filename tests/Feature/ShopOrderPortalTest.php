<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\InvoiceDocumentBundle;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemTracking;
use App\Models\TaxAdjustment;
use App\Models\TaxAdjustmentLine;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ShopOrderPortalTest extends TestCase
{
    use RefreshDatabase;

    private const ORDER_DOCUMENT_ACCESS_SESSION_KEY = 'store.order.document-access-tokens';

    public function test_guest_order_portal_hides_address_details_but_shows_public_updates(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Casey Customer',
            'billing_email' => 'casey@example.com',
            'billing_phone' => '0400123123',
            'subtotal_amount' => 45.00,
            'gst_amount' => 4.09,
            'total_amount' => 45.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_READY_FOR_PICKUP,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_name' => 'Casey Customer',
            'billing_email' => 'casey@example.com',
            'billing_phone' => '0400123123',
            'shipping_name' => 'Casey Customer',
            'shipping_phone' => '0400123123',
            'shipping_address' => '123 Hidden Street',
            'shipping_city' => 'Brisbane',
            'shipping_state' => 'QLD',
            'shipping_postcode' => '4000',
            'shipping_country' => 'Australia',
            'shipping_method' => 'Pick up',
            'shipping_method_code' => 'pickup',
            'shipping_package_summary' => '1 x Small Satchel',
            'shipping_chargeable_weight_grams' => 1250,
            'public_notes' => "Packed and waiting at the studio.\nBring photo ID for collection.",
            'total_amount' => 45.00,
            'subtotal_amount' => 45.00,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'gst_amount' => 4.09,
            'paid_at' => now(),
        ]);

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'title' => 'Pickup Kit',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 45.00,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Pickup Kit',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 1,
            'line_total_amount' => 45.00,
            'line_price_amount' => 45.00,
            'line_shipping_amount' => 0,
            'line_gst_amount' => 4.09,
        ]);

        $payment = Payment::factory()->create([
            'total_amount' => 45.00,
            'gst_amount' => 4.09,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 45.00,
        ]);

        $this->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]))
            ->assertOk()
            ->assertSee('Ready for Pickup')
            ->assertSee('Order Updates')
            ->assertSee('Packed and waiting at the studio.')
            ->assertSee('Bring photo ID for collection.')
            ->assertDontSee('123 Hidden Street')
            ->assertDontSee('casey@example.com')
            ->assertDontSee('0400123123')
            ->assertDontSee('Details')
            ->assertDontSee('Step 2 of 2')
            ->assertDontSee('1 x Small Satchel')
            ->assertDontSee('Known packed weight')
            ->assertDontSee('Keep this page URL if you checked out as a guest.')
            ->assertSee('Log in to view the saved address details for this order.');
    }

    public function test_guest_order_portal_shows_clean_shipping_history_and_cancelled_quantities(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Jordan Customer',
            'billing_email' => 'jordan@example.com',
            'billing_phone' => '0400999888',
            'subtotal_amount' => 65.00,
            'gst_amount' => 5.91,
            'total_amount' => 65.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'shipping_breakdown_data' => [
                'delivery_estimate_label' => '3-7 business days',
            ],
            'shipping_package_summary' => '2 x Small Satchel',
            'shipping_chargeable_weight_grams' => 2150,
            'total_amount' => 65.00,
            'subtotal_amount' => 65.00,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'gst_amount' => 5.91,
            'paid_at' => now(),
        ]);

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'title' => 'Split Parcel Pack',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 65.00,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Split Parcel Pack',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 3,
            'available_now_quantity' => 2,
            'delayed_quantity' => 1,
            'cancelled_delayed_quantity' => 1,
            'inventory_reserved_quantity' => 0,
            'line_total_amount' => 65.00,
            'line_price_amount' => 65.00,
            'line_shipping_amount' => 0,
            'line_gst_amount' => 5.91,
        ]);

        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $item->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 2,
            'carrier' => 'Australia Post',
            'tracking_number' => 'TRACK-PORTAL-1',
            'tracking_url' => 'https://tracking.example.test/portal',
            'notes' => 'Two units shipped in the first parcel.',
            'dispatched_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'total_amount' => 65.00,
            'gst_amount' => 5.91,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 65.00,
        ]);

        $this->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]))
            ->assertOk()
            ->assertSee('Recorded shipments')
            ->assertSee('Delivery 1')
            ->assertSee('TRACK-PORTAL-1')
            ->assertSee('Australia Post')
            ->assertSee('Estimated arrival: 3-7 business days')
            ->assertSee('Items in this delivery')
            ->assertSee('Split Parcel Pack')
            ->assertSee('Cancelled qty 1')
            ->assertDontSee('Backorder dispatch')
            ->assertDontSee('Tracking added for 2 qty')
            ->assertDontSee('2 x Small Satchel')
            ->assertDontSee('Known packed weight');
    }

    public function test_guest_order_portal_shows_same_day_manual_shipments_as_separate_deliveries(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Jordan Customer',
            'billing_email' => 'jordan@example.com',
            'billing_phone' => '0400999888',
            'subtotal_amount' => 65.00,
            'gst_amount' => 5.91,
            'total_amount' => 65.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'shipping_breakdown_data' => [
                'delivery_estimate_label' => '3-7 business days',
            ],
            'paid_at' => now(),
        ]);

        $itemOne = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Microbit',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 0,
        ]);
        $itemTwo = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Pinball Template',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 0,
        ]);

        $dispatchedAt = now()->subDay()->startOfDay()->addHours(10);

        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $itemOne->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'carrier' => 'Australia Post',
            'notes' => 'Packed in the main satchel.',
            'dispatched_at' => $dispatchedAt,
        ]);
        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $itemTwo->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'carrier' => 'Australia Post',
            'notes' => 'Second line in the same parcel.',
            'dispatched_at' => $dispatchedAt->copy()->addMinutes(15),
        ]);

        $response = $this->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]));

        $response
            ->assertOk()
            ->assertSee('Recorded shipments')
            ->assertSee('Delivery 1')
            ->assertSee('Delivery 2')
            ->assertSee('Microbit')
            ->assertSee('Pinball Template')
            ->assertSee('Packed in the main satchel.')
            ->assertSee('Second line in the same parcel.');

        $this->assertSame(1, substr_count($response->getContent(), 'Delivery 1'));
        $this->assertSame(1, substr_count($response->getContent(), 'Delivery 2'));
    }

    public function test_guest_order_portal_groups_matching_parcel_numbers_into_one_parcel_section(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Jordan Customer',
            'billing_email' => 'jordan@example.com',
            'billing_phone' => '0400999888',
            'subtotal_amount' => 65.00,
            'gst_amount' => 5.91,
            'total_amount' => 65.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'shipping_breakdown_data' => [
                'delivery_estimate_label' => '3-7 business days',
            ],
            'paid_at' => now(),
        ]);

        $itemOne = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Microbit',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 0,
        ]);
        $itemTwo = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Pinball Template',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 0,
        ]);

        $dispatchedAt = now()->subDay()->startOfDay()->addHours(10);

        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $itemOne->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'parcel_number' => 2,
            'carrier' => 'Australia Post',
            'notes' => 'Packed in parcel two.',
            'dispatched_at' => $dispatchedAt,
        ]);
        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $itemTwo->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'parcel_number' => 2,
            'carrier' => 'Australia Post',
            'notes' => 'Second line in the same parcel.',
            'dispatched_at' => $dispatchedAt->copy()->addMinutes(15),
        ]);

        $response = $this->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]));

        $response
            ->assertOk()
            ->assertSee('Recorded shipments')
            ->assertSee('Delivery 1')
            ->assertSee('Microbit')
            ->assertSee('Pinball Template')
            ->assertSee('Packed in parcel two.');

        $this->assertSame(1, substr_count($response->getContent(), 'Delivery 1'));
    }

    public function test_guest_order_portal_hides_document_downloads_but_offers_to_email_them(): void
    {
        $invoice = Invoice::factory()->create([
            'invoice_number' => '8650',
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'billing_phone' => '0400555666',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'total_amount' => 84.00,
            'subtotal_amount' => 72.00,
            'shipping_amount' => 12.00,
            'discount_amount' => 0,
            'gst_amount' => 7.64,
            'paid_at' => now(),
        ]);

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'title' => 'Documented Kit',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 72.00,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Documented Kit',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 1,
            'line_total_amount' => 72.00,
            'line_price_amount' => 72.00,
            'line_shipping_amount' => 12.00,
            'line_gst_amount' => 7.64,
        ]);

        $payment = Payment::factory()->create([
            'received_on' => now()->subDay(),
            'total_amount' => 84.00,
            'gst_amount' => 7.64,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 84.00,
        ]);

        $this->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]))
            ->assertOk()
            ->assertSee('Documents')
            ->assertSee('Email Documents to Order Owner')
            ->assertDontSee('Download Tax Invoice')
            ->assertDontSee('Download Receipt #'.$payment->id)
            ->assertDontSee('/invoices/'.$invoice->invoice_number.'/pdf', false)
            ->assertDontSee('/invoices/'.$invoice->invoice_number.'/receipts/'.$payment->id.'/pdf', false);
    }

    public function test_same_checkout_session_can_still_download_order_documents(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'billing_phone' => '0400555666',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'total_amount' => 84.00,
            'subtotal_amount' => 72.00,
            'shipping_amount' => 12.00,
            'discount_amount' => 0,
            'gst_amount' => 7.64,
            'paid_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'received_on' => now()->subDay(),
            'total_amount' => 84.00,
            'gst_amount' => 7.64,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 84.00,
        ]);

        $this->withSession([
            self::ORDER_DOCUMENT_ACCESS_SESSION_KEY => [$order->access_token],
        ])->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]))
            ->assertOk()
            ->assertSee('Download Tax Invoice')
            ->assertSee('Download Receipt #'.$payment->id);
    }

    public function test_guest_tracking_page_hides_cancelled_item_refund_receipts_without_document_access(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_PARTIALLY_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'paid_at' => now(),
        ]);

        $productLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Microbit',
            'quantity' => 1,
            'line_total_inc_tax' => 12.00,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'invoice_line_id' => $productLine->id,
            'product_title' => 'Microbit',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 1,
            'available_now_quantity' => 0,
            'delayed_quantity' => 1,
            'cancelled_delayed_quantity' => 1,
            'line_total_amount' => 12.00,
            'line_price_amount' => 12.00,
            'line_shipping_amount' => 0,
            'line_gst_amount' => 1.09,
        ]);

        $payment = Payment::factory()->create([
            'total_amount' => 84.00,
            'gst_amount' => 7.64,
        ]);
        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 84.00,
        ]);

        $adjustment = TaxAdjustment::factory()->create([
            'invoice_id' => $invoice->id,
            'total_amount' => -12.00,
            'gst_amount' => -1.09,
        ]);
        TaxAdjustmentLine::factory()->create([
            'tax_adjustment_id' => $adjustment->id,
            'invoice_line_id' => $productLine->id,
            'line_total_inc_tax' => 12.00,
            'tax_amount' => 1.09,
        ]);

        $refundPayment = Payment::factory()->create([
            'kind' => Payment::KIND_REFUND,
            'refund_of_payment_id' => $payment->id,
            'received_on' => now()->subDay(),
            'total_amount' => 12.00,
            'gst_amount' => 0,
        ]);
        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'tax_adjustment_id' => $adjustment->id,
            'allocated_amount' => -12.00,
        ]);

        $this->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]))
            ->assertOk()
            ->assertSee('Cancelled qty 1')
            ->assertDontSee('Refund Receipt #'.$refundPayment->id);
    }

    public function test_same_checkout_session_can_view_cancelled_item_refund_receipts(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_PARTIALLY_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'paid_at' => now(),
        ]);

        $productLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'description' => 'Microbit',
            'quantity' => 1,
            'line_total_inc_tax' => 12.00,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'invoice_line_id' => $productLine->id,
            'product_title' => 'Microbit',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 1,
            'available_now_quantity' => 0,
            'delayed_quantity' => 1,
            'cancelled_delayed_quantity' => 1,
            'line_total_amount' => 12.00,
            'line_price_amount' => 12.00,
            'line_shipping_amount' => 0,
            'line_gst_amount' => 1.09,
        ]);

        $payment = Payment::factory()->create([
            'total_amount' => 84.00,
            'gst_amount' => 7.64,
        ]);
        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 84.00,
        ]);

        $adjustment = TaxAdjustment::factory()->create([
            'invoice_id' => $invoice->id,
            'total_amount' => -12.00,
            'gst_amount' => -1.09,
        ]);
        TaxAdjustmentLine::factory()->create([
            'tax_adjustment_id' => $adjustment->id,
            'invoice_line_id' => $productLine->id,
            'line_total_inc_tax' => 12.00,
            'tax_amount' => 1.09,
        ]);

        $refundPayment = Payment::factory()->create([
            'kind' => Payment::KIND_REFUND,
            'refund_of_payment_id' => $payment->id,
            'received_on' => now()->subDay(),
            'total_amount' => 12.00,
            'gst_amount' => 0,
        ]);
        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'tax_adjustment_id' => $adjustment->id,
            'allocated_amount' => -12.00,
        ]);

        $this->withSession([
            self::ORDER_DOCUMENT_ACCESS_SESSION_KEY => [$order->access_token],
        ])->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]))
            ->assertOk()
            ->assertSee('Cancelled qty 1')
            ->assertSee('Refund Receipt #'.$refundPayment->id);
    }

    public function test_same_checkout_session_can_view_saved_shipping_address_details(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'billing_phone' => '0400555666',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'shipping_name' => 'Robin Customer',
            'shipping_address' => '12 Session Street',
            'shipping_city' => 'Brisbane',
            'shipping_state' => 'QLD',
            'shipping_postcode' => '4000',
            'shipping_country' => 'Australia',
            'paid_at' => now(),
        ]);

        $this->withSession([
            self::ORDER_DOCUMENT_ACCESS_SESSION_KEY => [$order->access_token],
        ])->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]))
            ->assertOk()
            ->assertSee('12 Session Street')
            ->assertSee('Brisbane, QLD, 4000')
            ->assertDontSee('Log in to view the saved address details for this order.');
    }

    public function test_order_owner_can_view_saved_shipping_address_details_from_tracking_link(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'billing_phone' => '0400555666',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $owner = User::factory()->create([
            'email' => 'robin@example.com',
        ]);

        $order = StoreOrder::factory()->create([
            'user_id' => $owner->id,
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'shipping_name' => 'Robin Customer',
            'shipping_address' => '88 Owner Lane',
            'shipping_city' => 'Brisbane',
            'shipping_state' => 'QLD',
            'shipping_postcode' => '4000',
            'shipping_country' => 'Australia',
            'paid_at' => now(),
        ]);

        $this->actingAs($owner)
            ->get(route('shop.order.tracking', [
                'accessToken' => $order->access_token,
            ]))
            ->assertOk()
            ->assertSee('88 Owner Lane')
            ->assertSee('Brisbane, QLD, 4000')
            ->assertDontSee('Log in to view the saved address details for this order.');
    }

    public function test_unrelated_logged_in_user_with_tracking_link_still_cannot_download_documents(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'billing_phone' => '0400555666',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'total_amount' => 84.00,
            'subtotal_amount' => 72.00,
            'shipping_amount' => 12.00,
            'discount_amount' => 0,
            'gst_amount' => 7.64,
            'paid_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'received_on' => now()->subDay(),
            'total_amount' => 84.00,
            'gst_amount' => 7.64,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 84.00,
        ]);

        $this->actingAs(User::factory()->create())
            ->get(route('shop.order.tracking', [
                'accessToken' => $order->access_token,
            ]))
            ->assertOk()
            ->assertSee('Email Documents to Order Owner')
            ->assertDontSee('Download Tax Invoice')
            ->assertDontSee('Download Receipt #'.$payment->id);
    }

    public function test_tracking_page_can_email_documents_to_the_order_owner(): void
    {
        Queue::fake();

        $invoice = Invoice::factory()->create([
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'billing_phone' => '0400555666',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'paid_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'received_on' => now()->subDay(),
            'total_amount' => 84.00,
            'gst_amount' => 7.64,
        ]);

        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 84.00,
        ]);

        $returnTo = route('shop.order.tracking', ['accessToken' => $order->access_token]);

        $this->post(route('invoice.public.email-documents', $invoice), [
            'return_to' => $returnTo,
        ])
            ->assertRedirect($returnTo);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'robin@example.com'
                && $job->mailable instanceof InvoiceDocumentBundle;
        });

        $this->assertSame(
            'Your invoice 8650 is ready from STEMMechanics',
            (new InvoiceDocumentBundle('Robin Customer', '8650', []))->build()->subject
        );
    }

    public function test_shipped_preorder_item_no_longer_shows_preorder_eta_on_order_page(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'billing_phone' => '0400555666',
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'paid_at' => now(),
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Pre-order Bot',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'is_preorder' => true,
            'preorder_shipping_estimate' => '2026-05-05',
            'quantity' => 1,
            'available_now_quantity' => 0,
            'delayed_quantity' => 1,
            'inventory_reserved_quantity' => 0,
        ]);

        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $item->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED,
            'quantity' => 1,
            'carrier' => 'Australia Post',
            'tracking_number' => 'PRE-TRACK-1',
            'tracking_url' => 'https://tracking.example.test/pre',
            'dispatched_at' => now(),
        ]);

        $response = $this->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]));

        $response->assertOk()
            ->assertSee('Recorded shipments')
            ->assertSee('Delivery 1')
            ->assertSee('PRE-TRACK-1')
            ->assertSee('Australia Post')
            ->assertSee('Items in this delivery')
            ->assertSee('Pre-order Bot')
            ->assertDontSee('Awaiting shipping')
            ->assertDontSee('Pre-order · Estimated shipping')
            ->assertDontSee('Backorder dispatch');
    }

    public function test_public_order_page_uses_customer_friendly_remaining_shipping_copy(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'billing_phone' => '0400555666',
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_PARTIALLY_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'shipping_breakdown_data' => [
                'delivery_estimate_label' => '3-7 business days',
            ],
            'paid_at' => now(),
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Microbit',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 5,
            'available_now_quantity' => 3,
            'delayed_quantity' => 2,
            'delayed_fulfilment_type' => 'backorder',
            'delayed_shipping_estimate' => '2026-05-01',
            'inventory_reserved_quantity' => 3,
        ]);

        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $item->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 3,
            'carrier' => 'Australia Post',
            'tracking_number' => 'TRACK-PART-1',
            'tracking_url' => 'https://tracking.example.test/part',
            'dispatched_at' => now(),
        ]);

        $response = $this->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]));

        $response
            ->assertOk()
            ->assertSee('Items in this order')
            ->assertSee('Awaiting shipping')
            ->assertSee('Recorded shipments')
            ->assertSee('Delivery 1')
            ->assertSee('Estimated arrival: 3-7 business days')
            ->assertSee('Items in this delivery')
            ->assertSee('TRACK-PART-1')
            ->assertSee('2 still to be shipped')
            ->assertSee('2 expected shipping May 1st 2026')
            ->assertDontSee('still open')
            ->assertDontSee('Backorder · Expected shipping')
            ->assertDontSee('Backorder');

        $this->assertSame(1, substr_count($response->getContent(), '2 still to be shipped'));
    }

    public function test_account_order_page_numbers_items_shows_skus_and_places_outstanding_in_the_summary(): void
    {
        $user = User::factory()->create([
            'email' => 'robin@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $user->id,
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'billing_phone' => '0400555666',
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $order = StoreOrder::factory()->create([
            'user_id' => $user->id,
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_PROCESSING,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'paid_at' => now(),
        ]);

        $microbit = Product::factory()->create([
            'title' => 'Microbit',
            'sku' => 'MB-001',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);
        $marbles = Product::factory()->create([
            'title' => 'Marbles',
            'sku' => 'MARBLES-002',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $microbit->id,
            'product_title' => 'Microbit',
            'product_sku' => 'MB-001',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 1,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $marbles->id,
            'product_title' => 'Marbles',
            'product_sku' => 'MARBLES-002',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 1,
            'delayed_quantity' => 1,
            'delayed_fulfilment_type' => 'backorder',
            'delayed_shipping_estimate' => '2026-05-01',
            'inventory_reserved_quantity' => 0,
        ]);

        $response = $this->actingAs($user)->get(route('account.order.show', $order));

        $response
            ->assertOk()
            ->assertSee('Order summary')
            ->assertSee('Items in this order')
            ->assertSee('Awaiting shipping')
            ->assertSee('Microbit')
            ->assertSee('SKU MB-001')
            ->assertSee('Marbles')
            ->assertSee('SKU MARBLES-002')
            ->assertSeeInOrder(['Total', 'Outstanding']);

        $this->assertSame(1, substr_count($response->getContent(), 'Outstanding'));
    }

    public function test_public_order_page_orders_deliveries_from_oldest_to_newest(): void
    {
        $invoice = Invoice::factory()->create([
            'billing_name' => 'Robin Customer',
            'billing_email' => 'robin@example.com',
            'billing_phone' => '0400555666',
            'subtotal_amount' => 84.00,
            'gst_amount' => 7.64,
            'total_amount' => 84.00,
        ]);

        $order = StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_PARTIALLY_SHIPPED,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_email' => 'robin@example.com',
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'shipping_breakdown_data' => [
                'delivery_estimate_label' => '3-7 business days',
            ],
            'paid_at' => now(),
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Microbit',
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'quantity' => 2,
            'available_now_quantity' => 2,
            'inventory_reserved_quantity' => 2,
        ]);

        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $item->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'carrier' => 'Australia Post',
            'tracking_number' => 'TRACK-NEWER-1',
            'tracking_url' => 'https://tracking.example.test/newer',
            'dispatched_at' => now()->subDay(),
        ]);

        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $item->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'carrier' => 'Australia Post',
            'tracking_number' => 'TRACK-OLDER-1',
            'tracking_url' => 'https://tracking.example.test/older',
            'dispatched_at' => now()->subDays(3),
        ]);

        $this->get(route('shop.order.tracking', [
            'accessToken' => $order->access_token,
        ]))
            ->assertOk()
            ->assertSeeInOrder([
                'Delivery 1',
                'TRACK-NEWER-1',
                'Delivery 2',
                'TRACK-OLDER-1',
            ]);
    }
}
