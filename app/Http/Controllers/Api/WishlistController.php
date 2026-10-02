<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\Wishlist;
use Illuminate\Http\Request;

/**
 * Authenticated wishlist for the mobile app. Reuses the same polymorphic
 * `wishlists` table and type→model mapping as the website so a user's saved
 * items are shared across web and app.
 */
class WishlistController extends Controller
{
    use ApiResponse;

    private const TYPES = [
        'package' => Package::class,
        'hotel' => Hotel::class,
        'destination' => Destination::class,
    ];

    public function index(Request $request)
    {
        $items = Wishlist::where('user_id', $request->user()->id)
            ->with('wishlistable')
            ->latest()
            ->get()
            ->map(function (Wishlist $w) {
                $m = $w->wishlistable;
                if (! $m) {
                    return null;
                }
                $type = array_search($w->wishlistable_type, self::TYPES, true) ?: 'item';

                return [
                    'type' => $type,
                    'id' => $m->id,
                    'name' => $m->name,
                    'slug' => $m->slug ?? null,
                    'image' => asset(img($m->cover_image ?? null)),
                    'price' => isset($m->base_price) ? (float) $m->base_price
                        : (isset($m->starting_price) ? (float) $m->starting_price : null),
                ];
            })
            ->filter()
            ->values();

        return $this->ok(['items' => $items]);
    }

    /** Toggle an item on/off the wishlist. */
    public function toggle(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:package,hotel,destination',
            'id' => 'required|integer',
        ]);

        $class = self::TYPES[$data['type']];
        abort_unless($class::whereKey($data['id'])->exists(), 404, 'Item not found.');

        $user = $request->user();

        $existing = Wishlist::where('user_id', $user->id)
            ->where('wishlistable_type', $class)
            ->where('wishlistable_id', $data['id'])
            ->first();

        if ($existing) {
            $existing->delete();

            return $this->ok(['status' => 'removed', 'in_wishlist' => false], 'Removed from wishlist.');
        }

        Wishlist::create([
            'user_id' => $user->id,
            'wishlistable_type' => $class,
            'wishlistable_id' => $data['id'],
        ]);

        return $this->ok(['status' => 'added', 'in_wishlist' => true], 'Added to wishlist.');
    }
}
