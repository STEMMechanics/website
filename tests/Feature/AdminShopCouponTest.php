<?php

namespace Tests\Feature;

use App\Models\Coupon;
use App\Models\Location;
use App\Models\Media;
use App\Models\User;
use App\Models\UserGroup;
use App\Models\Workshop;
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
            ->assertSee('Create Voucher')
            ->assertSeeText('Case insensitive.')
            ->assertSeeText('Fixed amount discount')
            ->assertSeeText('Products')
            ->assertSeeText('Workshops')
            ->assertSeeText('All products are allowed.')
            ->assertSeeText('All workshops are allowed.')
            ->assertSeeText('Total Usage Limit')
            ->assertSeeText('Per User Limit')
            ->assertSeeText('Optional')
            ->assertDontSee('Use dollars for fixed discounts, percent for percentage discounts, or leave at 0 for free shipping.');

        $this->actingAs($admin)
            ->post(route('admin.shop.coupon.store'), [
                'code' => 'SAVE15',
                'description' => 'Launch discount',
                'status' => Coupon::STATUS_ACTIVE,
                'discount_type' => Coupon::DISCOUNT_TYPE_PERCENTAGE,
                'amount' => '15',
                'applies_to_products' => true,
                'applies_to_workshops' => false,
                'minimum_order_amount' => '30.00',
                'usage_limit' => '100',
                'usage_limit_per_user' => '1',
            ])
            ->assertRedirect(route('admin.shop.coupon.index'));

        $this->assertDatabaseHas('coupons', [
            'code' => 'SAVE15',
            'discount_type' => Coupon::DISCOUNT_TYPE_PERCENTAGE,
            'status' => Coupon::STATUS_ACTIVE,
            'applies_to_products' => true,
            'applies_to_workshops' => false,
        ]);
    }

    public function test_admin_coupon_amount_is_normalized_for_the_selected_discount_type(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.coupon.store'), [
                'code' => 'SAVE16',
                'description' => 'Rounded percentage',
                'status' => Coupon::STATUS_ACTIVE,
                'discount_type' => Coupon::DISCOUNT_TYPE_PERCENTAGE,
                'amount' => '15.75',
                'applies_to_products' => true,
                'applies_to_workshops' => true,
            ])
            ->assertRedirect(route('admin.shop.coupon.index'));

        $percentageCoupon = Coupon::query()->where('code', 'SAVE16')->sole();
        $this->assertSame('16.00', (string) $percentageCoupon->amount);

        $this->actingAs($admin)
            ->post(route('admin.shop.coupon.store'), [
                'code' => 'FREESHIP',
                'description' => 'Free shipping voucher',
                'status' => Coupon::STATUS_ACTIVE,
                'discount_type' => Coupon::DISCOUNT_TYPE_FREE_SHIPPING,
                'amount' => '99.99',
                'applies_to_products' => true,
                'applies_to_workshops' => true,
            ])
            ->assertRedirect(route('admin.shop.coupon.index'));

        $freeShippingCoupon = Coupon::query()->where('code', 'FREESHIP')->sole();
        $this->assertSame('0.00', (string) $freeShippingCoupon->amount);
    }

    public function test_admin_coupon_edit_warns_when_the_voucher_has_already_expired(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $this->travelTo(now()->startOfMinute());

        $coupon = Coupon::factory()->create([
            'status' => Coupon::STATUS_ACTIVE,
            'ends_at' => now()->subHour(),
        ]);

        $this->actingAs($admin)
            ->get(route('admin.shop.coupon.edit', $coupon))
            ->assertOk()
            ->assertSee('This voucher will be inactive immediately because the end time is in the past.');
    }

    public function test_admin_can_save_workshop_only_coupon_scope(): void
    {
        $admin = User::factory()->create();
        UserGroup::query()->create([
            'user_id' => (string) $admin->id,
            'slug' => 'admin',
        ]);

        $location = Location::factory()->create();
        $workshopAuthor = User::factory()->create();
        $heroMedia = Media::factory()->create([
            'user_id' => (string) $workshopAuthor->id,
        ]);
        $workshop = Workshop::factory()->create([
            'location_id' => (string) $location->id,
            'user_id' => (string) $workshopAuthor->id,
            'hero_media_name' => (string) $heroMedia->name,
        ]);

        $this->actingAs($admin)
            ->post(route('admin.shop.coupon.store'), [
                'code' => 'WORKSHOPONLY',
                'description' => 'Workshop scope only',
                'status' => Coupon::STATUS_ACTIVE,
                'discount_type' => Coupon::DISCOUNT_TYPE_FIXED_AMOUNT,
                'amount' => '10.00',
                'applies_to_products' => false,
                'applies_to_workshops' => true,
                'workshop_ids' => [(string) $workshop->id],
            ])
            ->assertRedirect(route('admin.shop.coupon.index'));

        $coupon = Coupon::query()->where('code', 'WORKSHOPONLY')->with(['restrictedProducts', 'restrictedWorkshops'])->sole();
        $this->assertFalse($coupon->appliesToProducts());
        $this->assertTrue($coupon->appliesToWorkshops());
        $this->assertSame([(string) $workshop->id], $coupon->restrictedWorkshopIds());
        $this->assertSame([], $coupon->restrictedProductIds());
    }
}
