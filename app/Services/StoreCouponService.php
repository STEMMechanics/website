<?php

namespace App\Services;

use App\Models\Coupon;
use App\Models\User;

class StoreCouponService
{
    public function evaluate(
        ?string $couponCode,
        float $subtotal,
        float $shipping,
        ?User $user = null,
        ?string $billingEmail = null
    ): array {
        $normalizedCode = Coupon::normalizeCode($couponCode);
        if ($normalizedCode === '') {
            return [
                'coupon' => null,
                'coupon_code' => null,
                'discount_type' => null,
                'discount_amount' => 0.0,
                'error' => null,
            ];
        }

        $coupon = Coupon::query()
            ->whereRaw('UPPER(code) = ?', [$normalizedCode])
            ->first();

        if (! $coupon instanceof Coupon) {
            return [
                'coupon' => null,
                'coupon_code' => null,
                'discount_type' => null,
                'discount_amount' => 0.0,
                'error' => 'That voucher was not found.',
            ];
        }

        $error = $coupon->ineligibilityReason($subtotal, $user, $billingEmail);
        if ($error !== null) {
            return [
                'coupon' => $coupon,
                'coupon_code' => null,
                'discount_type' => (string) $coupon->discount_type,
                'discount_amount' => 0.0,
                'error' => $error,
            ];
        }

        $discountAmount = $coupon->discountAmountFor($subtotal, $shipping);

        return [
            'coupon' => $coupon,
            'coupon_code' => $coupon->code,
            'discount_type' => (string) $coupon->discount_type,
            'discount_amount' => round(max(0, $discountAmount), 2),
            'error' => null,
        ];
    }
}
