<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Review;
use App\Models\SavedTraveller;
use App\Models\Wishlist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class AccountController extends Controller
{
    public function dashboard()
    {
        $user = auth('web')->user();

        $bookings = $user->bookings()->with('items')->limit(5)->get();

        $stats = [
            'total' => $user->bookings()->count(),
            'upcoming' => $user->bookings()->whereIn('status', ['confirmed', 'payment_pending'])->count(),
            'completed' => $user->bookings()->where('status', 'completed')->count(),
            'unread' => $user->unreadNotificationsCount(),
        ];

        return view('account.dashboard', [
            'seo' => ['title' => 'My Account', 'description' => ''],
            'user' => $user,
            'bookings' => $bookings,
            'stats' => $stats,
        ]);
    }

    public function trips(Request $request)
    {
        $status = $request->query('status');

        $bookings = auth('web')->user()->bookings()
            ->with('items')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->paginate(10)
            ->withQueryString();

        return view('account.trips', [
            'seo' => ['title' => 'My Trips', 'description' => ''],
            'bookings' => $bookings,
            'status' => $status,
        ]);
    }

    public function showBooking(Request $request, Booking $booking)
    {
        $this->authorizeBooking($booking);

        return view('account.booking_show', [
            'seo' => ['title' => 'Booking ' . $booking->booking_reference, 'description' => ''],
            'booking' => $booking->load([
                'items', 'travellers', 'payments', 'refunds',
                'flight', 'hotelBooking.hotel', 'cab.vehicle', 'bookingHotels', 'packageFlights',
                'packageBooking.package.itineraries', 'packageBooking.package.destination',
            ]),
        ]);
    }

    public function invoice(Request $request, Booking $booking)
    {
        $this->authorizeBooking($booking);

        $booking->load([
            'items', 'travellers', 'payments', 'user',
            'flight',
            'hotelBooking.hotel',
            'cab.vehicle',
            'packageBooking.package.itineraries',
            'packageBooking.package.hotels',
            'packageBooking.package.destination',
        ]);

        return view('account.invoice', compact('booking'));
    }

    /**
     * Download a booking document as PDF (invoice, e-ticket, voucher, itinerary).
     */
    public function invoicePdf(Request $request, Booking $booking)
    {
        $this->authorizeBooking($booking);

        $service = app(\App\Services\PdfDocumentService::class);

        $type = $request->query('type', 'invoice');

        $document = match ($type) {
            'ticket' => ['Flight E-Ticket ' . $booking->booking_reference . '.pdf', $service->flightTicket($booking)],
            'voucher' => match ($booking->product_type) {
                'hotel' => ['Hotel Voucher ' . $booking->booking_reference . '.pdf', $service->hotelVoucher($booking)],
                'package' => ['Hotel Voucher ' . $booking->booking_reference . '.pdf', $service->packageHotelVoucher($booking)],
                default => ['Cab Voucher ' . $booking->booking_reference . '.pdf', $service->cabVoucher($booking)],
            },
            'itinerary' => ['Tour Itinerary & Voucher ' . $booking->booking_reference . '.pdf', $service->itinerary($booking)],
            default => ['Tax Invoice ' . $booking->booking_reference . '.pdf', $service->invoice($booking)],
        };

        return response()->streamDownload(
            fn () => print($document[1]),
            $document[0],
            ['Content-Type' => 'application/pdf']
        );
    }

    public function itinerary(Request $request, Booking $booking)
    {
        $this->authorizeBooking($booking);

        $booking->load([
            'items', 'travellers', 'payments', 'user',
            'flight',
            'hotelBooking.hotel',
            'cab.vehicle',
            'bookingHotels',
            'packageFlights',
            'packageBooking.package.itineraries',
            'packageBooking.package.hotels',
            'packageBooking.package.destination',
        ]);

        return view('account.itinerary', compact('booking'));
    }


    public function cancelBooking(Request $request, Booking $booking)
    {
        $this->authorizeBooking($booking);

        if (! $booking->isCancellable()) {
            return back()->with('error', 'This booking cannot be cancelled at this stage.');
        }

        $validated = $request->validate(['reason' => 'required|string|min:10|max:500']);

        app(\App\Services\BookingService::class)->requestCancellation($booking, $validated['reason'], auth('web')->id());

        return back()->with('success', 'Cancellation request submitted. Our team will process your refund shortly.');
    }

    public function profile()
    {
        return view('account.profile', [
            'seo' => ['title' => 'My Profile', 'description' => ''],
            'user' => auth('web')->user(),
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth('web')->user();

        $validated = $request->validate([
            'name' => 'required|string|max:80',
            'email' => ['required', 'email', Rule::unique('users')->ignore($user->id)],
            'phone' => 'nullable|string|max:15',
            'dob' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'city' => 'nullable|string|max:60',
            'address' => 'nullable|string|max:255',
        ]);

        $user->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function travellers()
    {
        return view('account.travellers', [
            'seo' => ['title' => 'Saved Travellers', 'description' => ''],
            'travellers' => auth('web')->user()->savedTravellers,
        ]);
    }

    public function storeTraveller(Request $request)
    {
        $validated = $request->validate([
            'title' => 'nullable|in:Mr,Mrs,Ms,Master,Miss',
            'first_name' => 'required|string|max:60',
            'last_name' => 'nullable|string|max:60',
            'dob' => 'nullable|date|before:today',
            'gender' => 'nullable|in:male,female,other',
            'id_type' => 'nullable|in:passport,aadhaar,driving_license,voter_id',
            'id_number' => 'nullable|string|max:60',
        ]);

        auth('web')->user()->savedTravellers()->create($validated);

        return back()->with('success', 'Traveller saved.');
    }

    public function destroyTraveller(SavedTraveller $traveller)
    {
        abort_unless($traveller->user_id === auth('web')->id(), 403);
        $traveller->delete();

        return back()->with('success', 'Traveller removed.');
    }

    public function wishlist()
    {
        $user = auth('web')->user();

        $items = $user->wishlists()->with('wishlistable')->get()->groupBy('wishlistable_type');

        return view('account.wishlist', [
            'seo' => ['title' => 'My Wishlist', 'description' => ''],
            'packages' => $items->get(\App\Models\Package::class, collect())->pluck('wishlistable')->filter(),
            'hotels' => $items->get(\App\Models\Hotel::class, collect())->pluck('wishlistable')->filter(),
            'destinations' => $items->get(\App\Models\Destination::class, collect())->pluck('wishlistable')->filter(),
        ]);
    }

    public function storeReview(Request $request)
    {
        $validated = $request->validate([
            'reviewable_type' => ['required', Rule::in(['package', 'hotel', 'vehicle'])],
            'reviewable_id' => 'required|integer',
            'rating' => 'required|integer|min:1|max:5',
            'title' => 'nullable|string|max:120',
            'content' => 'required|string|min:15|max:2000',
        ]);

        $model = match ($validated['reviewable_type']) {
            'package' => \App\Models\Package::class,
            'hotel' => \App\Models\Hotel::class,
            'vehicle' => \App\Models\Vehicle::class,
        };

        $target = $model::find($validated['reviewable_id']);
        abort_if(! $target, 404);

        // Verified badge when the user has a completed booking with this product
        $hasCompletedBooking = auth('web')->user()->bookings()
            ->where('status', 'completed')
            ->where(fn ($q) => $q
                ->where(fn ($w) => $w->where('product_type', $validated['reviewable_type'] === 'vehicle' ? 'cab' : $validated['reviewable_type'])->where('product_id', $target->id))
                ->orWhereHas($validated['reviewable_type'] === 'vehicle' ? 'cab' : $validated['reviewable_type'] . 'Booking', fn ($w) => $w->where(match ($validated['reviewable_type']) {
                    'package' => 'package_id',
                    'hotel' => 'hotel_id',
                    'vehicle' => 'vehicle_id',
                }, $target->id)))
            ->exists();

        Review::create([
            'user_id' => auth('web')->id(),
            'reviewable_type' => $model,
            'reviewable_id' => $target->id,
            'booking_id' => auth('web')->user()->bookings()->latest()->value('id'),
            'rating' => $validated['rating'],
            'title' => $validated['title'] ?? null,
            'content' => $validated['content'],
            'is_verified_booking' => $hasCompletedBooking,
            'status' => 'pending',
        ]);

        return back()->with('success', 'Thank you! Your review is awaiting moderation.');
    }

    public function notifications()
    {
        $user = auth('web')->user();

        return view('account.notifications', [
            'seo' => ['title' => 'Notifications', 'description' => ''],
            'notifications' => $user->notifications()->paginate(15),
        ]);
    }

    public function markNotificationsRead()
    {
        auth('web')->user()->notifications()->whereNull('read_at')->update(['read_at' => now()]);

        return back();
    }

    public function security()
    {
        return view('account.security', ['seo' => ['title' => 'Security', 'description' => '']]);
    }

    public function updatePassword(Request $request)
    {
        $validated = $request->validate([
            'current_password' => 'required|current_password:web',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        auth('web')->user()->update(['password' => Hash::make($validated['password'])]);

        return back()->with('success', 'Password changed successfully.');
    }

    protected function authorizeBooking(Booking $booking): void
    {
        // Admins may inspect everything.
        if (auth('admin')->check()) {
            return;
        }

        // user_id === null must never match a null auth id — check ownership explicitly.
        if ($booking->user_id !== null) {
            abort_unless($booking->user_id === auth('web')->id(), 403);
        } else {
            abort_unless($booking->sessionOwned(), 403);
        }
    }
}
