<?php

namespace Tests\Feature;

use App\Models\Invoice;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\StoreCartService;
use App\Services\StoreInventoryAllocatorService;
use App\Services\StoreOrderService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class SharedInventoryTest extends TestCase
{
    use RefreshDatabase;

    private function product(): array
    {
        $product = Product::factory()->create(['shared_inventory' => true, 'inventory_units' => 5, 'inventory_quantity' => 60, 'price' => 10]);
        $variant = ProductVariant::factory()->create(['product_id' => $product->id, 'inventory_units' => 10, 'inventory_quantity' => 99]);

        return [$product, $variant];
    }

    private function payload(Product $product, ?ProductVariant $variant, int $quantity): array
    {
        return ['invoice_number' => 'SHARED-TEST', 'issue_date' => today()->toDateString(), 'line_items_json' => json_encode([[
            'kind' => 'product', 'description' => 'Pigs', 'quantity' => $quantity, 'unit_price_inc_tax' => 10,
            'gst_applicable' => true, 'source_type' => Product::class, 'source_id' => $product->id,
            'details_json' => ['variant_id' => $variant?->id],
        ]])];
    }

    public function test_invoice_reservations_are_idempotent_adjustable_atomic_and_released_on_delete(): void
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
        [$product, $variant] = $this->product();
        $this->assertSame(12, $product->availableInventory());
        $this->assertSame(6, $product->availableInventory($variant));
        $payload = $this->payload($product, $variant, 2) + ['user_id' => $admin->id];
        $this->post(route('admin.invoice.store'), $payload)->assertSessionHasNoErrors();
        $invoice = Invoice::where('invoice_number', 'SHARED-TEST')->firstOrFail();
        $this->assertSame(40, $product->fresh()->inventory_quantity);
        $this->assertSame(99, $variant->fresh()->inventory_quantity);
        $this->put(route('admin.invoice.update', $invoice), $payload)->assertSessionHasNoErrors();
        $this->assertSame(40, $product->fresh()->inventory_quantity);
        $this->put(route('admin.invoice.update', $invoice), $this->payload($product, $variant, 3) + ['user_id' => $admin->id])->assertSessionHasNoErrors();
        $this->assertSame(30, $product->fresh()->inventory_quantity);
        $this->put(route('admin.invoice.update', $invoice), $this->payload($product, $variant, 7) + ['user_id' => $admin->id])->assertSessionHasErrors('line_items');
        $this->assertSame(30, $product->fresh()->inventory_quantity);
        $this->assertSame(3.0, (float) $invoice->fresh()->lines()->first()->quantity);
        $variant->update(['inventory_units' => 20]);
        $this->delete(route('admin.invoice.destroy', $invoice))->assertSessionHasNoErrors();
        $this->assertSame(60, $product->fresh()->inventory_quantity);
    }

    public function test_store_checkout_consumes_shared_units_and_cancellation_uses_original_pack_size(): void
    {
        Queue::fake();
        [$product, $variant] = $this->product();
        $this->post(route('shop.cart.add', $product), ['quantity' => 2, 'product_variant_id' => $variant->id])->assertSessionHasNoErrors();
        $service = app(StoreOrderService::class);
        $order = $service->createFromCart(app(StoreCartService::class)->lines(), [
            'billing_name' => 'Test Person', 'billing_email' => 'test@example.com', 'billing_phone' => '0400123456',
            'shipping_name' => 'Test Person', 'shipping_phone' => '0400123456', 'shipping_address' => '1 Test St',
            'shipping_city' => 'Brisbane', 'shipping_state' => 'QLD', 'shipping_postcode' => '4000', 'shipping_country' => 'Australia',
            'shipping_method_code' => 'regular',
        ], sendNotifications: false);
        $this->assertSame(40, $product->fresh()->inventory_quantity);
        $this->assertSame(2, $order->items->first()->inventory_reserved_quantity);
        $this->assertSame(10, $order->items->first()->inventory_units);
        $variant->update(['inventory_units' => 20]);
        $service->updateOrderStatus($order, StoreOrder::STATUS_CANCELLED, suppressAdminNotifications: true, suppressCustomerNotifications: true);
        $this->assertSame(60, $product->fresh()->inventory_quantity);
    }

    public function test_backorders_allocate_whole_packs_from_the_shared_pool(): void
    {
        Queue::fake();
        [$product, $variant] = $this->product();
        $product->update(['inventory_quantity' => 17]);
        $order = StoreOrder::factory()->create(['paid_at' => now()]);
        $ten = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id, 'product_id' => $product->id, 'product_variant_id' => $variant->id,
            'shared_inventory' => true, 'inventory_units' => 10, 'quantity' => 2,
            'available_now_quantity' => 0, 'delayed_quantity' => 2, 'delayed_fulfilment_type' => 'backorder',
        ]);
        $five = StoreOrderItem::factory()->create([
            'store_order_id' => $order->id, 'product_id' => $product->id,
            'shared_inventory' => true, 'inventory_units' => 5, 'quantity' => 1,
            'available_now_quantity' => 0, 'delayed_quantity' => 1, 'delayed_fulfilment_type' => 'backorder',
        ]);
        app(StoreInventoryAllocatorService::class)->allocateForVariant($variant);
        $this->assertSame(2, $product->fresh()->inventory_quantity);
        $this->assertSame(1, $ten->fresh()->inventory_reserved_quantity);
        $this->assertSame(1, $ten->fresh()->delayed_quantity);
        $this->assertSame(1, $five->fresh()->inventory_reserved_quantity);
        $this->assertSame(99, $variant->fresh()->inventory_quantity);
    }

    public function test_product_editor_saves_shared_stock_and_pack_sizes(): void
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin)->post(route('admin.shop.product.store'), [
            'title' => 'Shared pigs', 'status' => Product::STATUS_ACTIVE, 'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 10, 'shared_inventory' => 1, 'inventory_quantity' => 60, 'inventory_units' => 5,
            'variants' => [['name' => 'Ten pack', 'inventory_units' => 10, 'is_active' => 1]],
        ])->assertSessionHasNoErrors();
        $product = Product::where('title', 'Shared pigs')->firstOrFail();
        $this->assertTrue($product->shared_inventory);
        $this->assertSame(5, $product->inventory_units);
        $this->assertSame(10, $product->variants()->firstOrFail()->inventory_units);
        $this->get(route('admin.shop.product.edit', $product))->assertOk()->assertSee('Share stock across all packs and variants')->assertSee('Units in this pack');
    }

    public function test_mixed_pack_cart_shares_availability_and_checkout_backorders_the_remainder(): void
    {
        Queue::fake();
        [$product, $variant] = $this->product();
        $product->update(['inventory_quantity' => 25, 'allow_backorder' => true]);
        $variant->update(['allow_backorder' => true]);
        $this->post(route('shop.cart.add', $product), ['quantity' => 3])->assertSessionHasNoErrors();
        $this->post(route('shop.cart.add', $product), ['quantity' => 2, 'product_variant_id' => $variant->id])->assertSessionHasNoErrors();
        $lines = app(StoreCartService::class)->lines();
        $this->assertSame(4, (int) $lines->sum('available_now_quantity'));
        $this->assertSame(1, (int) $lines->sum('delayed_quantity'));
        $order = app(StoreOrderService::class)->createFromCart($lines, [
            'billing_name' => 'Test Person', 'billing_email' => 'mixed@example.com', 'billing_phone' => '0400123456',
            'shipping_name' => 'Test Person', 'shipping_phone' => '0400123456', 'shipping_address' => '1 Test St',
            'shipping_city' => 'Brisbane', 'shipping_state' => 'QLD', 'shipping_postcode' => '4000', 'shipping_country' => 'Australia',
            'shipping_method_code' => 'regular',
        ], sendNotifications: false);
        $this->assertSame(0, $product->fresh()->inventory_quantity);
        $this->assertSame(1, (int) $order->items->sum('delayed_quantity'));
        $this->assertSame(4, (int) $order->items->sum('inventory_reserved_quantity'));
    }

    public function test_issuing_then_cancelling_an_invoice_releases_its_reservation_once(): void
    {
        $admin = User::factory()->create();
        UserGroup::create(['user_id' => $admin->id, 'slug' => 'admin']);
        $this->actingAs($admin);
        [$product, $variant] = $this->product();
        $this->post(route('admin.invoice.store'), $this->payload($product, null, 3) + ['user_id' => $admin->id, 'issue_now' => 1])->assertSessionHasNoErrors();
        $invoice = Invoice::where('invoice_number', 'SHARED-TEST')->firstOrFail();
        $this->assertSame(45, $product->fresh()->inventory_quantity);
        $this->delete(route('admin.invoice.destroy', $invoice))->assertSessionHasNoErrors();
        $this->assertSame(60, $product->fresh()->inventory_quantity);
        $this->delete(route('admin.invoice.destroy', $invoice))->assertSessionHasNoErrors();
        $this->assertSame(60, $product->fresh()->inventory_quantity);
    }
}
