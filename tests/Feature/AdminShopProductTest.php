<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\ProductVariant;
use App\Models\StoreOrder;
use App\Models\StoreOrderItem;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

        $kits = ProductCategory::factory()->create([
            'name' => 'Kits',
            'slug' => 'kits',
            'sort_order' => 10,
        ]);
        $hardware = ProductCategory::factory()->create([
            'name' => 'Hardware',
            'slug' => 'hardware',
            'sort_order' => 20,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.product.create'))
            ->assertOk()
            ->assertSee('Create Product');

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Laser Cut Catapult Kit',
                'slug' => 'laser-cut-catapult-kit',
                'category_ids' => [$kits->id, $hardware->id],
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

        $product = Product::query()->where('slug', 'laser-cut-catapult-kit')->with('categories')->firstOrFail();
        $this->assertSame(['Kits', 'Hardware'], $product->categories->pluck('name')->all());
        $this->assertDatabaseHas('product_category_product', [
            'product_id' => $product->id,
            'product_category_id' => $kits->id,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('product_category_product', [
            'product_id' => $product->id,
            'product_category_id' => $hardware->id,
            'sort_order' => 1,
        ]);
    }

    public function test_admin_can_still_save_a_legacy_category_string_during_the_transition(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Legacy Catapult Kit',
                'slug' => 'legacy-catapult-kit',
                'category' => 'Kits',
                'sku' => 'LEG-100',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '24.95',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $product = Product::query()->where('slug', 'legacy-catapult-kit')->with('categories')->firstOrFail();

        $this->assertSame('Kits', $product->category);
        $this->assertSame(['Kits'], $product->categories->pluck('name')->all());
        $this->assertDatabaseHas('product_categories', [
            'name' => 'Kits',
        ]);
    }

    public function test_admin_can_save_a_product_subtitle(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Bundle Pack',
                'subtitle' => '25 pack',
                'slug' => 'bundle-pack',
                'sku' => 'BUNDLE-25',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '24.95',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $this->assertDatabaseHas('products', [
            'slug' => 'bundle-pack',
            'subtitle' => '25 pack',
        ]);
    }

    public function test_admin_can_rename_a_product_category_across_assigned_products(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $category = ProductCategory::factory()->create([
            'name' => 'Kits',
            'slug' => 'kits',
            'icon_class' => 'fa-solid fa-box',
            'sort_order' => 10,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Renamable Product',
                'slug' => 'renamable-product',
                'category_ids' => [$category->id],
                'sku' => 'REN-100',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '24.95',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $this->actingAs($admin)
            ->put(route('admin.shop.category.update', $category), [
                'name' => 'Build Kits',
                'slug' => 'kits',
                'icon_class' => 'fa-solid fa-boxes-stacked',
                'sort_order' => 10,
            ])
            ->assertRedirect();

        $product = Product::query()->where('slug', 'renamable-product')->with('categories')->firstOrFail();

        $this->assertSame(['Build Kits'], $product->categories->pluck('name')->all());
        $this->assertDatabaseHas('product_categories', [
            'id' => $category->id,
            'name' => 'Build Kits',
            'icon_class' => 'fa-solid fa-boxes-stacked',
        ]);
    }

    public function test_admin_can_reorder_product_categories_from_the_index_page(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $first = ProductCategory::factory()->create([
            'name' => 'Alpha',
            'slug' => 'alpha',
            'sort_order' => 10,
        ]);
        $middle = ProductCategory::factory()->create([
            'name' => 'Beta',
            'slug' => 'beta',
            'sort_order' => 20,
        ]);
        $last = ProductCategory::factory()->create([
            'name' => 'Gamma',
            'slug' => 'gamma',
            'sort_order' => 30,
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.category.index'))
            ->assertOk()
            ->assertSee('Move up', false)
            ->assertSee('Move down', false);

        $this->actingAs($admin)
            ->post(route('admin.shop.category.move-up', $middle))
            ->assertRedirect();

        $this->assertSame(['Beta', 'Alpha', 'Gamma'], ProductCategory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('name')
            ->all());

        $this->assertDatabaseHas('product_categories', [
            'id' => $first->id,
            'sort_order' => 20,
        ]);
        $this->assertDatabaseHas('product_categories', [
            'id' => $middle->id,
            'sort_order' => 10,
        ]);
        $this->assertDatabaseHas('product_categories', [
            'id' => $last->id,
            'sort_order' => 30,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.category.move-down', $middle))
            ->assertRedirect();

        $this->assertSame(['Alpha', 'Beta', 'Gamma'], ProductCategory::query()
            ->orderBy('sort_order')
            ->orderBy('id')
            ->pluck('name')
            ->all());
    }

    public function test_admin_can_auto_fill_the_base_sku_from_the_slug_when_it_is_blank(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Circuit Board Kit',
                'slug' => '',
                'sku' => '',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '24.95',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $this->assertDatabaseHas('products', [
            'title' => 'Circuit Board Kit',
            'slug' => 'circuit-board-kit',
            'sku' => 'circuit-board-kit',
        ]);
    }

    public function test_admin_can_make_the_auto_generated_base_sku_unique_when_the_slug_is_already_taken(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        Product::factory()->create([
            'title' => 'Existing Collision Product',
            'slug' => 'circuit-board-kit',
            'sku' => 'circuit-board-kit',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Circuit Board Kit',
                'slug' => '',
                'sku' => '',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '24.95',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $this->assertDatabaseHas('products', [
            'title' => 'Circuit Board Kit',
            'slug' => 'circuit-board-kit-2',
            'sku' => 'circuit-board-kit-2',
        ]);
    }

    public function test_admin_can_save_three_decimal_package_units_for_a_shop_product(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.product.create'))
            ->assertOk()
            ->assertSee('step="0.001"', false);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Precision Pack',
                'slug' => 'precision-pack',
                'sku' => 'PREC-001',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '9.95',
                'inventory_quantity' => '4',
                'shipping_units' => '1.234',
                'min_satchel_rank' => '2',
                'weight_grams' => '300',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $product = Product::query()->where('slug', 'precision-pack')->firstOrFail();

        $this->assertSame('1.234', number_format((float) $product->shipping_units, 3, '.', ''));
        $this->assertDatabaseHas('products', [
            'slug' => 'precision-pack',
            'shipping_units' => 1.234,
        ]);
    }

    public function test_featured_products_must_be_active(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Draft Featured Product',
                'slug' => 'draft-featured-product',
                'sku' => 'DRAFT-FEATURED',
                'status' => Product::STATUS_DRAFT,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '24.95',
                'inventory_quantity' => '12',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '1',
                'is_featured' => '1',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $this->assertDatabaseHas('products', [
            'slug' => 'draft-featured-product',
            'status' => Product::STATUS_DRAFT,
            'is_featured' => 0,
        ]);
    }

    public function test_admin_can_save_backorder_flags_and_clears_legacy_preorder_state(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $product = Product::factory()->create([
            'title' => 'Backorder Kit',
            'slug' => 'backorder-kit',
            'status' => Product::STATUS_ACTIVE,
            'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
            'price' => 19.95,
            'inventory_quantity' => 0,
            'shipping_units' => 1.00,
            'min_satchel_rank' => 2,
            'weight_grams' => 400,
            'is_preorder' => true,
            'preorder_shipping_estimate' => '2026-04-30',
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.product.update', $product), [
                'title' => 'Backorder Kit',
                'slug' => 'backorder-kit',
                'sku' => 'BACKORDER-KIT',
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

    public function test_admin_can_save_dynamic_backorder_shipping_estimates_for_a_shop_product(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-04-06 09:00:00'));

        try {
            $this->actingAs($admin)
                ->post(route('admin.shop.product.store'), [
                    'title' => 'Dynamic Backorder Kit',
                    'slug' => 'dynamic-backorder-kit',
                    'sku' => 'DYN-BACKORDER',
                    'status' => Product::STATUS_ACTIVE,
                    'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                    'price' => '19.95',
                    'inventory_quantity' => '0',
                    'shipping_units' => '1.00',
                    'min_satchel_rank' => '2',
                    'weight_grams' => '400',
                    'allow_backorder' => '1',
                    'backorder_shipping_estimate_type' => Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC,
                    'backorder_shipping_offset_days' => '7',
                ])
                ->assertRedirect(route('admin.shop.product.index'));

            $product = Product::query()->where('slug', 'dynamic-backorder-kit')->firstOrFail();

            $this->assertTrue((bool) $product->allow_backorder);
            $this->assertSame(Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC, $product->backorder_shipping_estimate_type);
            $this->assertSame(7, (int) $product->backorder_shipping_offset_days);
            $this->assertNull($product->backorder_shipping_estimate);
            $this->assertSame('2026-04-13', $product->backorderShippingEstimateLabel('Y-m-d'));
        } finally {
            Carbon::setTestNow();
        }
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
                'sku' => 'DIGI-PACK',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_DIGITAL,
                'price' => '12.00',
                'compare_at_price' => '',
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
                'sku' => 'STEM-PACK',
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
                'sku' => 'DIGI-DOWNLOADS',
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
                'sku' => 'DIGI-DOWNLOADS',
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

    public function test_admin_can_save_a_named_base_option_and_physical_variant_details(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);
        $product = Product::factory()->create([
            'sku' => 'PRODUCT-BASE-2',
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
                'sku' => 'KIT-BASE',
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
                        'inventory_quantity' => '6',
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
        $this->assertNull($variant->price);
        $this->assertNull($variant->compare_at_price);
        $this->assertNull($variant->shipping_units);
        $this->assertNull($variant->weight_grams);
        $this->assertSame('39.95', number_format((float) $variant->effectivePrice(), 2, '.', ''));
        $this->assertSame('Starter Kit', $variant->fresh()->product->variantDisplayName(null));
    }

    public function test_admin_can_save_variant_specific_backorder_settings_and_clear_legacy_preorder_state(): void
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
        $legacyPreorderVariant = ProductVariant::factory()->create([
            'product_id' => $product->id,
            'name' => 'Legacy Blue',
            'inventory_quantity' => 0,
            'is_preorder' => true,
            'preorder_shipping_estimate' => '2026-05-20',
            'sort_order' => 0,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.product.update', $product), [
                'title' => $product->title,
                'slug' => $product->slug,
                'sku' => 'BACKORDER-KIT',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '39.95',
                'inventory_quantity' => '5',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
                'variants' => [
                    [
                        'id' => $legacyPreorderVariant->id,
                        'name' => 'Legacy Blue',
                        'inventory_quantity' => '0',
                        'allow_backorder' => '1',
                        'backorder_shipping_estimate' => '2026-05-20',
                        'sort_order' => '0',
                        'is_active' => '1',
                    ],
                    [
                        'name' => 'Backorder Red',
                        'inventory_quantity' => '',
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
        $this->assertFalse((bool) $variants[0]->is_preorder);
        $this->assertTrue((bool) $variants[0]->allow_backorder);
        $this->assertNull($variants[0]->preorder_shipping_estimate);
        $this->assertSame('2026-05-20', optional($variants[0]->backorder_shipping_estimate)->toDateString());
        $this->assertFalse((bool) $variants[1]->is_preorder);
        $this->assertTrue((bool) $variants[1]->allow_backorder);
        $this->assertSame('2026-05-28', optional($variants[1]->backorder_shipping_estimate)->toDateString());
    }

    public function test_admin_can_save_variant_specific_dynamic_backorder_settings(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        Carbon::setTestNow(Carbon::parse('2026-04-06 09:00:00'));

        try {
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
                    'sku' => 'BACKORDER-KIT',
                    'status' => Product::STATUS_ACTIVE,
                    'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                    'price' => '39.95',
                    'inventory_quantity' => '5',
                    'shipping_units' => '1.00',
                    'min_satchel_rank' => '2',
                    'variants' => [
                        [
                            'name' => 'Backorder Blue',
                            'inventory_quantity' => '0',
                            'allow_backorder' => '1',
                            'backorder_shipping_estimate_type' => Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC,
                            'backorder_shipping_offset_days' => '7',
                            'sort_order' => '0',
                            'is_active' => '1',
                        ],
                    ],
                ])
                ->assertRedirect();

            $variant = $product->fresh()->variants()->firstOrFail();

            $this->assertTrue((bool) $variant->allow_backorder);
            $this->assertSame(Product::BACKORDER_SHIPPING_ESTIMATE_DYNAMIC, $variant->backorder_shipping_estimate_type);
            $this->assertSame(7, (int) $variant->backorder_shipping_offset_days);
            $this->assertNull($variant->backorder_shipping_estimate);
            $this->assertSame('2026-04-13', $variant->backorderShippingEstimateLabel('Y-m-d'));
        } finally {
            Carbon::setTestNow();
        }
    }

    public function test_physical_variants_use_base_pricing_and_packaging_even_if_override_values_are_posted(): void
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
            'box_only' => false,
        ]);

        $this->actingAs($admin)
            ->put(route('admin.shop.product.update', $product), [
                'title' => $product->title,
                'slug' => $product->slug,
                'sku' => 'RIGID-KIT',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '39.95',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
                'variants' => [
                    [
                        'name' => 'Rigid Large',
                        'sku' => 'RIGID-LARGE',
                        'price' => '49.95',
                        'compare_at_price' => '59.95',
                        'inventory_quantity' => '6',
                        'shipping_units' => '2.50',
                        'min_satchel_rank' => '4',
                        'weight_grams' => '900',
                        'box_only' => '1',
                        'sort_order' => '0',
                        'is_active' => '1',
                    ],
                ],
            ])
            ->assertRedirect();

        $variant = $product->fresh()->variants()->firstOrFail();

        $this->assertNull($variant->price);
        $this->assertNull($variant->compare_at_price);
        $this->assertNull($variant->shipping_units);
        $this->assertNull($variant->weight_grams);
        $this->assertSame('39.95', number_format((float) $variant->effectivePrice(), 2, '.', ''));
        $this->assertSame(2, $product->fresh()->minSatchelRankForVariant($variant->fresh()));
        $this->assertFalse($product->fresh()->boxOnlyForVariant($variant->fresh()));
    }

    public function test_admin_cannot_use_a_product_sku_that_matches_an_existing_variant(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $product = Product::factory()->create();
        ProductVariant::factory()->create([
            'product_id' => $product->id,
            'sku' => 'SHARED-SKU',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Conflict Product',
                'slug' => 'conflict-product',
                'sku' => 'SHARED-SKU',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '19.95',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '1',
            ])
            ->assertSessionHasErrors(['sku']);
    }

    public function test_admin_cannot_use_a_variant_sku_that_matches_an_existing_product(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        Product::factory()->create([
            'sku' => 'BASE-SKU',
        ]);

        $product = Product::factory()->create([
            'sku' => 'CURRENT-PRODUCT-SKU',
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
                'sku' => $product->sku,
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '39.95',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '2',
                'variants' => [
                    [
                        'name' => 'Conflicting Variant',
                        'sku' => 'BASE-SKU',
                        'sort_order' => '0',
                        'is_active' => '1',
                    ],
                ],
            ])
            ->assertSessionHasErrors(['variants.0.sku']);
    }

    public function test_admin_can_auto_fill_a_product_sku_when_it_is_blank(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.product.store'), [
                'title' => 'Missing SKU Product',
                'slug' => 'missing-sku-product',
                'sku' => '',
                'status' => Product::STATUS_ACTIVE,
                'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                'price' => '19.95',
                'shipping_units' => '1.00',
                'min_satchel_rank' => '1',
            ])
            ->assertRedirect(route('admin.shop.product.index'));

        $this->assertDatabaseHas('products', [
            'title' => 'Missing SKU Product',
            'slug' => 'missing-sku-product',
            'sku' => 'missing-sku-product',
        ]);
    }
}
