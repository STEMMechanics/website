<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopProductTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_shop_product(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.product.create'))
            ->assertOk()
            ->assertSee('Create Product');

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Laser Cut Catapult Kit',
                'slug' => 'laser-cut-catapult-kit',
                'category' => 'Kits',
                'sku' => 'CAT-100',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'short_description' => 'A build-at-home launcher kit.',
                'description' => 'Includes all timber parts and instructions.',
                'price' => '24.95',
                'compare_at_price' => '29.95',
                'inventory_quantity' => '12',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '3',
                'weight_grams' => '820',
                'box_only' => '0',
                'sort_order' => '5',
                'is_featured' => '1',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $this->assertDatabaseHas('products', [
            'slug' => 'laser-cut-catapult-kit',
            'category' => 'Kits',
            'sku' => 'CAT-100',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'shipping_units' => 1.00,
            'min_satchel_rank' => 3,
            'weight_grams' => 820,
            'box_only' => 0,
            'is_featured' => 1,
            'tax_rate' => 0.1000,
        ]);
    }

    public function test_admin_can_save_preorder_and_backorder_flags(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Preorder Kit',
                'slug' => 'preorder-kit',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '19.95',
                'inventory_quantity' => '0',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
                'weight_grams' => '400',
                'is_preorder' => '1',
                'preorder_shipping_estimate' => '2026-04-30',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $product = Product::query()->where('slug', 'preorder-kit')->firstOrFail();

        $this->assertTrue((bool) $product->is_preorder);
        $this->assertFalse((bool) $product->allow_backorder);
        $this->assertSame('2026-04-30', optional($product->preorder_shipping_estimate)->toDateString());

        $this->actingAs($admin)
            ->put(route('admin.shop.product.update', $product), [
                'title' => 'Preorder Kit',
                'slug' => 'preorder-kit',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '19.95',
                'inventory_quantity' => '3',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
                'weight_grams' => '400',
                'allow_backorder' => '1',
                'backorder_shipping_estimate' => '2026-05-15',
            ])
            ->assertRedirect();

        $product->refresh();

        $this->assertFalse((bool) $product->is_preorder);
        $this->assertTrue((bool) $product->allow_backorder);
        $this->assertNull($product->preorder_shipping_estimate);
        $this->assertSame('2026-05-15', optional($product->backorder_shipping_estimate)->toDateString());
    }

    public function test_admin_can_create_digital_product_without_auto_generated_licence_tiers(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'STEM Project Pack',
                'slug' => '',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_DIGITAL,
                'price' => '12.00',
                'compare_at_price' => '15.00',
                'sku' => 'DIGI-PACK',
                'private_notes' => '02/04/26 - ordered 12 from supplier',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $product = Product::query()->where('title', 'STEM Project Pack')->firstOrFail();
        $variants = $product->variants()->orderBy('sort_order')->get();

        $this->assertSame('stem-project-pack', $product->slug);
        $this->assertNull($product->low_stock_threshold);
        $this->assertSame('02/04/26 - ordered 12 from supplier', $product->private_notes);
        $this->assertCount(0, $variants);
        $this->assertSame('Home', $product->baseOptionName());
    }

    public function test_admin_can_remove_all_digital_licence_tiers_without_them_returning(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $product = Product::factory()->create([
            'title' => 'Digital Project Pack',
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 12.00,
            'status' => Product::STATUS_ACTIVE,
        ]);

        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Classroom Licence',
            'price' => 60.00,
            'sort_order' => 0,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Organisation Licence',
            'price' => 240.00,
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.product.update', $product), [
                'title' => 'Digital Project Pack',
                'slug' => $product->slug,
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_DIGITAL,
                'price' => '12.00',
                'compare_at_price' => '',
                'sku' => '',
                'variants' => [],
            ])
            ->assertRedirect();

        $this->assertCount(0, $product->fresh('variants')->variants);
    }

    public function test_admin_can_save_digital_download_files_on_a_product(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $media = Media::factory()->create([
            'name' => 'worksheet.pdf',
            'mime_type' => 'application/pdf',
            'user_id' => (string) $admin->id,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'STEM Project Pack',
                'slug' => 'stem-project-pack',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_DIGITAL,
                'price' => '12.00',
                'download_files' => $media->name,
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $product = Product::query()->where('slug', 'stem-project-pack')->firstOrFail();

        $this->assertSame(
            ['worksheet.pdf'],
            $product->fresh()->downloadMedia()->pluck('name')->all()
        );
    }

    public function test_admin_update_preserves_digital_download_files_when_submitted_as_json(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $product = Product::factory()->create([
            'title' => 'STEM Project Pack',
            'slug' => 'stem-project-pack',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 12.00,
        ]);
        $media = Media::factory()->create([
            'name' => 'worksheet.pdf',
            'mime_type' => 'application/pdf',
            'user_id' => (string) $admin->id,
        ]);

        $product->updateFiles($media->name, 'downloads');

        $this->actingAs($admin)
            ->put(route('admin.shop.product.update', $product), [
                'title' => $product->title,
                'slug' => $product->slug,
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_DIGITAL,
                'price' => '12.00',
                'download_files' => json_encode([$media->name]),
            ])
            ->assertRedirect();

        $this->assertSame(
            ['worksheet.pdf'],
            $product->fresh()->downloadMedia()->pluck('name')->all()
        );
    }

    public function test_admin_update_accepts_html_escaped_json_for_digital_download_files(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $product = Product::factory()->create([
            'title' => 'STEM Project Pack',
            'slug' => 'stem-project-pack',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 12.00,
        ]);
        $media = Media::factory()->create([
            'name' => 'worksheet.pdf',
            'mime_type' => 'application/pdf',
            'user_id' => (string) $admin->id,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.product.update', $product), [
                'title' => $product->title,
                'slug' => $product->slug,
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_DIGITAL,
                'price' => '12.00',
                'download_files' => '[&quot;worksheet.pdf&quot;]',
            ])
            ->assertRedirect();

        $this->assertSame(
            ['worksheet.pdf'],
            $product->fresh()->downloadMedia()->pluck('name')->all()
        );
    }

    public function test_admin_product_page_shows_inventory_reserved_and_awaiting_fulfilment_context(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $product = Product::factory()->create([
            'inventory_quantity' => 8,
        ]);
        $variant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'inventory_quantity' => 5,
            'name' => 'Blue',
        ]);
        $order = StoreOrder::factory()->create();

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => null,
            'quantity' => 6,
            'available_now_quantity' => 5,
            'delayed_quantity' => 1,
            'inventory_reserved_quantity' => 4,
        ]);
        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'product_variant_id' => $variant->id,
            'variant_name' => 'Blue',
            'quantity' => 3,
            'available_now_quantity' => 3,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 1,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.product.edit', $product))
            ->assertOk()
            ->assertSee('Base Inventory Quantity')
            ->assertSee('Awaiting fulfilment:')
            ->assertSee('Reserved now:')
            ->assertSee('6')
            ->assertSee('4')
            ->assertSee('Blue')
            ->assertSee('3')
            ->assertSee('1');
    }

    public function test_admin_product_index_shows_remaining_reserved_and_backorder_summary(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $product = Product::factory()->create([
            'title' => 'Inventory Summary Kit',
            'slug' => 'inventory-summary-kit',
            'inventory_quantity' => 12,
        ]);
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'inventory_quantity' => 8,
            'is_active' => true,
        ]);

        $order = StoreOrder::factory()->create();
        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $product->id,
            'quantity' => 5,
            'available_now_quantity' => 2,
            'delayed_quantity' => 3,
            'inventory_reserved_quantity' => 4,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.product.index'))
            ->assertOk()
            ->assertSeeText('Qty Remaining')
            ->assertSeeText('Inventory Summary Kit')
            ->assertSee('href="'.route('admin.shop.product.edit', $product).'"', false)
            ->assertSeeText('20 available')
            ->assertSeeText('5 awaiting fulfilment')
            ->assertSeeText('4 reserved now')
            ->assertSeeText('3 backordered');
    }

    public function test_admin_product_index_shows_digital_products_as_free_downloads(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        Product::factory()->create([
            'title' => 'Digital Guide',
            'slug' => 'digital-guide',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_DIGITAL,
            'price' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.product.index'))
            ->assertOk()
            ->assertSeeText('Digital Guide')
            ->assertSeeText('Digital')
            ->assertSeeText('Instant download')
            ->assertSeeText('Free');
    }

    public function test_actionable_filter_shows_untracked_products_with_open_fulfilment_work(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $actionableProduct = Product::factory()->create([
            'title' => 'Untracked Supplier Item',
            'inventory_quantity' => null,
        ]);
        $inactiveProduct = Product::factory()->create([
            'title' => 'Nothing Pending',
            'inventory_quantity' => null,
        ]);
        $order = StoreOrder::factory()->create();

        StoreOrderItem::factory()->create([
            'store_order_id' => $order->id,
            'product_id' => $actionableProduct->id,
            'quantity' => 22,
            'available_now_quantity' => 0,
            'delayed_quantity' => 0,
            'inventory_reserved_quantity' => 0,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.product.index', ['filter' => 'actionable']))
            ->assertOk()
            ->assertSeeText('Untracked Supplier Item')
            ->assertSeeText('Not tracked')
            ->assertSeeText('22 awaiting fulfilment')
            ->assertDontSeeText('Nothing Pending');
    }

    public function test_admin_can_save_a_named_base_option_and_variant_package_units(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 39.95,
            'shipping_units' => 1.00,
            'min_satchel_rank' => 2,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.product.update', $product), [
                'title' => $product->title,
                'slug' => $product->slug,
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '39.95',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
                'base_variant_name' => 'Starter Kit',
                'base_variant_description' => 'Uses the base product configuration.',
                'variants' => [
                    [
                        'name' => 'Extended Kit',
                        'description' => 'Adds the larger component bundle.',
                        'sku' => 'KIT-EXT',
                        'price' => '49.95',
                        'compare_at_price' => '',
                        'shipping_units' => '2.50',
                        'inventory_quantity' => '6',
                        'weight_grams' => '900',
                        'sort_order' => '0',
                        'is_active' => '1',
                    ],
                ],
            ])
            ->assertRedirect();

        $variant = $product->fresh()->variants()->firstOrFail();

        $this->assertSame('Starter Kit', $product->fresh()->base_variant_name);
        $this->assertSame('Uses the base product configuration.', $product->fresh()->base_variant_description);
        $this->assertSame('Extended Kit', (string) $variant->name);
        $this->assertSame('2.50', number_format((float) $variant->shipping_units, 2, '.', ''));
        $this->assertSame('Starter Kit', $variant->fresh()->product->variantDisplayName(null));
    }

    public function test_admin_can_save_variant_specific_fulfilment_settings(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $product = Product::factory()->create([
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 39.95,
            'inventory_quantity' => 5,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.product.update', $product), [
                'title' => $product->title,
                'slug' => $product->slug,
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '39.95',
                'inventory_quantity' => '5',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
                'variants' => [
                    [
                        'name' => 'Pre-order Blue',
                        'price' => '49.95',
                        'inventory_quantity' => '',
                        'is_preorder' => '1',
                        'preorder_shipping_estimate' => '2026-05-20',
                        'allow_backorder' => '0',
                        'backorder_shipping_estimate' => '',
                        'sort_order' => '0',
                        'is_active' => '1',
                    ],
                    [
                        'name' => 'Backorder Red',
                        'price' => '44.95',
                        'inventory_quantity' => '0',
                        'is_preorder' => '0',
                        'preorder_shipping_estimate' => '',
                        'allow_backorder' => '1',
                        'backorder_shipping_estimate' => '2026-05-28',
                        'sort_order' => '1',
                        'is_active' => '1',
                    ],
                ],
            ])
            ->assertRedirect();

        $variants = $product->fresh()->variants()->orderBy('sort_order')->get();

        $this->assertCount(2, $variants);
        $this->assertTrue((bool) $variants[0]->is_preorder);
        $this->assertFalse((bool) $variants[0]->allow_backorder);
        $this->assertSame('2026-05-20', optional($variants[0]->preorder_shipping_estimate)->toDateString());
        $this->assertFalse((bool) $variants[1]->is_preorder);
        $this->assertTrue((bool) $variants[1]->allow_backorder);
        $this->assertSame('2026-05-28', optional($variants[1]->backorder_shipping_estimate)->toDateString());
    }
}
