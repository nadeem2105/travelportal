<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\Wishlist;
use Illuminate\Http\Request;

class WishlistController extends Controller
{
    public function toggle(Request $request)
    {
        $validated = $request->validate([
            'type' => 'required|in:package,hotel,destination',
            'id' => 'required|integer',
        ]);

        $model = match ($validated['type']) {
            'package' => Package::class,
            'hotel' => Hotel::class,
            'destination' => Destination::class,
        };

        abort_unless($model::whereKey($validated['id'])->exists(), 404, 'Item not found.');

        $user = auth('web')->user();

        $existing = Wishlist::where('user_id', $user->id)
            ->where('wishlistable_type', $model)
            ->where('wishlistable_id', $validated['id'])
            ->first();

        if ($existing) {
            $existing->delete();

            return response()->json(['status' => 'removed']);
        }

        Wishlist::create([
            'user_id' => $user->id,
            'wishlistable_type' => $model,
            'wishlistable_id' => $validated['id'],
        ]);

        return response()->json(['status' => 'added']);
    }
}
