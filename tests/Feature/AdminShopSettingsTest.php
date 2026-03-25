<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\SiteOption;
use App\Models\StoreShippingMethod;
use App\Models\StoreShippingMethodPackage;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\StoreShippingService;
use App\Services\StoreShippingMethodService;
use App\Support\ShopAvailability;
use App\Support\ShopShippingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_update_shop_shipping_settings(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.settings.edit'))
            ->assertOk()
            ->assertSee('Store Settings');

        $regular = StoreShippingMethod::query()->where('code', 'regular')->firstOrFail();
        $express = StoreShippingMethod::query()->where('code', 'express')->firstOrFail();
        $pickup = StoreShippingMethod::query()->where('code', 'pickup')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.shop.settings.update'), [
                'public_enabled' => '0',
                'max_satchel_weight_grams' => 4200,
                'boxed_shipping_label' => 'Special box shipping',
                'boxed_shipping_message' => 'This order needs a custom boxed shipment.',
                'boxed_shipping_amount' => '25.00',
                'shipping_methods' => [
                    [
                        'id' => $regular->id,
                        'code' => 'regular',
                        'name' => 'Regular shipping',
                        'description' => 'Standard delivery for in-stock items.',
                        'shipment_label' => 'Shipment',
                        'immediate_status_label' => 'Ships now',
                        'delayed_status_label' => 'Ships later',
                        'delivery_estimate_min_days' => '3',
                        'delivery_estimate_max_days' => '7',
                        'is_active' => '1',
                        'sort_order' => '0',
                        'packages' => [
                            [
                                'code' => 'mini',
                                'label' => 'Mini',
                                'sort_order' => 1,
                                'capacity' => '0.50',
                                'price' => '7.50',
                                'is_active' => '1',
                            ],
                            [
                                'code' => 'large',
                                'label' => 'Large',
                                'sort_order' => 2,
                                'capacity' => '2.50',
                                'price' => '14.50',
                                'is_active' => '1',
                            ],
                        ],
                    ],
                    [
                        'id' => $express->id,
                        'code' => 'express',
                        'name' => 'Express shipping',
                        'description' => 'Faster delivery once dispatched.',
                        'shipment_label' => 'Parcel',
                        'immediate_status_label' => 'Ready now',
                        'delayed_status_label' => 'Ready later',
                        'delivery_estimate_min_days' => '1',
                        'delivery_estimate_max_days' => '2',
                        'is_active' => '1',
                        'sort_order' => '1',
                        'packages' => [
                            [
                                'code' => 'mini',
                                'label' => 'Mini',
                                'sort_order' => 1,
                                'capacity' => '0.50',
                                'price' => '14.25',
                                'is_active' => '1',
                            ],
                            [
                                'code' => 'large',
                                'label' => 'Large',
                                'sort_order' => 2,
                                'capacity' => '2.50',
                                'price' => '21.00',
                                'is_active' => '1',
                            ],
                        ],
                    ],
                    [
                        'id' => $pickup->id,
                        'code' => 'pickup',
                        'name' => 'Pick up',
                        'description' => 'Free pickup. We will contact you when your order is available to collect.',
                        'shipment_label' => 'Collection',
                        'immediate_status_label' => 'Available now',
                        'delayed_status_label' => 'Available later',
                        'is_active' => '1',
                        'sort_order' => '2',
                        'packages' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('site_options', [
            'name' => ShopShippingSettings::MAX_WEIGHT_OPTION,
            'value' => '4200',
        ]);
        $this->assertDatabaseHas('site_options', [
            'name' => ShopAvailability::PUBLIC_ENABLED_OPTION,
            'value' => '0',
        ]);
        $this->assertDatabaseHas('site_options', [
            'name' => ShopShippingSettings::BOXED_LABEL_OPTION,
            'value' => 'Special box shipping',
        ]);
        $this->assertDatabaseHas('store_shipping_methods', [
            'id' => $express->id,
            'code' => 'express',
            'shipment_label' => 'Parcel',
            'immediate_status_label' => 'Ready now',
            'delayed_status_label' => 'Ready later',
            'calculator' => StoreShippingMethod::CALCULATOR_PACKAGES,
            'delivery_estimate_min_days' => 1,
            'delivery_estimate_max_days' => 2,
            'rate_multiplier' => 1.00,
            'rate_adjustment_amount' => 0.00,
        ]);
        $this->assertDatabaseHas('store_shipping_method_packages', [
            'store_shipping_method_id' => $express->id,
            'code' => 'mini',
            'label' => 'Mini',
            'price' => 14.25,
        ]);
        $this->assertDatabaseHas('store_shipping_methods', [
            'id' => $pickup->id,
            'calculator' => StoreShippingMethod::CALCULATOR_PICKUP,
            'is_pickup' => true,
            'flat_rate_amount' => '0.00',
        ]);
        $this->assertSame(0, StoreShippingMethodPackage::query()->where('store_shipping_method_id', $pickup->id)->count());

        $quote = app(StoreShippingService::class)->quote(collect([
            (object) [
                'product' => new Product([
                    'title' => 'Mini Kit',
                    'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                ]),
                'quantity' => 1,
                'display_title' => 'Mini Kit',
                'unit_shipping_units' => 0.5,
                'unit_min_satchel_rank' => 1,
                'unit_weight_grams' => 300,
                'box_only' => false,
            ],
        ]), 'Australia', 'express');

        $this->assertSame('1 x Mini', $quote['package_summary']);
        $this->assertSame('1-2 business days', $quote['delivery_estimate_label']);
        $this->assertSame(14.25, round((float) $quote['amount'], 2));

        $delayedQuote = app(StoreShippingService::class)->quote(collect([
            (object) [
                'product' => new Product([
                    'title' => 'Mini Kit',
                    'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                ]),
                'quantity' => 2,
                'display_title' => 'Mini Kit',
                'unit_shipping_units' => 0.5,
                'unit_min_satchel_rank' => 1,
                'unit_weight_grams' => 300,
                'box_only' => false,
                'available_now_quantity' => 1,
                'delayed_quantity' => 1,
                'delayed_fulfilment_type' => 'backorder',
            ],
        ]), 'Australia', 'express');

        $this->assertSame('Parcel 1: Ready now', $delayedQuote['shipments'][0]['title']);
        $this->assertSame('Parcel 2: Ready later', $delayedQuote['shipments'][1]['title']);
    }

    public function test_checkout_defaults_to_the_first_active_shipping_channel_by_sort_order(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $regular = StoreShippingMethod::query()->where('code', 'regular')->firstOrFail();
        $express = StoreShippingMethod::query()->where('code', 'express')->firstOrFail();
        $pickup = StoreShippingMethod::query()->where('code', 'pickup')->firstOrFail();

        $this->actingAs($admin)
            ->put(route('admin.shop.settings.update'), [
                'public_enabled' => '1',
                'max_satchel_weight_grams' => 5000,
                'boxed_shipping_label' => 'Manual quote',
                'boxed_shipping_message' => 'Manual shipping quote required.',
                'boxed_shipping_amount' => '',
                'shipping_methods' => [
                    [
                        'id' => $regular->id,
                        'code' => 'regular',
                        'name' => 'Regular shipping',
                        'description' => 'Standard delivery for in-stock items.',
                        'delivery_estimate_min_days' => '3',
                        'delivery_estimate_max_days' => '7',
                        'is_active' => '1',
                        'sort_order' => '1',
                        'packages' => [
                            [
                                'code' => 'small',
                                'label' => 'Small',
                                'sort_order' => 1,
                                'capacity' => '1.00',
                                'price' => '9.95',
                                'is_active' => '1',
                            ],
                        ],
                    ],
                    [
                        'id' => $express->id,
                        'code' => 'express',
                        'name' => 'Express shipping',
                        'description' => 'Faster delivery once dispatched.',
                        'delivery_estimate_min_days' => '1',
                        'delivery_estimate_max_days' => '3',
                        'is_active' => '1',
                        'sort_order' => '0',
                        'packages' => [
                            [
                                'code' => 'small',
                                'label' => 'Small',
                                'sort_order' => 1,
                                'capacity' => '1.00',
                                'price' => '13.43',
                                'is_active' => '1',
                            ],
                        ],
                    ],
                    [
                        'id' => $pickup->id,
                        'code' => 'pickup',
                        'name' => 'Pick up',
                        'description' => 'Free pickup. We will contact you when your order is available to collect.',
                        'delivery_estimate_min_days' => '',
                        'delivery_estimate_max_days' => '',
                        'is_active' => '0',
                        'sort_order' => '2',
                        'packages' => [],
                    ],
                ],
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('store_shipping_methods', [
            'id' => $regular->id,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $this->assertDatabaseHas('store_shipping_methods', [
            'id' => $express->id,
            'is_active' => true,
            'sort_order' => 0,
        ]);
        $this->assertDatabaseHas('store_shipping_methods', [
            'id' => $pickup->id,
            'is_active' => false,
            'sort_order' => 2,
        ]);

        $resolvedMethod = app(StoreShippingMethodService::class)->resolveForLines(collect([
            (object) [
                'product' => new Product([
                    'title' => 'Mini Kit',
                    'product_type' => Product::PRODUCT_TYPE_PHYSICAL,
                ]),
                'quantity' => 1,
                'display_title' => 'Mini Kit',
                'unit_shipping_units' => 0.5,
                'unit_min_satchel_rank' => 1,
                'unit_weight_grams' => 300,
                'box_only' => false,
            ],
        ]));

        $this->assertSame('express', $resolvedMethod?->code);
    }

    public function test_admin_settings_surface_legacy_package_options_when_channel_packages_are_missing(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        SiteOption::query()->updateOrCreate(
            ['name' => ShopShippingSettings::SATCHELS_OPTION],
            ['value' => json_encode([
                [
                    'code' => 'legacy_small',
                    'label' => 'Legacy Small',
                    'rank' => 1,
                    'capacity' => 1.00,
                    'price' => 9.95,
                    'active' => true,
                ],
            ], JSON_THROW_ON_ERROR)]
        );

        $regular = StoreShippingMethod::query()->where('code', 'regular')->firstOrFail();
        $express = StoreShippingMethod::query()->where('code', 'express')->firstOrFail();

        StoreShippingMethodPackage::query()
            ->whereIn('store_shipping_method_id', [$regular->id, $express->id])
            ->delete();

        $this->actingAs($admin)
            ->get(route('admin.shop.settings.edit'))
            ->assertOk()
            ->assertSee('Legacy Small')
            ->assertSee('Package Options');
    }
}
