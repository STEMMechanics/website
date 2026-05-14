<?php

namespace Tests\Feature;

use App\Jobs\SendEmail;
use App\Mail\PaymentReceiptPdf;
use App\Mail\StoreOrderAdminUpdateNotice;
use App\Mail\StoreOrderCustomerUpdateNotice;
use App\Models\Coupon;
use App\Models\Invoice;
use App\Models\InvoiceLine;
use App\Models\InvoicePaymentAllocation;
use App\Models\Payment;
use App\Models\Product;
use App\Models\SiteOption;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\StoreOrderItemCollection;
use App\Models\StoreOrderItemTracking;
use App\Models\StoreOrderUpdate;
use App\Models\TaxAdjustment;
use App\Models\User;
use App\Models\UserGroup;
use App\Support\ShopShippingSettings;
use App\Services\SquareApiService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\TestCase;

class ShopAdminOrderItemsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_order_page_exposes_item_cancellation_and_tracking_actions(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder()->forceFill([
            'billing_phone' => '0400123456',
        ]);
        $order->save();
        StoreOrderItemTracking::query()->create([
            'store_order_item_id' => StoreOrderItem::factory()->create([
                'store_order_id' => $order->id,
                'product_id' => Product::factory()->create([
                    'inventory_quantity' => 2,
                ])->id,
            ])->id,
            'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
            'quantity' => 1,
            'carrier' => 'Australia Post',
            'tracking_number' => null,
            'tracking_url' => null,
            'notes' => null,
            'dispatched_at' => now(),
        ]);
        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => Product::factory()->create([
                'inventory_quantity' => 3,
            ])->id,
            'quantity' => 3,
            'available_now_quantity' => 2,
            'delayed_quantity' => 1,
            'inventory_reserved_quantity' => 2,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.order.edit', $order))
            ->assertOk()
            ->assertSee('Order Status')
            ->assertSee('Placed')
            ->assertSee('Invoice')
            ->assertSee('Customer')
            ->assertSee('0400 123 456')
            ->assertSee('Delivery Details')
            ->assertSee('Order Summary')
            ->assertSee('Outstanding')
            ->assertSee('Order Changes')
            ->assertSee('Queued Order Changes')
            ->assertSee('Preparing Order')
            ->assertSee('Cancel Items')
            ->assertSee('Add Shipment')
            ->assertSee('Parcel number')
            ->assertSee('Tracking mode')
            ->assertSee('No Tracking Number')
            ->assertSee('Tracking Number')
            ->assertSee('Private Notes')
            ->assertSee('Public Notes')
            ->assertSee('Partially Shipped')
            ->assertSee('Save All Changes')
            ->assertSee('Clear Staged Changes')
            ->assertDontSee('Finish Order Edits')
            ->assertSee($item->displayTitle());
    }

    public function test_admin_pickup_orders_show_pickup_copy_and_pick_list_pdf_link(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_PROCESSING,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
            'shipping_breakdown_data' => [
                'delivery_estimate_label' => 'Timing to be confirmed',
                'shipments' => [
                    [
                        'delivery_estimate_label' => 'Available now',
                        'title_meta' => 'Collection',
                    ],
                ],
            ],
        ]);

        $product = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Pickup Kit',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.order.edit', $order))
            ->assertOk()
            ->assertSee('Collection Details')
            ->assertSee('Pick up / Collection')
            ->assertSee('Customer will be contacted for collection.')
            ->assertSee('Ready for Pickup')
            ->assertSee('Pick List PDF')
            ->assertDontSee('Timing to be confirmed')
            ->assertDontSee('Collection: Available now');

        $this->actingAs($admin)
            ->get(route('admin.shop.order.pick-list.pdf', $order))
            ->assertOk()
            ->assertHeader('content-type', 'application/pdf');
    }

    public function test_admin_pickup_orders_expose_item_collection_actions_without_order_level_ready_button(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_PROCESSING,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
        ]);

        $productA = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);
        $productB = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productA->id,
            'product_title' => 'Pickup Item A',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productB->id,
            'product_title' => 'Pickup Item B',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        $response = $this->actingAs($admin)
            ->get(route('admin.shop.order.edit', $order))
            ->assertOk();

        $response
            ->assertSeeHtml('x-on:click="openCollection(')
            ->assertDontSee('x-on:click="markOrderReadyForPickup()"');
    }

    public function test_admin_ready_for_pickup_orders_lock_item_actions_after_save(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_PROCESSING,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
            'fulfilled_at' => null,
        ]);

        $product = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Pickup Kit',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.collection.store', ['storeOrder' => $order, 'storeOrderItem' => $item]), [
                'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
                'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
                'quantity' => 1,
                'collected_at' => now()->toDateString(),
                'notes' => 'Ready for pickup.',
            ])
            ->assertRedirect();

        $order = $order->fresh();
        $item = $item->fresh(['collectionEntries', 'trackingEntries']);

        $this->assertSame(StoreOrder::STATUS_READY_FOR_PICKUP, $order->status);
        $this->assertSame(1, (int) $item->readyPickupQuantity());

        $this->actingAs($admin)
            ->get(route('admin.shop.order.edit', $order))
            ->assertOk()
            ->assertDontSee('x-on:click="openCancel(')
            ->assertDontSee('x-on:click="openTracking(')
            ->assertDontSee('x-on:click="openBulkCancel()"')
            ->assertDontSee('x-on:click="openBulkTracking()"')
            ->assertDontSeeHtml('x-model="statusValue"')
            ->assertSee('Pickup readiness is managed from the item actions.');

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.cancel', ['storeOrder' => $order, 'storeOrderItem' => $item]), [
                'quantity' => 1,
                'reason' => 'No longer needed',
            ])
            ->assertRedirect()
            ->assertSessionHasErrorsIn('cancelItem_'.$item->id, ['available_quantity']);

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.tracking.store', ['storeOrder' => $order, 'storeOrderItem' => $item]), [
                'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
                'tracking_mode' => 'none',
                'quantity' => 1,
                'parcel_number' => 1,
                'carrier' => '',
                'tracking_number' => '',
                'tracking_url' => '',
                'notes' => '',
                'dispatched_at' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrorsIn('trackingItem_'.$item->id, ['quantity']);
    }

    public function test_admin_pickup_orders_can_record_partial_collections_and_transition_through_partial_collection_state(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_PROCESSING,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
            'fulfilled_at' => null,
        ]);

        $product = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Pickup Kit',
            'quantity' => 2,
            'available_now_quantity' => 1,
            'delayed_quantity' => 1,
            'inventory_reserved_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.order.edit', $order))
            ->assertOk()
            ->assertSeeHtml('x-on:click="openCollection(')
            ->assertDontSee('x-on:click="openTracking(');

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.collection.store', ['storeOrder' => $order, 'storeOrderItem' => $item]), [
                'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
                'quantity' => 1,
                'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
                'collected_at' => now()->toDateString(),
                'notes' => 'Set aside for collection.',
            ])
            ->assertRedirect();

        $order = $order->fresh();
        $item = $item->fresh(['collectionEntries', 'trackingEntries']);

        $this->assertSame(StoreOrder::STATUS_READY_FOR_PARTIAL_COLLECTION, $order->status);
        $this->assertSame(0, (int) $item->collectedAvailableQuantity());
        $this->assertSame(0, (int) $item->collectedDelayedQuantity());
        $this->assertSame(1, (int) $item->readyPickupAvailableQuantity());
        $this->assertSame(0, (int) $item->readyPickupDelayedQuantity());
        $this->assertSame(1, (int) $item->readyPickupQuantity());
        $this->assertSame(2, (int) $item->remainingPickupQuantity());
        $this->assertDatabaseHas('store_order_item_collections', [
            'store_order_item_id' => $item->id,
            'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
            'quantity' => 1,
            'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
            'notes' => 'Set aside for collection.',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.order.edit', $order))
            ->assertOk()
            ->assertSee('Ready for Partial Collection')
            ->assertSeeHtml('x-on:click="openCollection(')
            ->assertSeeHtml('Collected <span')
            ->assertSeeHtml('To prepare <span');

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.collection.store', ['storeOrder' => $order, 'storeOrderItem' => $item]), [
                'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
                'quantity' => 1,
                'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_COLLECTED,
                'collected_at' => now()->toDateString(),
                'notes' => 'Collected from the counter.',
            ])
            ->assertRedirect();

        $order = $order->fresh();
        $item = $item->fresh(['collectionEntries', 'trackingEntries']);

        $this->assertSame(StoreOrder::STATUS_PARTIALLY_COLLECTED, $order->status);
        $this->assertNotNull($order->fulfilled_at);
        $this->assertSame(1, (int) $item->collectedAvailableQuantity());
        $this->assertSame(0, (int) $item->collectedDelayedQuantity());
        $this->assertSame(0, (int) $item->readyPickupQuantity());
        $this->assertSame(1, (int) $item->remainingPickupQuantity());
        $this->assertSame(0, (int) $item->inventory_reserved_quantity);

        $this->actingAs($admin)
            ->get(route('admin.shop.order.edit', $order))
            ->assertOk()
            ->assertSee('Partially Collected')
            ->assertDontSee('x-on:click="openCollection(')
            ->assertDontSeeHtml('x-model="statusValue"');
    }

    public function test_admin_pickup_orders_can_skip_the_customer_email_when_collecting_items(): void
    {
        Queue::fake();

        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_PROCESSING,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
            'fulfilled_at' => null,
        ]);

        $product = Product::factory()->create([
            'inventory_quantity' => 1,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Pickup Kit',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        StoreOrderItemCollection::create([
            'store_order_item_id' => $item->id,
            'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
            'quantity' => 1,
            'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
            'collected_by_user_id' => null,
            'notes' => 'Ready for collection.',
            'collected_at' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.order.update', $order), [
                'status' => StoreOrder::STATUS_COLLECTED,
                'notes' => null,
                'public_notes' => null,
                'send_update_email' => 0,
                'item_actions_json' => json_encode([
                    [
                        'type' => 'collection',
                        'item_id' => $item->id,
                        'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
                        'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_COLLECTED,
                        'quantity' => 1,
                        'collected_at' => now()->toDateString(),
                        'notes' => 'Collected in store.',
                    ],
                ]),
            ])
            ->assertRedirect();

        Queue::assertNotPushed(SendEmail::class);
        $this->assertSame(StoreOrder::STATUS_COLLECTED, (string) $order->fresh()->status);
        $this->assertSame(1, (int) $item->fresh()->collectedQuantity());
    }

    public function test_admin_pickup_orders_display_the_partial_collection_status_label(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_READY_FOR_PARTIAL_COLLECTION,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
        ]);

        $product = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Pickup Kit',
            'quantity' => 2,
            'available_now_quantity' => 1,
            'delayed_quantity' => 1,
            'inventory_reserved_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.order.edit', $order))
            ->assertOk()
            ->assertSee('Ready for Partial Collection')
            ->assertSeeHtml('x-on:submit.prevent="stagePickupAction(')
            ->assertSee('Stage Pickup Action')
            ->assertSee('Pickup readiness is managed from the item actions.');
    }

    public function test_admin_pickup_orders_can_stage_multiple_ready_actions_before_saving_order_changes(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_PROCESSING,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
        ]);

        $productA = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);
        $productB = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);

        $itemA = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productA->id,
            'product_title' => 'Pickup Item A',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);
        $itemB = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productB->id,
            'product_title' => 'Pickup Item B',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.order.update', $order), [
                'status' => StoreOrder::STATUS_READY_FOR_PARTIAL_COLLECTION,
                'notes' => '',
                'public_notes' => '',
                'item_actions_json' => json_encode([
                    [
                        'type' => 'collection',
                        'item_id' => $itemA->id,
                        'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
                        'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
                        'quantity' => 1,
                        'collected_at' => '',
                        'notes' => 'Item A ready.',
                    ],
                    [
                        'type' => 'collection',
                        'item_id' => $itemB->id,
                        'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
                        'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
                        'quantity' => 1,
                        'collected_at' => '',
                        'notes' => 'Item B ready.',
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $order = $order->fresh();
        $itemA = $itemA->fresh(['collectionEntries']);
        $itemB = $itemB->fresh(['collectionEntries']);

        $this->assertSame(StoreOrder::STATUS_READY_FOR_PICKUP, (string) $order->status);
        $this->assertSame(1, (int) $itemA->readyPickupQuantity());
        $this->assertSame(1, (int) $itemB->readyPickupQuantity());
        $this->assertSame(1, (int) $itemA->remainingPickupQuantity());
        $this->assertSame(1, (int) $itemB->remainingPickupQuantity());
        $this->assertDatabaseHas('store_order_item_collections', [
            'store_order_item_id' => $itemA->id,
            'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
            'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
            'quantity' => 1,
            'notes' => 'Item A ready.',
        ]);
        $this->assertDatabaseHas('store_order_item_collections', [
            'store_order_item_id' => $itemB->id,
            'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
            'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
            'quantity' => 1,
            'notes' => 'Item B ready.',
        ]);
    }

    public function test_admin_pickup_orders_can_stage_collections_after_items_have_been_marked_ready(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_READY_FOR_PICKUP,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
        ]);

        $product = Product::factory()->create([
            'inventory_quantity' => 1,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Pickup Item',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        StoreOrderItemCollection::query()->create([
            'store_order_item_id' => $item->id,
            'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
            'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
            'quantity' => 1,
            'collected_by_user_id' => null,
            'notes' => 'Ready to collect.',
            'collected_at' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.order.update', $order), [
                'status' => StoreOrder::STATUS_READY_FOR_PICKUP,
                'notes' => '',
                'public_notes' => '',
                'item_actions_json' => json_encode([
                    [
                        'type' => 'collection',
                        'item_id' => $item->id,
                        'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_COLLECTED,
                        'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
                        'quantity' => 1,
                        'collected_at' => now()->toDateString(),
                        'notes' => 'Collected at counter.',
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $order = $order->fresh();
        $item = $item->fresh(['collectionEntries']);

        $this->assertSame(StoreOrder::STATUS_COLLECTED, (string) $order->status);
        $this->assertSame(0, (int) $item->readyPickupQuantity());
        $this->assertSame(1, (int) $item->collectedQuantity());
        $this->assertDatabaseHas('store_order_item_collections', [
            'store_order_item_id' => $item->id,
            'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
            'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_COLLECTED,
            'quantity' => 1,
            'notes' => 'Collected at counter.',
        ]);
    }

    public function test_admin_pickup_orders_become_collected_when_the_full_item_quantity_has_been_collected(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_READY_FOR_PICKUP,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
        ]);

        $product = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Pickup Kit',
            'quantity' => 2,
            'available_now_quantity' => 2,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 2,
        ]);

        StoreOrderItemCollection::query()->create([
            'store_order_item_id' => $item->id,
            'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
            'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
            'quantity' => 2,
            'collected_by_user_id' => null,
            'notes' => 'Ready to collect.',
            'collected_at' => null,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.order.update', $order), [
                'status' => StoreOrder::STATUS_READY_FOR_PICKUP,
                'notes' => '',
                'public_notes' => '',
                'item_actions_json' => json_encode([
                    [
                        'type' => 'collection',
                        'item_id' => $item->id,
                        'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_COLLECTED,
                        'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
                        'quantity' => 2,
                        'collected_at' => now()->toDateString(),
                        'notes' => 'Collected in full.',
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $order = $order->fresh();
        $item = $item->fresh(['collectionEntries']);

        $this->assertSame(StoreOrder::STATUS_COLLECTED, (string) $order->status);
        $this->assertSame(0, (int) $item->readyPickupQuantity());
        $this->assertSame(2, (int) $item->collectedQuantity());
        $this->assertSame(0, (int) $item->remainingPickupQuantity());
    }

    public function test_admin_pickup_orders_keep_other_items_unready_when_one_item_is_marked_ready(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_PROCESSING,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
        ]);

        $productA = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);
        $productB = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);

        $itemA = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productA->id,
            'product_title' => 'Pickup Item A',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);
        $itemB = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productB->id,
            'product_title' => 'Pickup Item B',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.collection.store', ['storeOrder' => $order, 'storeOrderItem' => $itemA]), [
                'collection_type' => StoreOrderItemCollection::COLLECTION_TYPE_AVAILABLE,
                'quantity' => 1,
                'pickup_state' => StoreOrderItemCollection::PICKUP_STATE_READY,
                'collected_at' => now()->toDateString(),
                'notes' => 'First item set aside.',
            ])
            ->assertRedirect();

        $order = $order->fresh();
        $itemA = $itemA->fresh(['collectionEntries']);
        $itemB = $itemB->fresh(['collectionEntries']);

        $this->assertSame(StoreOrder::STATUS_READY_FOR_PARTIAL_COLLECTION, $order->status);
        $this->assertSame(1, (int) $itemA->readyPickupQuantity());
        $this->assertSame(0, (int) $itemB->readyPickupQuantity());
        $this->assertSame(1, (int) $itemA->remainingPickupQuantity());
        $this->assertSame(1, (int) $itemB->remainingPickupQuantity());

        $this->actingAs($admin)
            ->get(route('admin.shop.order.edit', $order))
            ->assertOk()
            ->assertSee('Ready for Partial Collection')
            ->assertSee('Pickup Item A')
            ->assertSee('Pickup Item B')
            ->assertSeeHtml('x-text="readyPickup('.$itemA->id.')"')
            ->assertSeeHtml('x-text="readyPickup('.$itemB->id.')"');
    }

    public function test_admin_pickup_orders_reject_manual_ready_for_pickup_status_changes(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_PROCESSING,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.order.update', $order), [
                'status' => StoreOrder::STATUS_READY_FOR_PICKUP,
                'notes' => null,
                'public_notes' => null,
                'item_actions_json' => null,
            ])
            ->assertRedirect()
            ->assertSessionHasNoErrors();

        $this->assertSame(StoreOrder::STATUS_PROCESSING, $order->fresh()->status);
    }

    public function test_admin_collected_pickup_orders_hide_item_actions_and_reject_new_item_changes(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $order->update([
            'status' => StoreOrder::STATUS_COLLECTED,
            'shipping_method' => 'Pick up / Collection',
            'shipping_method_code' => 'pickup',
            'fulfilled_at' => now(),
        ]);

        $product = Product::factory()->create([
            'inventory_quantity' => 2,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Pickup Kit',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.order.edit', $order))
            ->assertOk()
            ->assertSee('Collected')
            ->assertDontSee('Cancel Items')
            ->assertDontSee('Stage Cancellation')
            ->assertDontSee('Add Shipment Entry');

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.cancel', ['storeOrder' => $order, 'storeOrderItem' => $item]), [
                'quantity' => 1,
                'reason' => 'No longer needed',
            ])
            ->assertRedirect()
            ->assertSessionHasErrorsIn('cancelItem_'.$item->id, ['available_quantity']);

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.tracking.store', ['storeOrder' => $order, 'storeOrderItem' => $item]), [
                'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
                'tracking_mode' => 'none',
                'quantity' => 1,
                'parcel_number' => 1,
                'carrier' => '',
                'tracking_number' => '',
                'tracking_url' => '',
                'notes' => '',
                'dispatched_at' => now()->toDateString(),
            ])
            ->assertRedirect()
            ->assertSessionHasErrorsIn('trackingItem_'.$item->id, ['quantity']);
    }

    public function test_admin_invoice_page_lists_linked_refund_records_with_associated_payments(): void
    {
        $admin = $this->makeAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Jamie',
            'surname' => 'Example',
            'email' => 'jamie@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-SHOP-REFUND-1',
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'status' => Invoice::STATUS_PAID,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 20.00,
            'reference' => 'sq-pay-1',
        ]);
        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 20.00,
        ]);

        $adjustment = TaxAdjustment::factory()->create([
            'invoice_id' => $invoice->id,
            'adjustment_number' => 'TAN-SHOP-1001',
            'total_amount' => -10.00,
        ]);
        InvoicePaymentAllocation::factory()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'tax_adjustment_id' => $adjustment->id,
            'allocated_amount' => -10.00,
        ]);

        $refund = Payment::factory()->create([
            'user_id' => $customer->id,
            'refund_of_payment_id' => $payment->id,
            'kind' => Payment::KIND_REFUND,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'total_amount' => 10.00,
            'gateway_provider' => 'square',
            'gateway_reference_id' => 'sq-refund-1',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.invoice.edit', $invoice))
            ->assertOk()
            ->assertSee('Associated Payments')
            ->assertSee('#'.$payment->id)
            ->assertSee('#'.$refund->id)
            ->assertSee('Refund for #'.$payment->id)
            ->assertSee('-$10.00', false)
            ->assertSee('TAN-SHOP-1001');
    }

    public function test_admin_can_cancel_part_of_an_order_item_and_release_only_reserved_stock_for_that_item(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();

        $productOne = Product::factory()->create([
            'inventory_quantity' => 3,
        ]);
        $productTwo = Product::factory()->create([
            'inventory_quantity' => 7,
        ]);

        $itemOne = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productOne->id,
            'product_title' => 'Tracked Kit',
            'quantity' => 3,
            'available_now_quantity' => 2,
            'delayed_quantity' => 1,
            'inventory_reserved_quantity' => 2,
        ]);
        $itemTwo = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productTwo->id,
            'product_title' => 'Other Kit',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.cancel', [
                'storeOrder' => $order,
                'storeOrderItem' => $itemOne,
            ]), [
                'available_quantity' => 1,
                'delayed_quantity' => 1,
                'reason' => 'Customer asked to remove these units.',
            ])
            ->assertRedirect();

        $itemOne = $itemOne->fresh();
        $itemTwo = $itemTwo->fresh();

        $this->assertSame(1, (int) $itemOne->cancelled_available_quantity);
        $this->assertSame(1, (int) $itemOne->cancelled_delayed_quantity);
        $this->assertSame(1, (int) $itemOne->inventory_reserved_quantity);
        $this->assertSame(4, (int) $productOne->fresh()->inventory_quantity);

        $this->assertSame(0, (int) $itemTwo->cancelled_available_quantity);
        $this->assertSame(0, (int) $itemTwo->cancelled_delayed_quantity);
        $this->assertSame(1, (int) $itemTwo->inventory_reserved_quantity);
        $this->assertSame(7, (int) $productTwo->fresh()->inventory_quantity);

        $this->assertDatabaseHas('store_order_item_cancellations', [
            'store_order_item_id' => $itemOne->id,
            'available_quantity' => 1,
            'delayed_quantity' => 1,
            'reason' => 'Customer asked to remove these units.',
        ]);
    }

    public function test_staged_cancellation_prefers_delayed_quantity_before_reserved_stock(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();
        $product = Product::factory()->create([
            'inventory_quantity' => 0,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Backorder Heavy Kit',
            'quantity' => 10,
            'available_now_quantity' => 1,
            'delayed_quantity' => 9,
            'inventory_reserved_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.order.update', $order), [
                'status' => StoreOrder::STATUS_PROCESSING,
                'notes' => '',
                'public_notes' => '',
                'item_actions_json' => json_encode([
                    [
                        'type' => 'cancel',
                        'item_id' => $item->id,
                        'quantity' => 1,
                        'reason' => 'Customer cancelled one backordered unit.',
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $item = $item->fresh();

        $this->assertSame(0, (int) $item->cancelled_available_quantity);
        $this->assertSame(1, (int) $item->cancelled_delayed_quantity);
        $this->assertSame(1, (int) $item->inventory_reserved_quantity);
        $this->assertSame(0, (int) $product->fresh()->inventory_quantity);
        $this->assertDatabaseHas('store_order_item_cancellations', [
            'store_order_item_id' => $item->id,
            'available_quantity' => 0,
            'delayed_quantity' => 1,
            'reason' => 'Customer cancelled one backordered unit.',
        ]);
    }

    public function test_admin_item_cancellation_creates_tax_adjustment_square_refund_and_immediate_emails(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $admin = $this->makeAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Jamie',
            'surname' => 'Example',
            'email' => 'jamie@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-SHOP-1001',
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 18.18,
            'gst_amount' => 1.82,
            'total_amount' => 20.00,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $order = StoreOrder::factory()->create([
            'user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_PROCESSING,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_name' => 'Jamie Example',
            'billing_email' => 'jamie@example.com',
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'subtotal_amount' => 20.00,
            'shipping_amount' => 0.00,
            'discount_amount' => 0.00,
            'gst_amount' => 1.82,
            'total_amount' => 20.00,
            'paid_at' => now(),
        ]);

        $invoiceLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'kind' => 'product',
            'description' => 'Tracked Kit',
            'quantity' => 2,
            'unit_price_ex_tax' => 9.09,
            'tax_rate' => 0.1000,
            'line_total_ex_tax' => 18.18,
            'tax_amount' => 1.82,
            'line_total_inc_tax' => 20.00,
        ]);

        $product = Product::factory()->create([
            'inventory_quantity' => 3,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'invoice_line_id' => $invoiceLine->id,
            'product_title' => 'Tracked Kit',
            'quantity' => 2,
            'available_now_quantity' => 2,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 2,
            'unit_price' => 10.00,
            'tax_rate' => 0.1000,
            'line_price_amount' => 20.00,
            'line_gst_amount' => 1.82,
            'line_total_amount' => 20.00,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'reference' => 'Store order '.$order->order_number,
            'total_amount' => 20.00,
            'gst_amount' => 1.82,
            'gateway_provider' => 'square',
            'gateway_status' => 'COMPLETED',
            'square_payment_id' => 'sq-payment-1',
            'square_paid_money_amount' => 2000,
            'square_refunded_money_amount' => 0,
        ]);

        InvoicePaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 20.00,
        ]);

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        $squareApi->shouldReceive('createRefund')->once()->andReturn([
            'refund' => [
                'id' => 'sq-refund-1',
                'status' => 'COMPLETED',
                'amount_money' => ['amount' => 1000],
            ],
        ]);
        $this->app->instance(SquareApiService::class, $squareApi);

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.cancel', [
                'storeOrder' => $order,
                'storeOrderItem' => $item,
            ]), [
                'available_quantity' => 1,
                'delayed_quantity' => 0,
                'reason' => 'Customer requested a partial cancellation.',
            ])
            ->assertRedirect();

        $adjustment = TaxAdjustment::query()->first();
        $refundPayment = Payment::query()->where('refund_of_payment_id', $payment->id)->first();
        $update = StoreOrderUpdate::query()->where('store_order_item_id', $item->id)->first();

        $this->assertNotNull($adjustment);
        $this->assertNotNull($refundPayment);
        $this->assertNotNull($update);
        $this->assertSame(-10.00, round((float) $adjustment->total_amount, 2));
        $this->assertSame(1000, (int) $payment->fresh()->square_refunded_money_amount);
        $this->assertNotNull($update->customer_digest_queued_at);
        $this->assertNotNull($update->admin_digest_queued_at);

        $this->assertDatabaseHas('tax_adjustment_lines', [
            'tax_adjustment_id' => $adjustment->id,
            'invoice_line_id' => $invoiceLine->id,
            'quantity' => 1,
            'line_total_inc_tax' => 10.00,
        ]);
        $this->assertDatabaseHas('invoice_payment_allocations', [
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'tax_adjustment_id' => $adjustment->id,
            'allocated_amount' => -10.00,
        ]);

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($order): bool {
            return $job->to === 'jamie@example.com'
                && $job->mailable instanceof StoreOrderCustomerUpdateNotice
                && $job->mailable->subjectLine === 'Some items on your order '.$order->order_number.' were cancelled';
        });
        Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job): bool => $job->to === 'ops@example.com' && $job->mailable instanceof StoreOrderAdminUpdateNotice);
        Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job): bool => $job->to === 'jamie@example.com' && $job->mailable instanceof \App\Mail\InvoiceDocumentBundle);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'jamie@example.com'
                && $job->mailable instanceof PaymentReceiptPdf
                && $job->mailable->isRefund === true;
        });
    }

    public function test_admin_can_apply_multiple_staged_item_cancellations_with_one_combined_save(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $admin = $this->makeAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Jordan',
            'surname' => 'Example',
            'email' => 'jordan@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-SHOP-1004',
            'billing_name' => 'Jordan Example',
            'billing_email' => 'jordan@example.com',
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 18.18,
            'gst_amount' => 1.82,
            'total_amount' => 20.00,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $order = StoreOrder::factory()->create([
            'user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_PROCESSING,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_name' => 'Jordan Example',
            'billing_email' => 'jordan@example.com',
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'subtotal_amount' => 20.00,
            'shipping_amount' => 0.00,
            'discount_amount' => 0.00,
            'gst_amount' => 1.82,
            'total_amount' => 20.00,
            'paid_at' => now(),
        ]);

        $firstLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'kind' => 'product',
            'description' => 'Queued Kit One',
            'quantity' => 1,
            'unit_price_ex_tax' => 9.09,
            'tax_rate' => 0.1000,
            'line_total_ex_tax' => 9.09,
            'tax_amount' => 0.91,
            'line_total_inc_tax' => 10.00,
        ]);
        $secondLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 2,
            'kind' => 'product',
            'description' => 'Queued Kit Two',
            'quantity' => 1,
            'unit_price_ex_tax' => 9.09,
            'tax_rate' => 0.1000,
            'line_total_ex_tax' => 9.09,
            'tax_amount' => 0.91,
            'line_total_inc_tax' => 10.00,
        ]);

        $productOne = Product::factory()->create([
            'inventory_quantity' => 0,
        ]);
        $productTwo = Product::factory()->create([
            'inventory_quantity' => 0,
        ]);

        $itemOne = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productOne->id,
            'invoice_line_id' => $firstLine->id,
            'product_title' => 'Queued Kit One',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
            'unit_price' => 10.00,
            'tax_rate' => 0.1000,
            'line_price_amount' => 10.00,
            'line_gst_amount' => 0.91,
            'line_total_amount' => 10.00,
        ]);
        $itemTwo = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productTwo->id,
            'invoice_line_id' => $secondLine->id,
            'product_title' => 'Queued Kit Two',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
            'unit_price' => 10.00,
            'tax_rate' => 0.1000,
            'line_price_amount' => 10.00,
            'line_gst_amount' => 0.91,
            'line_total_amount' => 10.00,
        ]);

        $payment = Payment::factory()->create([
            'user_id' => $customer->id,
            'created_by' => $admin->id,
            'kind' => Payment::KIND_PAYMENT,
            'payment_method' => Payment::PAYMENT_METHOD_CREDIT_CARD,
            'reference' => 'Store order '.$order->order_number,
            'total_amount' => 20.00,
            'gst_amount' => 1.82,
            'gateway_provider' => 'square',
            'gateway_status' => 'COMPLETED',
            'square_payment_id' => 'sq-payment-batch-1',
            'square_paid_money_amount' => 2000,
            'square_refunded_money_amount' => 0,
        ]);

        InvoicePaymentAllocation::query()->create([
            'invoice_id' => $invoice->id,
            'payment_id' => $payment->id,
            'allocated_amount' => 20.00,
        ]);

        $squareApi = Mockery::mock(SquareApiService::class);
        $squareApi->shouldReceive('isEnabled')->andReturn(true);
        $squareApi->shouldReceive('createRefund')->once()->andReturn([
            'refund' => [
                'id' => 'sq-refund-batch-1',
                'status' => 'COMPLETED',
                'amount_money' => ['amount' => 2000],
            ],
        ]);
        $this->app->instance(SquareApiService::class, $squareApi);

        $this->actingAs($admin)
            ->put(route('admin.shop.order.update', $order), [
                'status' => StoreOrder::STATUS_PROCESSING,
                'notes' => '',
                'public_notes' => '',
                'item_actions_json' => json_encode([
                    [
                        'type' => 'cancel',
                        'item_id' => $itemOne->id,
                        'available_quantity' => 1,
                        'delayed_quantity' => 0,
                        'reason' => 'Queued cancellation for item one.',
                    ],
                    [
                        'type' => 'cancel',
                        'item_id' => $itemTwo->id,
                        'available_quantity' => 1,
                        'delayed_quantity' => 0,
                        'reason' => 'Queued cancellation for item two.',
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $adjustment = TaxAdjustment::query()->with('lines')->first();
        $refundPayments = Payment::query()->where('refund_of_payment_id', $payment->id)->get();
        $updates = StoreOrderUpdate::query()->where('store_order_id', $order->id)->get();

        $this->assertNotNull($adjustment);
        $this->assertCount(2, $adjustment->lines);
        $this->assertSame(-20.00, round((float) $adjustment->total_amount, 2));
        $this->assertCount(1, $refundPayments);
        $this->assertSame(2000, (int) $payment->fresh()->square_refunded_money_amount);
        $this->assertCount(2, $updates);
        $this->assertTrue($updates->every(fn (StoreOrderUpdate $update): bool => $update->customer_digest_queued_at !== null));
        $this->assertTrue($updates->every(fn (StoreOrderUpdate $update): bool => $update->admin_digest_queued_at !== null));

        $this->assertDatabaseHas('tax_adjustment_lines', [
            'tax_adjustment_id' => $adjustment->id,
            'invoice_line_id' => $firstLine->id,
            'line_total_inc_tax' => 10.00,
        ]);
        $this->assertDatabaseHas('tax_adjustment_lines', [
            'tax_adjustment_id' => $adjustment->id,
            'invoice_line_id' => $secondLine->id,
            'line_total_inc_tax' => 10.00,
        ]);
        $this->assertSame(1, (int) $productOne->fresh()->inventory_quantity);
        $this->assertSame(1, (int) $productTwo->fresh()->inventory_quantity);
        $this->assertSame(1, (int) $itemOne->fresh()->cancelled_available_quantity);
        $this->assertSame(1, (int) $itemTwo->fresh()->cancelled_available_quantity);

        $this->assertCount(1, Queue::pushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'jordan@example.com'
                && $job->mailable instanceof StoreOrderCustomerUpdateNotice;
        }));
        Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job): bool => $job->to === 'ops@example.com' && $job->mailable instanceof StoreOrderAdminUpdateNotice);
        $this->assertCount(0, Queue::pushed(SendEmail::class, fn (SendEmail $job): bool => $job->to === 'jordan@example.com' && $job->mailable instanceof \App\Mail\InvoiceDocumentBundle));
        $this->assertCount(1, Queue::pushed(SendEmail::class, function (SendEmail $job): bool {
            return $job->to === 'jordan@example.com'
                && $job->mailable instanceof PaymentReceiptPdf
                && $job->mailable->isRefund === true;
        }));
        $this->assertSame(StoreOrder::STATUS_CANCELLED, (string) $order->fresh()->status);
    }

    public function test_order_status_and_public_note_changes_send_immediate_customer_updates_without_admin_self_notifications(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder()->forceFill([
            'billing_name' => 'Taylor Example',
            'billing_email' => 'taylor@example.com',
        ]);
        $order->save();

        $this->actingAs($admin)
            ->put(route('admin.shop.order.update', $order), [
                'status' => StoreOrder::STATUS_CANCELLED,
                'notes' => 'Customer requested the order be closed out.',
                'public_notes' => 'Your order has been cancelled and refunded.',
                'item_actions_json' => '',
            ])
            ->assertRedirect();

        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($order): bool {
            return $job->to === 'taylor@example.com'
                && $job->mailable instanceof StoreOrderCustomerUpdateNotice
                && $job->mailable->subjectLine === 'Your order '.$order->order_number.' has been cancelled';
        });
        Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job): bool => $job->to === 'ops@example.com' && $job->mailable instanceof StoreOrderAdminUpdateNotice);

        $this->assertSame(2, StoreOrderUpdate::query()->where('store_order_id', $order->id)->count());
        $this->assertSame(2, StoreOrderUpdate::query()->where('store_order_id', $order->id)->whereNotNull('customer_digest_queued_at')->count());
        $this->assertSame(2, StoreOrderUpdate::query()->where('store_order_id', $order->id)->whereNotNull('admin_digest_queued_at')->count());
    }

    public function test_admin_item_cancellation_uses_net_item_amount_after_allocated_discount(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $admin = $this->makeAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Casey',
            'surname' => 'Buyer',
            'email' => 'casey@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-SHOP-1002',
            'billing_name' => 'Casey Buyer',
            'billing_email' => 'casey@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'subtotal_amount' => 81.82,
            'gst_amount' => 8.18,
            'total_amount' => 90.00,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $order = StoreOrder::factory()->create([
            'user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_PENDING_PAYMENT,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_name' => 'Casey Buyer',
            'billing_email' => 'casey@example.com',
            'subtotal_amount' => 100.00,
            'shipping_amount' => 0.00,
            'discount_amount' => 10.00,
            'coupon_code' => 'SAVE10',
            'coupon_type' => Coupon::DISCOUNT_TYPE_FIXED_AMOUNT,
            'gst_amount' => 8.18,
            'total_amount' => 90.00,
            'paid_at' => null,
        ]);

        $firstLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'kind' => 'product',
            'description' => 'Discounted Kit',
            'quantity' => 1,
            'unit_price_ex_tax' => 54.55,
            'tax_rate' => 0.1000,
            'line_total_ex_tax' => 54.55,
            'tax_amount' => 5.45,
            'line_total_inc_tax' => 60.00,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 2,
            'kind' => 'product',
            'description' => 'Second Kit',
            'quantity' => 1,
            'unit_price_ex_tax' => 36.36,
            'tax_rate' => 0.1000,
            'line_total_ex_tax' => 36.36,
            'tax_amount' => 3.64,
            'line_total_inc_tax' => 40.00,
        ]);
        InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 3,
            'kind' => 'discount',
            'description' => 'Voucher SAVE10',
            'quantity' => 1,
            'unit_price_ex_tax' => -9.09,
            'tax_rate' => 0.1000,
            'line_total_ex_tax' => -9.09,
            'tax_amount' => -0.91,
            'line_total_inc_tax' => -10.00,
        ]);

        $productOne = Product::factory()->create(['inventory_quantity' => 1]);
        $productTwo = Product::factory()->create(['inventory_quantity' => 1]);

        $itemOne = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productOne->id,
            'invoice_line_id' => $firstLine->id,
            'product_title' => 'Discounted Kit',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 1,
            'unit_price' => 60.00,
            'tax_rate' => 0.1000,
            'line_price_amount' => 60.00,
            'line_gst_amount' => 5.45,
            'line_total_amount' => 60.00,
        ]);
        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $productTwo->id,
            'product_title' => 'Second Kit',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 1,
            'unit_price' => 40.00,
            'tax_rate' => 0.1000,
            'line_price_amount' => 40.00,
            'line_gst_amount' => 3.64,
            'line_total_amount' => 40.00,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.cancel', [
                'storeOrder' => $order,
                'storeOrderItem' => $itemOne,
            ]), [
                'available_quantity' => 1,
                'delayed_quantity' => 0,
                'reason' => 'Customer removed the discounted item.',
            ])
            ->assertRedirect();

        $adjustment = TaxAdjustment::query()->first();

        $this->assertNotNull($adjustment);
        $this->assertSame(-54.00, round((float) $adjustment->total_amount, 2));
        $this->assertDatabaseHas('tax_adjustment_lines', [
            'tax_adjustment_id' => $adjustment->id,
            'invoice_line_id' => $firstLine->id,
            'line_total_inc_tax' => 54.00,
        ]);
        $this->assertSame(0, Payment::query()->whereNotNull('refund_of_payment_id')->count());
    }

    public function test_full_single_item_cancellation_also_credits_remaining_shipping_amount(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');

        $admin = $this->makeAdminUser();
        $customer = User::factory()->create([
            'firstname' => 'Morgan',
            'surname' => 'Buyer',
            'email' => 'morgan@example.com',
        ]);

        $invoice = Invoice::factory()->create([
            'user_id' => $customer->id,
            'invoice_number' => 'INV-SHOP-1003',
            'billing_name' => 'Morgan Buyer',
            'billing_email' => 'morgan@example.com',
            'status' => Invoice::STATUS_ISSUED,
            'subtotal_amount' => 22.73,
            'gst_amount' => 2.27,
            'total_amount' => 25.00,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
            'due_date' => now()->addDays(7)->toDateString(),
        ]);

        $order = StoreOrder::factory()->create([
            'user_id' => $customer->id,
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_PENDING_PAYMENT,
            'contains_physical' => true,
            'contains_digital' => false,
            'billing_name' => 'Morgan Buyer',
            'billing_email' => 'morgan@example.com',
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'subtotal_amount' => 20.00,
            'shipping_amount' => 5.00,
            'discount_amount' => 0.00,
            'gst_amount' => 2.27,
            'total_amount' => 25.00,
            'paid_at' => null,
        ]);

        $productLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 1,
            'kind' => 'product',
            'description' => 'Single Item Kit',
            'quantity' => 1,
            'unit_price_ex_tax' => 18.18,
            'tax_rate' => 0.1000,
            'line_total_ex_tax' => 18.18,
            'tax_amount' => 1.82,
            'line_total_inc_tax' => 20.00,
        ]);
        $shippingLine = InvoiceLine::factory()->create([
            'invoice_id' => $invoice->id,
            'line_number' => 2,
            'kind' => 'shipping',
            'description' => 'Regular shipping',
            'quantity' => 1,
            'unit_price_ex_tax' => 4.55,
            'tax_rate' => 0.1000,
            'line_total_ex_tax' => 4.55,
            'tax_amount' => 0.45,
            'line_total_inc_tax' => 5.00,
        ]);

        $product = Product::factory()->create([
            'inventory_quantity' => 1,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'invoice_line_id' => $productLine->id,
            'product_title' => 'Single Item Kit',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'inventory_reserved_quantity' => 1,
            'unit_price' => 20.00,
            'tax_rate' => 0.1000,
            'line_price_amount' => 20.00,
            'line_gst_amount' => 1.82,
            'line_total_amount' => 20.00,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.cancel', [
                'storeOrder' => $order,
                'storeOrderItem' => $item,
            ]), [
                'available_quantity' => 1,
                'delayed_quantity' => 0,
                'reason' => 'Customer cancelled the full order.',
            ])
            ->assertRedirect();

        $adjustment = TaxAdjustment::query()->first();

        $this->assertNotNull($adjustment);
        $this->assertSame(-25.00, round((float) $adjustment->total_amount, 2));
        $this->assertDatabaseHas('tax_adjustment_lines', [
            'tax_adjustment_id' => $adjustment->id,
            'invoice_line_id' => $productLine->id,
            'line_total_inc_tax' => 20.00,
        ]);
        $this->assertDatabaseHas('tax_adjustment_lines', [
            'tax_adjustment_id' => $adjustment->id,
            'invoice_line_id' => $shippingLine->id,
            'line_total_inc_tax' => 5.00,
        ]);
    }

    public function test_admin_can_add_tracking_to_available_and_delayed_item_quantities(): void
    {
        Queue::fake();
        config()->set('mail.admin_bcc', 'ops@example.com');
        SiteOption::query()->updateOrCreate([
            'name' => ShopShippingSettings::TRACKING_LINK_TEMPLATES_OPTION,
        ], [
            'value' => json_encode([
                'Sendle' => 'https://tracking.example.test/delayed?id={{tracking_number}}',
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) ?: '{}',
        ]);

        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();
        $product = Product::factory()->create([
            'inventory_quantity' => 3,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Split Shipment Kit',
            'quantity' => 3,
            'available_now_quantity' => 2,
            'delayed_quantity' => 1,
            'inventory_reserved_quantity' => 2,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.tracking.store', [
                'storeOrder' => $order,
                'storeOrderItem' => $item,
            ]), [
                'tracking_mode' => 'none',
                'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
                'quantity' => 1,
                'parcel_number' => 1,
                'carrier' => 'Australia Post',
                'tracking_number' => null,
                'tracking_url' => null,
                'notes' => 'First parcel dispatched.',
                'dispatched_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        $this->actingAs($admin)
            ->post(route('admin.shop.order.item.tracking.store', [
                'storeOrder' => $order,
                'storeOrderItem' => $item,
            ]), [
                'tracking_mode' => 'tracking_number',
                'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED,
                'quantity' => 1,
                'parcel_number' => 2,
                'carrier' => 'Sendle',
                'tracking_number' => 'TRACK-DELAYED-1',
                'tracking_url' => '',
                'notes' => 'Backorder parcel dispatched later.',
                'dispatched_at' => now()->toDateString(),
            ])
            ->assertRedirect();

        $item = $item->fresh('trackingEntries');

        $this->assertCount(2, $item->trackingEntries);
        $this->assertSame(1, (int) $item->inventory_reserved_quantity);
        $this->assertSame(2, $item->trackedQuantity());
        $this->assertSame(1, $item->remainingFulfillableQuantity());
        $this->assertSame(StoreOrder::STATUS_PARTIALLY_SHIPPED, (string) $order->fresh()->status);

        $availableTracking = $item->trackingEntries->firstWhere('carrier', 'Australia Post');
        $delayedTracking = $item->trackingEntries->firstWhere('tracking_number', 'TRACK-DELAYED-1');

        $this->assertNotNull($availableTracking);
        $this->assertSame(StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE, $availableTracking->shipment_type);
        $this->assertSame(1, (int) $availableTracking->parcel_number);
        $this->assertNull($availableTracking->tracking_number);
        $this->assertNull($availableTracking->tracking_url);
        $this->assertNotNull($delayedTracking);
        $this->assertSame(StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED, $delayedTracking->shipment_type);
        $this->assertSame(2, (int) $delayedTracking->parcel_number);
        $this->assertSame('https://tracking.example.test/delayed?id=TRACK-DELAYED-1', (string) $delayedTracking->tracking_url);
        $this->assertSame(3, (int) $product->fresh()->inventory_quantity);
        Queue::assertPushed(SendEmail::class, function (SendEmail $job) use ($order): bool {
            return $job->to === strtolower((string) $order->billing_email)
                && $job->mailable instanceof StoreOrderCustomerUpdateNotice
                && $job->mailable->subjectLine === 'Part of your order '.$order->order_number.' has now shipped';
        });
        Queue::assertNotPushed(SendEmail::class, fn (SendEmail $job): bool => $job->to === 'ops@example.com' && $job->mailable instanceof StoreOrderAdminUpdateNotice);
    }

    public function test_order_becomes_shipped_when_all_physical_quantity_has_dispatch_entries(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();
        $product = Product::factory()->create([
            'inventory_quantity' => 4,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Complete Dispatch Kit',
            'quantity' => 3,
            'available_now_quantity' => 2,
            'delayed_quantity' => 1,
            'inventory_reserved_quantity' => 2,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.order.update', $order), [
                'status' => StoreOrder::STATUS_PROCESSING,
                'notes' => '',
                'public_notes' => '',
                'item_actions_json' => json_encode([
                    [
                        'type' => 'tracking',
                        'item_id' => $item->id,
                        'tracking_mode' => 'tracking_number',
                        'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
                        'quantity' => 2,
                        'parcel_number' => 1,
                        'carrier' => 'Australia Post',
                        'tracking_number' => 'TRACK-READY-2',
                        'tracking_url' => '',
                        'notes' => 'Reserved items sent.',
                        'dispatched_at' => now()->toDateString(),
                    ],
                    [
                        'type' => 'tracking',
                        'item_id' => $item->id,
                        'tracking_mode' => 'tracking_number',
                        'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_DELAYED,
                        'quantity' => 1,
                        'parcel_number' => 2,
                        'carrier' => 'Sendle',
                        'tracking_number' => 'TRACK-LATE-2',
                        'tracking_url' => 'https://tracking.example.test/complete',
                        'notes' => 'Backorder sent later.',
                        'dispatched_at' => now()->toDateString(),
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $this->assertSame(StoreOrder::STATUS_SHIPPED, (string) $order->fresh()->status);
    }

    public function test_order_becomes_shipped_when_single_physical_item_is_fully_dispatched(): void
    {
        $admin = $this->makeAdminUser();
        $order = $this->makePhysicalOrder();
        $product = Product::factory()->create([
            'inventory_quantity' => 1,
        ]);

        $item = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_title' => 'Single Dispatch Kit',
            'quantity' => 1,
            'available_now_quantity' => 1,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.order.update', $order), [
                'status' => StoreOrder::STATUS_PROCESSING,
                'notes' => '',
                'public_notes' => '',
                'item_actions_json' => json_encode([
                    [
                        'type' => 'tracking',
                        'item_id' => $item->id,
                        'tracking_mode' => 'tracking_number',
                        'shipment_type' => StoreOrderItemTracking::SHIPMENT_TYPE_AVAILABLE,
                        'quantity' => 1,
                        'parcel_number' => 1,
                        'carrier' => 'Australia Post',
                        'tracking_number' => 'TRACK-SINGLE-1',
                        'tracking_url' => '',
                        'notes' => 'Single item sent.',
                        'dispatched_at' => now()->toDateString(),
                    ],
                ], JSON_THROW_ON_ERROR),
            ])
            ->assertRedirect();

        $this->assertSame(StoreOrder::STATUS_SHIPPED, (string) $order->fresh()->status);
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

    private function makePhysicalOrder(): StoreOrder
    {
        $invoice = Invoice::factory()->create([
            'status' => Invoice::STATUS_PAID,
            'subtotal_amount' => 0,
            'gst_amount' => 0,
            'total_amount' => 0,
            'issue_date' => now()->toDateString(),
            'issued_at' => now(),
        ]);

        return StoreOrder::factory()->create([
            'invoice_id' => $invoice->id,
            'status' => StoreOrder::STATUS_PROCESSING,
            'contains_physical' => true,
            'contains_digital' => false,
            'shipping_method' => 'Regular shipping',
            'shipping_method_code' => 'regular',
            'subtotal_amount' => 0,
            'shipping_amount' => 0,
            'discount_amount' => 0,
            'gst_amount' => 0,
            'total_amount' => 0,
            'paid_at' => now(),
        ]);
    }
}
