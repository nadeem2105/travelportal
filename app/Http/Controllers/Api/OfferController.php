<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Offer;
use Illuminate\Http\Request;

/**
 * Public promotional offers for the mobile home screen. Read-only; mirrors the
 * website's active-offer logic (window + status + sort order).
 */
class OfferController extends Controller
{
    use ApiResponse;

    public function index()
    {
        $offers = Offer::active()->with('coupon:id,code,discount_type,discount_value')->get()
            ->map(fn (Offer $o) => $this->transform($o));

        return $this->ok(['offers' => $offers]);
    }

    public function show(Offer $offer)
    {
        abort_unless($offer->status === 'active', 404);

        return $this->ok(['offer' => $this->transform($offer->load('coupon:id,code,discount_type,discount_value'))]);
    }

    private function transform(Offer $o): array
    {
        return [
            'id' => $o->id,
            'title' => $o->title,
            'slug' => $o->slug,
            'description' => $o->description,
            'image' => asset(img($o->image, 'images/destinations/srinagar.svg')),
            'discount_text' => $o->discount_text,
            'badge' => $o->badge,
            'button_text' => $o->button_text ?? 'Book Now',
            'link_url' => $o->link_url,
            'coupon' => $o->coupon ? [
                'code' => $o->coupon->code,
                'discount_type' => $o->coupon->discount_type,
                'discount_value' => (float) $o->coupon->discount_value,
            ] : null,
            'ends_at' => $o->ends_at?->toIso8601String(),
        ];
    }
}
