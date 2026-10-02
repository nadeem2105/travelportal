<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\CouponService;
use Illuminate\Http\Request;

/**
 * Coupon validation for the mobile checkout. This ONLY previews the discount —
 * the authoritative coupon application still happens server-side when the
 * booking is created/paid, so the client can never fake a discount.
 */
class CouponController extends Controller
{
    use ApiResponse;

    public function apply(Request $request, CouponService $coupons)
    {
        $data = $request->validate([
            'code' => 'required|string|max:40',
            'amount' => 'required|numeric|min:0',
            'product_type' => 'required|in:package,hotel,flight,cab',
        ]);

        $result = $coupons->validate(
            $data['code'],
            (float) $data['amount'],
            $data['product_type'],
            $request->user()?->id
        );

        if (! ($result['valid'] ?? false)) {
            return $this->fail($result['message'] ?? 'This coupon is not valid.', 422);
        }

        $discount = (float) $result['discount'];

        return $this->ok([
            'code' => $result['code'],
            'discount' => $discount,
            'description' => $result['description'] ?? null,
            'payable' => round(max(0, (float) $data['amount'] - $discount), 2),
        ], 'Coupon applied.');
    }
}
