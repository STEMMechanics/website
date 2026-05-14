<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\StoreOrderAdminUpdateDigest;
use App\Mail\StoreOrderCustomerUpdateDigest;
use App\Models\Invoice;
use App\Models\Product;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemCollection;
use App\Models\StoreOrderItemTracking;
use App\Models\StoreOrderUpdate;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\StoreOrderUpdateService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class StoreOrderUpdateDigestTest extends TestCase
{
    use RefreshDatabase;

    public function test_restocking_a_backorder_product_allocates_stock_to_the_oldest_paid_order_and_records_an_update(): void
    {
        $admin = $this->makeAdminUser();
        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'allow_backorder' => true,
            'backorder_shipping_estimate' => '2026-04-20',
            'inventory_quantity' => 0,
            'shipping_units' => 1,
            'min_satchel_rank' => 2,
            'weight_grams' => 500,
        ]);

        $olderOrder = $this->makePaidPhysicalOrder([
            'billing_name' => 'Older Customer',
            'billing_email' => 'older@example.com',
            'created_at' => now()->subDays(2),
            'updated_at' => now()->subDays(2),
        ]);
        $newerOrder = $this->makePaidPhysicalOrder([
            'billing_name' => 'Newer Customer',
            'billing_email' => 'newer@example.com',
            'created_at' => now()->subDay(),
            'updated_at' => now()->subDay(),
        ]);

        $olderItem = StoreOrderItem::factory()->create([
            'store_order_id' => $olderOrder->id,
            'product_id' => $product->id,
            'product_title' => 'Backorder Kit',
            'quantity' => 2,
            'available_now_quantity' => 0,
            'delayed_quantity' => 2,
            'delayed_fulfilment_type' => 'backorder',
            'delayed_shipping_estimate' => '2026-04-20',
            'inventory_reserved_quantity' => 0,
        ]);
        $newerItem = StoreOrderItem::factory()->create([
            'store_order_id' => $newerOrder->id,
            'product_id' => $product->id,
            'product_title' => 'Backorder Kit',
            'quantity' => 2,
            'available_now_quantity' => 0,
            'delayed_quantity' => 2,
            'delayed_fulfilment_type' => 'backorder',
            'delayed_shipping_estimate' => '2026-04-20',
            'inventory_reserved_quantity' => 0,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.product.update', $product), [
                'title' => $product->title,
                'slug' => $product->slug,
                'sku' => 'BACKORDER-KIT',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => (string) $product->price,
                'inventory_quantity' => '3',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
                'weight_grams' => '500',
                'allow_backorder' => '1',
                'backorder_shipping_estimate' => '2026-04-20',
            ])
            ->assertRedirect();

        $olderItem->refresh();
        $newerItem->refresh();

        $this->assertSame(2, (int) $olderItem->available_now_quantity);
        $this->assertSame(0, (int) $olderItem->delayed_quantity);
        $this->assertSame(2, (int) $olderItem->inventory_reserved_quantity);
        $this->assertNull($olderItem->delayed_fulfilment_type);

        $this->assertSame(1, (int) $newerItem->available_now_quantity);
        $this->assertSame(1, (int) $newerItem->delayed_quantity);
        $this->assertSame(1, (int) $newerItem->inventory_reserved_quantity);
        $this->assertSame(0, (int) $product->fresh()->inventory_quantity);

        $this->assertDatabaseHas('store_order_updates', [
            'store_order_id' => $olderOrder->id,
            'store_order_item_id' => $olderItem->id,
            'event_type' => StoreOrderUpdate::EVENT_BACKORDER_ALLOCATED,
        ]);
        $this->assertDatabaseHas('store_order_updates', [
            'store_order_id' => $newerOrder->id,
            'store_order_item_id' => $newerItem->id,
            'event_type' => StoreOrderUpdate::EVENT_BACKORDER_ALLOCATED,
        ]);
    }

    public function test_nightly_digest_batches_multiple_order_updates_into_single_customer_and_admin_emails(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $orderOne = $this->makePaidPhysicalOrder([
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'order_number' => 'SO-1001',
        ]);
        $orderTwo = $this->makePaidPhysicalOrder([
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'order_number' => 'SO-1002',
        ]);

        StoreOrderUpdate::query()->create([
            'store_order_id' => $orderOne->id,
            'event_type' => StoreOrderUpdate::EVENT_STATUS_CHANGED,
            'customer_visible' => true,
            'payload' => [
                'from_status' => StoreOrder::STATUS_PROCESSING,
                'from_status_label' => 'Preparing Order',
                'to_status' => StoreOrder::STATUS_READY_FOR_PICKUP,
                'to_status_label' => 'Ready for Pickup',
            ],
            'occurred_at' => now()->subHours(4),
        ]);
        StoreOrderUpdate::query()->create([
            'store_order_id' => $orderOne->id,
            'event_type' => StoreOrderUpdate::EVENT_PUBLIC_NOTE_UPDATED,
            'customer_visible' => true,
            'payload' => [
                'public_note' => 'Packed and waiting at the studio.',
            ],
            'occurred_at' => now()->subHours(3),
        ]);
        StoreOrderUpdate::query()->create([
            'store_order_id' => $orderTwo->id,
            'event_type' => StoreOrderUpdate::EVENT_BACKORDER_ALLOCATED,
            'customer_visible' => true,
            'payload' => [
                'item_title' => 'Delayed Kit',
                'allocated_quantity' => 2,
                'remaining_delayed_quantity' => 0,
            ],
            'occurred_at' => now()->subHours(2),
        ]);

        Artisan::call('store:orders:send-update-digests');

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'jamie@example.com'
                && $job->mailable instanceof StoreOrderCustomerUpdateDigest
                && count($job->mailable->orders) === 2;
        });

        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'ops@example.com'
                && $job->mailable instanceof StoreOrderAdminUpdateDigest
                && count($job->mailable->orders) === 2;
        });

        $this->assertSame(3, StoreOrderUpdate::query()->whereNotNull('customer_digest_queued_at')->count());
        $this->assertSame(3, StoreOrderUpdate::query()->whereNotNull('admin_digest_queued_at')->count());

        Queue::fake();
        Artisan::call('store:orders:send-update-digests');
        Queue::assertNothingPushed();
    }

    public function test_status_change_updates_use_clear_transition_wording(): void
    {
        $order = $this->makePaidPhysicalOrder([
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'order_number' => 'SO-2001',
            'status' => StoreOrder::STATUS_PARTIALLY_SHIPPED,
        ]);

        $update = StoreOrderUpdate::query()->create([
            'store_order_id' => $order->id,
            'event_type' => StoreOrderUpdate::EVENT_STATUS_CHANGED,
            'customer_visible' => true,
            'payload' => [
                'from_status' => StoreOrder::STATUS_PROCESSING,
                'from_status_label' => 'Preparing Order',
                'to_status' => StoreOrder::STATUS_PARTIALLY_SHIPPED,
                'to_status_label' => 'Partially Shipped',
            ],
            'occurred_at' => now()->subMinutes(5),
        ]);

        $payload = app(StoreOrderUpdateService::class)->payloadForEvents([$update->id], false);

        $this->assertNotNull($payload);
        $this->assertSame('partially_shipped', $payload['orders'][0]['notification_type']);
        $this->assertSame('Part of the order has now shipped.', $payload['orders'][0]['updates'][0]['summary']);
        $this->assertSame('Previously: Preparing Order.', $payload['orders'][0]['updates'][0]['detail']);
    }

    public function test_ready_for_pickup_payload_groups_ready_and_expected_items(): void
    {
        $order = $this->makePaidPhysicalOrder([
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'order_number' => 'SO-2101',
            'status' => StoreOrder::STATUS_READY_FOR_PICKUP,
            'shipping_method_code' => 'pickup',
            'shipping_method' => 'Free pickup',
        ]);

        $readyItem = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Microbit Base',
            'variant_name' => '',
            'quantity' => 5,
            'available_now_quantity' => 2,
            'delayed_quantity' => 3,
            'delayed_fulfilment_type' => 'backorder',
            'delayed_shipping_estimate' => '2026-04-20',
            'inventory_reserved_quantity' => 2,
        ]);
        StoreOrderItemCollection::query()->create([
            'store_order_item_id' => $readyItem->id,
            'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
            'quantity' => 2,
            'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
            'collected_at' => now()->subMinutes(5),
        ]);

        $update = StoreOrderUpdate::query()->create([
            'store_order_id' => $order->id,
            'event_type' => StoreOrderUpdate::EVENT_STATUS_CHANGED,
            'customer_visible' => true,
            'payload' => [
                'from_status' => StoreOrder::STATUS_PROCESSING,
                'from_status_label' => 'Preparing Order',
                'to_status' => StoreOrder::STATUS_READY_FOR_PICKUP,
                'to_status_label' => 'Ready for Pickup',
            ],
            'occurred_at' => now()->subMinutes(5),
        ]);

        $payload = app(StoreOrderUpdateService::class)->payloadForEvents([$update->id], false);

        $this->assertNotNull($payload);
        $this->assertSame('ready_for_pickup', $payload['orders'][0]['notification_type']);
        $this->assertSame('Ready for collection now', $payload['orders'][0]['item_sections'][0]['heading']);
        $this->assertSame('Microbit Base', $payload['orders'][0]['item_sections'][0]['items'][0]['title']);
        $this->assertSame(2, $payload['orders'][0]['item_sections'][0]['items'][0]['quantity']);
        $this->assertSame('Still expected later', $payload['orders'][0]['item_sections'][1]['heading']);
        $this->assertSame(3, $payload['orders'][0]['item_sections'][1]['items'][0]['quantity']);
        $this->assertStringContainsString('Expected availability April 20th 2026', (string) $payload['orders'][0]['item_sections'][1]['items'][0]['detail']);
    }

    public function test_ready_for_partial_collection_payload_groups_ready_and_remaining_items(): void
    {
        $order = $this->makePaidPhysicalOrder([
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'order_number' => 'SO-2101B',
            'status' => StoreOrder::STATUS_READY_FOR_PARTIAL_COLLECTION,
            'shipping_method_code' => 'pickup',
            'shipping_method' => 'Free pickup',
        ]);

        $readyItem = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Microbit Base',
            'variant_name' => '',
            'quantity' => 2,
            'available_now_quantity' => 1,
            'delayed_quantity' => 1,
            'delayed_fulfilment_type' => 'backorder',
            'delayed_shipping_estimate' => '2026-04-20',
            'inventory_reserved_quantity' => 1,
        ]);
        StoreOrderItemCollection::query()->create([
            'store_order_item_id' => $readyItem->id,
            'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
            'quantity' => 1,
            'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
            'collected_at' => now()->subMinutes(5),
        ]);

        $update = StoreOrderUpdate::query()->create([
            'store_order_id' => $order->id,
            'event_type' => StoreOrderUpdate::EVENT_STATUS_CHANGED,
            'customer_visible' => true,
            'payload' => [
                'from_status' => StoreOrder::STATUS_PROCESSING,
                'from_status_label' => 'Preparing Order',
                'to_status' => StoreOrder::STATUS_READY_FOR_PARTIAL_COLLECTION,
                'to_status_label' => 'Ready for Partial Collection',
            ],
            'occurred_at' => now()->subMinutes(5),
        ]);

        $payload = app(StoreOrderUpdateService::class)->payloadForEvents([$update->id], false);

        $this->assertNotNull($payload);
        $this->assertSame('ready_for_partial_collection', $payload['orders'][0]['notification_type']);
        $this->assertSame('Ready for partial collection', $payload['orders'][0]['item_sections'][0]['heading']);
        $this->assertSame('Microbit Base', $payload['orders'][0]['item_sections'][0]['items'][0]['title']);
        $this->assertSame(1, $payload['orders'][0]['item_sections'][0]['items'][0]['quantity']);
        $this->assertSame('Still to be prepared', $payload['orders'][0]['item_sections'][1]['heading']);
        $this->assertSame(1, $payload['orders'][0]['item_sections'][1]['items'][0]['quantity']);
    }

    public function test_tracking_payload_groups_shipped_and_remaining_items(): void
    {
        $order = $this->makePaidPhysicalOrder([
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'order_number' => 'SO-2102',
            'status' => StoreOrder::STATUS_PARTIALLY_SHIPPED,
            'shipping_breakdown_data' => [
                'shipments' => [
                    [
                        'delivery_estimate_label' => '3-7 business days',
                    ],
                ],
            ],
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Robot Kit',
            'variant_name' => '',
            'quantity' => 3,
            'available_now_quantity' => 2,
            'delayed_quantity' => 1,
            'delayed_fulfilment_type' => 'backorder',
            'delayed_shipping_estimate' => '2026-04-20',
            'inventory_reserved_quantity' => 1,
        ]);

        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $item->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'carrier' => 'Australia Post',
            'tracking_number' => 'TRACK-123',
            'tracking_url' => 'https://tracking.example.test/TRACK-123',
            'notes' => 'Left the workshop today.',
            'dispatched_at' => now()->subMinutes(10),
        ]);

        $update = StoreOrderUpdate::query()->create([
            'store_order_id' => $order->id,
            'store_order_item_id' => $item->id,
            'event_type' => StoreOrderUpdate::EVENT_TRACKING_ADDED,
            'customer_visible' => true,
            'payload' => [
                'item_title' => $item->displayTitle(),
                'quantity' => 1,
                'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
                'carrier' => 'Australia Post',
                'tracking_number' => 'TRACK-123',
                'tracking_url' => 'https://tracking.example.test/TRACK-123',
                'notes' => 'Left the workshop today.',
            ],
            'occurred_at' => now()->subMinutes(5),
        ]);

        $payload = app(StoreOrderUpdateService::class)->payloadForEvents([$update->id], false);

        $this->assertNotNull($payload);
        $this->assertSame('partially_shipped', $payload['orders'][0]['notification_type']);
        $this->assertSame('Shipped now', $payload['orders'][0]['item_sections'][0]['heading']);
        $this->assertStringContainsString('Estimated arrival: 3-7 business days', (string) $payload['orders'][0]['item_sections'][0]['detail']);
        $this->assertStringContainsString('Australia Post', (string) $payload['orders'][0]['item_sections'][0]['detail']);
        $this->assertStringContainsString('Tracking TRACK-123', (string) $payload['orders'][0]['item_sections'][0]['detail']);
        $this->assertSame('Robot Kit', $payload['orders'][0]['item_sections'][0]['items'][0]['title']);
        $this->assertSame(1, $payload['orders'][0]['item_sections'][0]['items'][0]['quantity']);
        $this->assertNull($payload['orders'][0]['item_sections'][0]['items'][0]['detail']);
        $this->assertSame('To be shipped', $payload['orders'][0]['item_sections'][1]['heading']);
        $this->assertSame(2, $payload['orders'][0]['item_sections'][1]['items'][0]['quantity']);
        $this->assertStringContainsString('shipping estimated april 20th 2026', strtolower((string) $payload['orders'][0]['item_sections'][1]['items'][0]['detail']));
    }

    public function test_tracking_payload_groups_same_day_manual_shipments_into_separate_delivery_sections(): void
    {
        $order = $this->makePaidPhysicalOrder([
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'order_number' => 'SO-2102B',
            'status' => StoreOrder::STATUS_SHIPPED,
            'shipping_breakdown_data' => [
                'shipments' => [
                    [
                        'delivery_estimate_label' => '3-7 business days',
                    ],
                ],
            ],
        ]);

        $itemOne = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Microbit',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 0,
        ]);
        $itemTwo = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Pinball Template',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 0,
        ]);

        $dispatchedAt = now()->subDay()->startOfDay()->addHours(10);

        $trackingOne = StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $itemOne->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'carrier' => 'Australia Post',
            'tracking_number' => null,
            'tracking_url' => null,
            'notes' => 'Packed with the rest of the order.',
            'dispatched_at' => $dispatchedAt,
        ]);
        $trackingTwo = StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $itemTwo->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'carrier' => 'Australia Post',
            'tracking_number' => null,
            'tracking_url' => null,
            'notes' => 'Second item in the same parcel.',
            'dispatched_at' => $dispatchedAt->copy()->addMinutes(15),
        ]);

        $service = app(StoreOrderUpdateService::class);
        $updateOne = $service->recordTrackingAdded($order, $itemOne, $trackingOne);
        $updateTwo = $service->recordTrackingAdded($order, $itemTwo, $trackingTwo);

        $payload = $service->payloadForEvents([$updateOne?->id, $updateTwo?->id], false);
        $expectedDetailOne = sprintf(
            'Shipped %s | Estimated arrival: 3-7 business days | Australia Post',
            $dispatchedAt->format('F jS Y'),
        );
        $expectedDetailTwo = sprintf(
            'Shipped %s | Estimated arrival: 3-7 business days | Australia Post',
            $dispatchedAt->copy()->addMinutes(15)->format('F jS Y'),
        );

        $this->assertNotNull($payload);
        $this->assertSame('shipped', $payload['orders'][0]['notification_type']);
        $this->assertCount(2, $payload['orders'][0]['item_sections']);
        $this->assertSame('Delivery 1', $payload['orders'][0]['item_sections'][0]['heading']);
        $this->assertSame('Delivery 2', $payload['orders'][0]['item_sections'][1]['heading']);
        $this->assertSame($expectedDetailOne, $payload['orders'][0]['item_sections'][0]['detail']);
        $this->assertSame($expectedDetailTwo, $payload['orders'][0]['item_sections'][1]['detail']);
        $this->assertCount(1, $payload['orders'][0]['item_sections'][0]['items']);
        $this->assertCount(1, $payload['orders'][0]['item_sections'][1]['items']);
        $this->assertSame('Microbit', $payload['orders'][0]['item_sections'][0]['items'][0]['title']);
        $this->assertSame('Pinball Template', $payload['orders'][0]['item_sections'][1]['items'][0]['title']);
        $this->assertNull($payload['orders'][0]['item_sections'][0]['items'][0]['detail']);
        $this->assertNull($payload['orders'][0]['item_sections'][1]['items'][0]['detail']);
    }

    public function test_tracking_payload_groups_matching_parcel_numbers_into_one_parcel_section(): void
    {
        $order = $this->makePaidPhysicalOrder([
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'order_number' => 'SO-2102C',
            'status' => StoreOrder::STATUS_SHIPPED,
        ]);

        $itemOne = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Microbit',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 0,
        ]);
        $itemTwo = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Pinball Template',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 0,
        ]);

        $dispatchedAt = now()->subDay()->startOfDay()->addHours(10);

        $trackingOne = StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $itemOne->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'parcel_number' => 2,
            'carrier' => 'Australia Post',
            'tracking_number' => null,
            'tracking_url' => null,
            'notes' => 'Packed together in parcel two.',
            'dispatched_at' => $dispatchedAt,
        ]);
        $trackingTwo = StoreOrderItemTracking::query()->create([
            'store_order_item_id' => $itemTwo->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'parcel_number' => 2,
            'carrier' => 'Australia Post',
            'tracking_number' => null,
            'tracking_url' => null,
            'notes' => 'Second line in the same parcel.',
            'dispatched_at' => $dispatchedAt->copy()->addMinutes(15),
        ]);

        $service = app(StoreOrderUpdateService::class);
        $updateOne = $service->recordTrackingAdded($order, $itemOne, $trackingOne);
        $updateTwo = $service->recordTrackingAdded($order, $itemTwo, $trackingTwo);

        $payload = $service->payloadForEvents([$updateOne?->id, $updateTwo?->id], false);

        $this->assertNotNull($payload);
        $this->assertSame('shipped', $payload['orders'][0]['notification_type']);
        $this->assertCount(1, $payload['orders'][0]['item_sections']);
        $this->assertSame('Parcel #2', $payload['orders'][0]['item_sections'][0]['heading']);
        $this->assertSame('Microbit', $payload['orders'][0]['item_sections'][0]['items'][0]['title']);
        $this->assertSame('Pinball Template', $payload['orders'][0]['item_sections'][0]['items'][1]['title']);
    }

    public function test_partial_item_cancellation_uses_specific_notification_type_and_sections(): void
    {
        $order = $this->makePaidPhysicalOrder([
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'order_number' => 'SO-2103',
            'status' => StoreOrder::STATUS_PROCESSING,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_title' => 'Circuit Pack',
            'quantity' => 4,
            'available_now_quantity' => 2,
            'delayed_quantity' => 2,
            'cancelled_available_quantity' => 1,
            'cancelled_delayed_quantity' => 0,
        ]);

        $update = StoreOrderUpdate::query()->create([
            'store_order_id' => $order->id,
            'store_order_item_id' => $item->id,
            'event_type' => StoreOrderUpdate::EVENT_ITEM_CANCELLED,
            'customer_visible' => true,
            'payload' => [
                'item_title' => $item->displayTitle(),
                'available_quantity' => 1,
                'delayed_quantity' => 0,
                'reason' => 'Requested by customer',
            ],
            'occurred_at' => now()->subMinutes(5),
        ]);

        $payload = app(StoreOrderUpdateService::class)->payloadForEvents([$update->id], false);

        $this->assertNotNull($payload);
        $this->assertSame('items_cancelled', $payload['orders'][0]['notification_type']);
        $this->assertSame('Cancelled from this order', $payload['orders'][0]['item_sections'][0]['heading']);
        $this->assertSame(1, $payload['orders'][0]['item_sections'][0]['items'][0]['quantity']);
        $this->assertSame('Requested by customer', $payload['orders'][0]['item_sections'][0]['items'][0]['detail']);
        $this->assertSame('Still active on this order', $payload['orders'][0]['item_sections'][1]['heading']);
        $this->assertSame(3, $payload['orders'][0]['item_sections'][1]['items'][0]['quantity']);
    }

    public function test_digest_mailables_render_clickable_order_buttons(): void
    {
        $customerMail = new StoreOrderCustomerUpdateDigest('Jamie Example', '15 March 2026', [[
            'order_number' => 'SO-3001',
            'status_label' => 'Preparing Order',
            'order_url' => 'https://test.stemmechanics.com.au/tracking/example-token',
            'updates' => [],
        ]]);
        $adminMail = new StoreOrderAdminUpdateDigest('15 March 2026', [[
            'order_number' => 'SO-3001',
            'status_label' => 'Preparing Order',
            'admin_url' => 'https://test.stemmechanics.com.au/admin/store/orders/SO-3001',
            'customer_name' => 'Jamie Example',
            'customer_email' => 'jamie@example.com',
            'updates' => [],
        ]]);

        $customerRendered = $customerMail->render();
        $adminRendered = $adminMail->render();

        $this->assertStringContainsString('View Order', $customerRendered);
        $this->assertStringContainsString('https://test.stemmechanics.com.au/tracking/example-token', $customerRendered);
        $this->assertStringContainsString('Open in Admin', $adminRendered);
        $this->assertStringContainsString('https://test.stemmechanics.com.au/admin/store/orders/SO-3001', $adminRendered);
    }

    private function makeAdminUser(): User
    {
        $admin = User::factory()->create();

        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        return $admin;
    }

    private function makePaidPhysicalOrder(array $attributes = []): StoreOrder
    {
        $defaults = [
            'invoice_id' => Invoice::factory()->create()->id,
            'status' => StoreOrder::STATUS_PROCESSING,
            'contains_physical' => true,
            'contains_digital' => false,
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'paid_at' => now()->subHour(),
        ];

        return StoreOrder::factory()->create(array_merge($defaults, $attributes));
    }
}
