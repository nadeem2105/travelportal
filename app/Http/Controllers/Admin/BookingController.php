<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Hotel;
use App\Models\Package;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\ActivityLogger;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    public function index(Request $request)
    {
        $type = $request->route('type') ?? $request->query('type');
        $status = $request->query('status');
        $q = trim((string) $request->query('q'));

        $perPage = in_array((int) $request->query('per_page'), [15, 25, 50, 100]) ? (int) $request->query('per_page') : 15;

        $sortable = ['booking_reference', 'total_amount', 'created_at'];
        $sortCol = $request->query('sort_col');
        $sortDir = $request->query('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $bookings = Booking::with(['user', 'items', 'payments'])
            ->when($type, fn ($query) => $query->where('product_type', $type))
            ->when($status, fn ($query) => $query->where('status', $status))
            ->when($request->query('gateway'), fn ($query, $v) => $query->whereHas('payments', fn ($p) => $p->where('gateway', $v)))
            ->when($request->query('from'), fn ($query, $v) => $query->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($query, $v) => $query->whereDate('created_at', '<=', $v))
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('booking_reference', 'like', "%{$q}%")
                ->orWhere('supplier_booking_id', 'like', "%{$q}%")
                ->orWhere('contact->email', 'like', "%{$q}%")
                ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->when($request->query('sort'), function ($query, $sort) {
                match ($sort) {
                    'oldest' => $query->oldest(),
                    'amount_high' => $query->orderByDesc('total_amount'),
                    'amount_low' => $query->orderBy('total_amount'),
                    default => $query->latest(),
                };
            }, fn ($query) => $query->latest()))
            ->paginate($perPage)
            ->withQueryString();

        $gateways = \App\Models\PaymentGateway::orderBy('sort_order')->pluck('name', 'code')->all();
        $statusOptions = collect(Booking::STATUSES)->mapWithKeys(fn ($s) => [$s => label_case($s)])->all();

        return view('admin.bookings.index', compact('bookings', 'type', 'status', 'q', 'gateways', 'statusOptions'));
    }

    /**
     * Manual booking form — lets staff record an offline/phone booking directly
     * in the admin panel (walk-in customers, agent bookings taken over call, etc.).
     */
    public function create()
    {
        $packages = Package::orderBy('name')->get(['id', 'name']);
        $hotels = Hotel::orderBy('name')->get(['id', 'name', 'city']);
        $vehicles = Vehicle::orderBy('name')->get(['id', 'name']);

        return view('admin.bookings.create', compact('packages', 'hotels', 'vehicles'));
    }

    /**
     * Persist a manually-created booking through the authoritative BookingService
     * (same engine as the public flow) so pricing, references and details stay
     * consistent. Admin-entered amounts are trusted here (offline booking).
     */
    public function store(Request $request, BookingService $bookings)
    {
        $data = $request->validate([
            'product_type' => 'required|in:package,hotel,cab,flight',
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email|max:150',
            'phone' => 'nullable|string|max:20',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'status' => 'required|in:payment_pending,confirmed',
            'notes' => 'nullable|string|max:2000',
            // package
            'package_id' => 'nullable|exists:packages,id',
            'package_name' => 'nullable|string|max:200',
            'travel_date' => 'nullable|date',
            'adults' => 'nullable|integer|min:0|max:99',
            'children' => 'nullable|integer|min:0|max:99',
            'rooms' => 'nullable|integer|min:0|max:99',
            // hotel
            'hotel_id' => 'nullable|exists:hotels,id',
            'hotel_name' => 'nullable|string|max:200',
            'room_type' => 'nullable|string|max:100',
            'check_in' => 'nullable|date',
            'check_out' => 'nullable|date|after_or_equal:check_in',
            'meal_plan' => 'nullable|string|max:50',
            // cab
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'vehicle_name' => 'nullable|string|max:150',
            'pickup_location' => 'nullable|string|max:200',
            'drop_location' => 'nullable|string|max:200',
            'pickup_datetime' => 'nullable|date',
            'trip_type' => 'nullable|in:one_way,round_trip,local,airport',
            // flight
            'airline' => 'nullable|string|max:100',
            'flight_number' => 'nullable|string|max:20',
            'origin' => 'nullable|string|max:100',
            'destination' => 'nullable|string|max:100',
            'depart_at' => 'nullable|date',
        ]);

        $subtotal = (float) $data['subtotal'];
        $tax = (float) ($data['tax_amount'] ?? 0);
        $discount = (float) ($data['discount_amount'] ?? 0);
        $total = max(0, $subtotal + $tax - $discount);

        $pricing = [
            'supplier_cost' => $subtotal,
            'subtotal' => $subtotal,
            'markup_amount' => 0.0,
            'discount_amount' => $discount,
            'tax_amount' => $tax,
            'total' => $total,
            'commission_amount' => 0.0,
            'currency' => strtoupper($data['currency'] ?? 'INR'),
            'source' => 'admin_manual',
        ];

        $name = trim($data['first_name'] . ' ' . ($data['last_name'] ?? ''));
        $contact = [
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'] ?? '',
            'full_name' => $name,
            'email' => $data['email'] ?? null,
            'phone' => $data['phone'] ?? null,
        ];

        // Attach to an existing customer account when the email matches one.
        $user = ! empty($data['email']) ? User::where('email', $data['email'])->first() : null;

        $payload = [
            'product_type' => $data['product_type'],
            'user_id' => $user?->id,
            'pricing' => $pricing,
            'contact' => $contact,
            'items' => [[
                'item_type' => $data['product_type'],
                'name' => $this->manualItemName($data),
                'quantity' => 1,
                'unit_price' => $subtotal,
                'total_price' => $subtotal,
            ]],
            'notes' => $data['notes'] ?? 'Manual booking created in admin.',
        ];

        $payload = array_merge($payload, $this->manualDetailPayload($data));

        try {
            $booking = $bookings->create($payload);
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error('Manual booking creation failed: ' . $e->getMessage());

            return back()->withInput()->with('error', 'Could not create the booking: ' . $e->getMessage());
        }

        // Manual bookings shouldn't be swept away by the 30-min expiry job while
        // staff finalise them — give a generous hold and record the entered discount.
        $booking->forceFill([
            'discount_amount' => $discount,
            'expires_at' => now()->addDays(7),
        ])->save();

        // When staff records an already-settled offline booking, confirm it now
        // (mirrors the confirmation side-effects without a payment record).
        if ($data['status'] === 'confirmed') {
            $booking->forceFill(['status' => 'confirmed', 'booked_at' => now()])->save();
            $bookings->confirmBookingHotels($booking);

            try {
                if (! empty($data['email']) || ! empty($data['phone'])) {
                    app(\App\Services\NotificationService::class)->sendBookingConfirmation($booking, true);
                }
            } catch (\Throwable $e) {
                \Illuminate\Support\Facades\Log::warning('Manual booking confirmation notice failed: ' . $e->getMessage());
            }
        }

        ActivityLogger::log('create', 'bookings', "Manual {$data['product_type']} booking {$booking->booking_reference} created", [
            'booking' => $booking->booking_reference,
        ]);

        return redirect()->route('admin.bookings.show', $booking)
            ->with('success', "Booking {$booking->booking_reference} created.");
    }

    /** Human-readable line-item label for a manual booking. */
    private function manualItemName(array $data): string
    {
        return match ($data['product_type']) {
            'package' => $data['package_name'] ?: (Package::find($data['package_id'] ?? 0)?->name ?? 'Custom Package'),
            'hotel' => $data['hotel_name'] ?: (Hotel::find($data['hotel_id'] ?? 0)?->name ?? 'Hotel Stay'),
            'cab' => $data['vehicle_name'] ?: (Vehicle::find($data['vehicle_id'] ?? 0)?->name ?? 'Cab Transfer'),
            'flight' => trim(($data['airline'] ?? 'Flight') . ' ' . ($data['flight_number'] ?? '')),
            default => 'Booking',
        };
    }

    /** Build the product-specific payload branch BookingService::create() expects. */
    private function manualDetailPayload(array $data): array
    {
        return match ($data['product_type']) {
            'package' => [
                'product_id' => $data['package_id'] ?? null,
                'package' => [
                    'package_id' => $data['package_id'] ?? null,
                    'package_name' => $data['package_name'] ?: (Package::find($data['package_id'] ?? 0)?->name ?? 'Custom Package'),
                    'departure_date' => $data['travel_date'] ?? null,
                    'adults' => (int) ($data['adults'] ?? 2),
                    'children' => (int) ($data['children'] ?? 0),
                    'rooms' => (int) ($data['rooms'] ?? 1),
                ],
            ],
            'hotel' => [
                'product_id' => $data['hotel_id'] ?? null,
                'hotel' => [
                    'hotel_id' => $data['hotel_id'] ?? null,
                    'hotel_name' => $data['hotel_name'] ?: (Hotel::find($data['hotel_id'] ?? 0)?->name ?? 'Hotel'),
                    'room_type' => $data['room_type'] ?? null,
                    'check_in' => $data['check_in'] ?: now()->toDateString(),
                    'check_out' => $data['check_out'] ?: now()->addDay()->toDateString(),
                    'rooms' => (int) ($data['rooms'] ?? 1),
                    'meal_plan' => $data['meal_plan'] ?? 'room_only',
                ],
            ],
            'cab' => [
                'product_id' => $data['vehicle_id'] ?? null,
                'cab' => [
                    'vehicle_id' => $data['vehicle_id'] ?? null,
                    'vehicle_name' => $data['vehicle_name'] ?: (Vehicle::find($data['vehicle_id'] ?? 0)?->name ?? null),
                    'pickup_location' => $data['pickup_location'] ?: 'As per itinerary',
                    'drop_location' => $data['drop_location'] ?? null,
                    'pickup_datetime' => $data['pickup_datetime'] ?: now()->toDateTimeString(),
                    'trip_type' => $data['trip_type'] ?? 'one_way',
                ],
            ],
            'flight' => [
                'flight' => [
                    'airline' => ['code' => null, 'name' => $data['airline'] ?? null],
                    'flight_number' => $data['flight_number'] ?? null,
                    'trip_type' => 'one_way',
                    'segments' => [[
                        'origin' => $data['origin'] ?? null,
                        'destination' => $data['destination'] ?? null,
                        'departure_at' => $data['depart_at'] ?? null,
                    ]],
                ],
            ],
            default => [],
        };
    }

    public function show(Request $request, Booking $booking)
    {
        $booking->load(['user', 'items', 'travellers', 'payments', 'refunds', 'supplier', 'flight', 'hotelBooking', 'cab', 'packageBooking', 'bookingHotels', 'packageFlights']);

        return view('admin.bookings.show', compact('booking'));
    }

    public function invoice(Booking $booking)
    {
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

    public function itinerary(Booking $booking)
    {
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


    public function updateStatus(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'status' => 'required|in:' . implode(',', Booking::STATUSES),
        ]);

        $previous = $booking->status;
        $booking->update(['status' => $validated['status']]);

        if ($validated['status'] === 'confirmed' && $previous !== 'confirmed') {
            app(\App\Services\NotificationService::class)->sendBookingConfirmation($booking, true);
        } elseif ($validated['status'] === 'cancelled' && $previous !== 'cancelled') {
            app(\App\Services\NotificationService::class)->sendCancellation($booking, (float) $booking->total_amount);
        }

        ActivityLogger::log('update', 'bookings', "Booking {$booking->booking_reference} status: {$previous} → {$validated['status']}", [
            'booking' => $booking->booking_reference,
        ]);

        return back()->with('success', 'Booking status updated.');
    }

    public function resendConfirmationEmail(Booking $booking, \App\Services\NotificationService $notifications)
    {
        $recipientEmail = $booking->contact['email'] ?? $booking->user?->email;

        if (! $recipientEmail) {
            return back()->with('error', 'No email address found for this booking.');
        }

        $notifications->sendBookingConfirmation($booking, true);

        ActivityLogger::log('email', 'bookings', "Confirmation email resent to {$recipientEmail} for booking {$booking->booking_reference}");

        return back()->with('success', "Booking confirmation email successfully dispatched to {$recipientEmail}.");
    }

    public function sendWhatsAppInvoice(Booking $booking, \App\Services\NotificationService $notifications)
    {
        $recipientPhone = $booking->contact['phone'] ?? $booking->user?->phone;

        if (! $recipientPhone) {
            return back()->with('error', 'No phone number found for this booking to send WhatsApp invoice.');
        }

        $result = $notifications->sendBookingInvoiceWhatsApp($booking);

        if ($result['success'] ?? false) {
            ActivityLogger::log('whatsapp', 'bookings', "WhatsApp invoice sent to {$recipientPhone} for booking {$booking->booking_reference}");
            return back()->with('success', "WhatsApp tax invoice successfully sent to {$recipientPhone}.");
        }

        return back()->with('error', 'Failed to send WhatsApp invoice: ' . ($result['error'] ?? 'Check WhatsApp configuration'));
    }

    public function addNotes(Request $request, Booking $booking)
    {
        $validated = $request->validate(['admin_notes' => 'required|string|max:2000']);

        $booking->update(['admin_notes' => $validated['admin_notes']]);

        ActivityLogger::log('update', 'bookings', "Notes added to {$booking->booking_reference}");

        return back()->with('success', 'Notes saved.');
    }

    public function assignDriver(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'driver_name' => 'required|string|max:100',
            'driver_phone' => 'required|string|max:20',
            'vehicle_number' => 'required|string|max:30',
            'vendor_name' => 'nullable|string|max:100',
        ]);

        if ($booking->product_type !== 'cab' || ! $booking->cab) {
            return back()->with('error', 'Driver details can only be assigned to cab bookings.');
        }

        $booking->cab->update([
            'driver_details' => $validated,
        ]);

        if ($booking->status === 'confirmed') {
            app(\App\Services\NotificationService::class)->sendBookingConfirmation($booking, true);
        }

        ActivityLogger::log('update', 'bookings', "Driver {$validated['driver_name']} assigned to cab booking {$booking->booking_reference}");

        return back()->with('success', 'Driver details assigned and notification sent to traveller.');
    }

    public function cancel(Request $request, Booking $booking)
    {
        $validated = $request->validate([
            'penalty_percent' => 'required|numeric|min:0|max:100',
            'reason' => 'nullable|string|max:500',
        ]);

        $refund = $booking->refunds()->where('status', 'requested')->latest()->first();

        if (! $refund) {
            $refund = app(BookingService::class)->requestCancellation($booking, $validated['reason'] ?? 'Cancelled by admin', null);
        }

        app(BookingService::class)->approveCancellation($booking, $refund, (float) $validated['penalty_percent'], auth('admin')->id());

        ActivityLogger::log('cancel', 'bookings', "Booking {$booking->booking_reference} cancelled with " . $validated['penalty_percent'] . "% penalty");

        return back()->with('success', 'Booking cancelled and refund initiated.');
    }

    public function export(Request $request): StreamedResponse
    {
        $filename = 'bookings-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'bookings', 'Exported bookings CSV');

        return response()->streamDownload(function () use ($request) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Reference', 'Type', 'Customer', 'Email', 'Status', 'Amount', 'Booked At']);

            Booking::with('user')
                ->when($request->query('type'), fn ($q, $v) => $q->where('product_type', $v))
                ->when($request->query('status'), fn ($q, $v) => $q->where('status', $v))
                ->when($request->query('gateway'), fn ($q, $v) => $q->whereHas('payments', fn ($p) => $p->where('gateway', $v)))
                ->when($request->query('from'), fn ($q, $v) => $q->whereDate('created_at', '>=', $v))
                ->when($request->query('to'), fn ($q, $v) => $q->whereDate('created_at', '<=', $v))
                ->when($request->query('q'), fn ($q, $v) => $q->where(fn ($w) => $w
                    ->where('booking_reference', 'like', "%{$v}%")
                    ->orWhere('supplier_booking_id', 'like', "%{$v}%")
                    ->orWhere('contact->email', 'like', "%{$v}%")
                    ->orWhereHas('user', fn ($u) => $u->where('name', 'like', "%{$v}%")->orWhere('email', 'like', "%{$v}%"))))
                ->chunk(500, function ($bookings) use ($out) {
                    foreach ($bookings as $booking) {
                        fputcsv($out, [
                            $booking->booking_reference,
                            $booking->product_type,
                            $booking->user?->name ?? $booking->contact['first_name'] ?? 'Guest',
                            $booking->user?->email ?? $booking->contact['email'] ?? '',
                            $booking->status,
                            $booking->total_amount,
                            $booking->created_at->toDateTimeString(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }
}
