<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\Review;
use Illuminate\Http\Request;

/**
 * Product reviews. Listing is public and shows only approved reviews; posting
 * requires authentication and is marked verified when the user has a confirmed
 * booking for that product. New reviews start as "pending" for admin approval —
 * they never appear publicly until approved (existing moderation flow).
 */
class ReviewController extends Controller
{
    use ApiResponse;

    private const TYPES = ['package' => Package::class, 'hotel' => Hotel::class];

    /** GET /reviews?type=package&id=12 */
    public function index(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:package,hotel',
            'id' => 'required|integer',
            'per_page' => 'nullable|integer|min:1|max:50',
        ]);

        $class = self::TYPES[$data['type']];

        $reviews = Review::approved()
            ->where('reviewable_type', $class)
            ->where('reviewable_id', $data['id'])
            ->with('user:id,name,avatar')
            ->latest()
            ->paginate($data['per_page'] ?? 10)
            ->through(fn (Review $r) => [
                'id' => $r->id,
                'rating' => (int) $r->rating,
                'title' => $r->title,
                'content' => $r->content,
                'verified_booking' => (bool) $r->is_verified_booking,
                'author' => $r->user?->name ?? 'Traveller',
                'avatar' => $r->user?->avatar ? asset(img($r->user->avatar)) : null,
                'created_at' => $r->created_at?->toIso8601String(),
            ]);

        $summary = Review::approved()
            ->where('reviewable_type', $class)
            ->where('reviewable_id', $data['id'])
            ->selectRaw('COUNT(*) as total, AVG(rating) as avg')
            ->first();

        return $this->ok([
            'reviews' => $reviews,
            'summary' => [
                'count' => (int) ($summary->total ?? 0),
                'average' => round((float) ($summary->avg ?? 0), 1),
            ],
        ]);
    }

    /** POST /reviews (auth) */
    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:package,hotel',
            'id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:120',
            'content' => 'required|string|min:10|max:2000',
        ]);

        $class = self::TYPES[$data['type']];
        abort_unless($class::whereKey($data['id'])->exists(), 404, 'Item not found.');

        $user = $request->user();

        // One review per user per product.
        $already = Review::where('user_id', $user->id)
            ->where('reviewable_type', $class)
            ->where('reviewable_id', $data['id'])
            ->exists();

        if ($already) {
            return $this->fail('You have already reviewed this.', 409);
        }

        // Verified when the user has a confirmed/completed booking for it.
        $booking = Booking::where('user_id', $user->id)
            ->where('product_type', $data['type'])
            ->where('product_id', $data['id'])
            ->whereIn('status', ['confirmed', 'completed'])
            ->latest('id')
            ->first();

        $review = Review::create([
            'user_id' => $user->id,
            'reviewable_type' => $class,
            'reviewable_id' => $data['id'],
            'booking_id' => $booking?->id,
            'rating' => $data['rating'],
            'title' => $data['title'] ?? null,
            'content' => $data['content'],
            'is_verified_booking' => (bool) $booking,
            'status' => 'pending',
        ]);

        return $this->ok(
            ['id' => $review->id, 'status' => $review->status],
            'Thanks! Your review has been submitted for approval.',
            201
        );
    }
}
