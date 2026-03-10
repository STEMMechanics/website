<?php

namespace Tests\Feature;

use App\Models\Product;
use App\Models\User;
use App\Models\UserGroup;
use App\Services\StoreShippingService;
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

        $this->actingAs($admin)
            ->put(route('admin.shop.settings.update'), [
                'public_enabled' => '0',
                'max_satchel_weight_grams' => 4200,
                'boxed_shipping_label' => 'Special box shipping',
                'boxed_shipping_message' => 'This order needs a custom boxed shipment.',
                'boxed_shipping_amount' => '25.00',
                'satchels' => [
                    [
                        'code' => 'mini',
                        'label' => 'Mini',
                        'rank' => 1,
                        'capacity' => '0.50',
                        'price' => '7.50',
                        'active' => '1',
                    ],
                    [
                        'code' => 'large',
                        'label' => 'Large',
                        'rank' => 2,
                        'capacity' => '2.50',
                        'price' => '14.50',
                        'active' => '1',
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
        ]));

        $this->assertSame('1 x Mini Satchel', $quote['package_summary']);
        $this->assertSame(7.50, round((float) $quote['amount'], 2));
    }
}
