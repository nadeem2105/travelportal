<?php

namespace App\Services;

use App\Models\Booking;
use App\Models\Coupon;
use Illuminate\Support\Carbon;

/**
 * All coupon validation happens server-side.
 */
class CouponService
{
    public function validate(string $code, float $amount, string $productType, ?int $userId = null): array
    {
        $coupon = Coupon::active()->where('code', strtoupper(trim($code)))->first();

        if (! $coupon) {
            return $this->fail('This coupon code is invalid or has expired.');
        }

        if ($coupon->product_types && ! in_array($productType, $coupon->product_types)) {
            return $this->fail('This coupon is not applicable to ' . ucfirst($productType) . ' bookings.');
        }

        if ($amount < (float) $coupon->min_booking_amount) {
            return $this->fail('Minimum booking amount for this coupon is ₹' . number_format((float) $coupon->min_booking_amount) . '.');
        }

        if ($coupon->usage_limit !== null && $coupon->used_count >= $coupon->usage_limit) {
            return $this->fail('This coupon has reached its usage limit.');
        }

        if ($userId) {
            $userUsage = Booking::where('user_id', $userId)
                ->where('coupon_id', $coupon->id)
                ->whereNotIn('status', ['failed', 'cancelled'])
                ->count();

            if ($userUsage >= $coupon->per_user_limit) {
                return $this->fail('You have already used this coupon the maximum number of times.');
            }

            if ($coupon->first_booking_only) {
                $hasBookings = Booking::where('user_id', $userId)
                    ->whereNotIn('status', ['failed', 'cancelled', 'payment_pending'])
                    ->exists();

                if ($hasBookings) {
                    return $this->fail('This coupon is valid on your first booking only.');
                }
            }

            if ($coupon->user_ids && ! in_array($userId, $coupon->user_ids)) {
                return $this->fail('This coupon is not valid for your account.');
            }
        }

        $discount = $coupon->discount_type === 'percentage'
            ? $amount * ((float) $coupon->discount_value / 100)
            : (float) $coupon->discount_value;

        if ($coupon->max_discount !== null) {
            $discount = min($discount, (float) $coupon->max_discount);
        }

        $discount = min(round($discount, 2), $amount);

        return [
            'valid' => true,
            'coupon_id' => $coupon->id,
            'code' => $coupon->code,
            'discount' => $discount,
            'description' => $coupon->description,
        ];
    }

    public function markUsed(Coupon $coupon): void
    {
        $coupon->increment('used_count');
    }

    protected function fail(string $message): array
    {
        return ['valid' => false, 'message' => $message];
    }
}
