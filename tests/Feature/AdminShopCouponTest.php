<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\User;
use App\Models\UserGroup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminShopCouponTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_create_a_shop_coupon(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.coupon.create'))
            ->assertOk()
            ->assertSee('Create Voucher');

        $this->actingAs($admin)
            ->post(route('admin.shop.coupon.store'), [
                'code' => 'SAVE15',
                'description' => 'Launch discount',
                'status' => Coupon::STATUS_ACTIVE,
                'discount_type' => Coupon::DISCOUNT_TYPE_PERCENTAGE,
                'amount' => '15',
                'minimum_order_amount' => '30.00',
                'usage_limit' => '100',
                'usage_limit_per_user' => '1',
            ])
            ->assertRedirect(route('admin.shop.coupon.index'));

        $this->assertDatabaseHas('coupons', [
            'code' => 'SAVE15',
            'discount_type' => Coupon::DISCOUNT_TYPE_PERCENTAGE,
            'status' => Coupon::STATUS_ACTIVE,
        ]);
    }
}
