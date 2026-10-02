<?php

namespace App\Services\WhatsApp\Assistant;

use App\Models\Booking;
use App\Models\WhatsAppConversation;
use App\Services\BookingService;
use App\Services\PdfDocumentService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * Ownership-scoped, backend-authorized self-service tools the AI assistant may
 * call. EVERY method that touches private data resolves the owned booking set
 * through CustomerResolver first — the AI can never widen its own access. Tools
 * return plain arrays (JSON-encoded back to the model). Document tools return a
 * ['_document' => ['bytes'=>..,'filename'=>..,'caption'=>..]] marker that the
 * orchestrator turns into a real WhatsApp document; the model only sees a short
 * confirmation string, never the bytes.
 *
 * Rules honored here:
 *  - Never fabricate: everything comes from the DB / existing services.
 *  - Distinguish STORED booking info from LIVE provider info (we only have
 *    stored data; live flight/cab status is explicitly labelled unavailable).
 *  - Cancellation/modification are REQUESTS that need explicit confirmation and
 *    backend/admin action — nothing is auto-cancelled or auto-modified.
 */
class CustomerTools
{
    public function __construct(
        private CustomerResolver $resolver,
        private BookingService $bookings,
        private PdfDocumentService $pdf,
    ) {
    }

    /** The active conversation is injected per-invocation by the orchestrator. */
    private WhatsAppConversation $conversation;

    public function forConversation(WhatsAppConversation $conversation): self
    {
        $this->conversation = $conversation;

        return $this;
    }

    // === Profile ========================================================

    public function getMyProfile(array $args = []): array
    {
        if (! $this->verified()) {
            return $this->needsVerification();
        }

        $name = $this->resolver->displayName($this->conversation);
        $user = $this->conversation->verifiedUser;
        $contact = $this->conversation->verifiedContact;

        return [
            'name' => $name ?: 'Guest',
            'email' => $user?->email ?? $contact?->email,
            'phone_on_file' => $user?->phone ?? $contact?->phone,
            'preferred_language' => $contact?->preferred_language,
            'registered_account' => (bool) $user,
        ];
    }

    // === Booking lists ==================================================

    public function getMyBookings(array $args = []): array
    {
        return $this->listBookings(null, 'all');
    }

    public function getMyUpcomingBookings(array $args = []): array
    {
        return $this->listBookings('upcoming', 'upcoming');
    }

    public function getMyPastBookings(array $args = []): array
    {
        return $this->listBookings('past', 'past');
    }

    private function listBookings(?string $filter, string $label): array
    {
        if (! $this->verified()) {
            return $this->needsVerification();
        }

        $q = $this->ownedQuery();
        if (! $q) {
            return ['bookings' => [], 'message' => 'No bookings found for your number.'];
        }

        $today = Carbon::today();
        if ($filter === 'upcoming') {
            $q->whereIn('status', ['confirmed', 'payment_pending'])
                ->where(fn ($w) => $w->whereNull('booked_at')->orWhereDate('booked_at', '>=', $today->copy()->subYear()));
        } elseif ($filter === 'past') {
            $q->whereIn('status', ['completed', 'cancelled', 'refunded']);
        }

        $bookings = $q->latest('id')->limit(15)->get();

        // Sort upcoming by nearest travel date computed from detail rows.
        $rows = $bookings->map(fn (Booking $b) => $this->bookingSummary($b))->all();

        if ($filter === 'upcoming') {
            usort($rows, fn ($a, $b) => strcmp((string) ($a['travel_date'] ?? '9999'), (string) ($b['travel_date'] ?? '9999')));
        }

        return [
            'scope' => $label,
            'count' => count($rows),
            'bookings' => $rows,
        ];
    }

    // === Booking detail =================================================

    public function getBookingDetails(array $args = []): array
    {
        $booking = $this->resolveArgBooking($args);
        if (is_array($booking)) {
            return $booking; // error/needs-verification payload
        }

        $booking->loadMissing(['items', 'travellers', 'packageBooking', 'hotelBooking', 'cab', 'flight', 'bookingHotels']);

        $paid = $this->paidAmount($booking);

        return [
            'booking_reference' => $booking->booking_reference,
            'status' => $booking->status,
            'product_type' => $booking->product_type,
            'destination' => $this->destination($booking),
            'travel_date' => $this->travelDate($booking),
            'travellers' => $booking->travellers->map(fn ($t) => $t->full_name)->filter()->values()->all()
                ?: $this->paxSummary($booking),
            'hotel' => $this->hotelBrief($booking),
            'flight' => $this->flightBrief($booking),
            'cab' => $this->cabBrief($booking),
            'total_amount' => (float) $booking->total_amount,
            'paid_amount' => $paid,
            'pending_amount' => max((float) $booking->total_amount - $paid, 0),
            'currency' => $booking->currency ?? 'INR',
            'note' => 'All figures are from your stored booking record.',
        ];
    }

    public function getBookingHotel(array $args = []): array
    {
        $booking = $this->resolveArgBooking($args);
        if (is_array($booking)) {
            return $booking;
        }
        $booking->loadMissing(['hotelBooking', 'bookingHotels']);

        $hotel = $this->hotelBrief($booking);
        if (! $hotel) {
            return ['message' => 'This booking has no hotel component on record.'];
        }

        return ['hotel' => $hotel, 'note' => 'Stored booking information.'];
    }

    public function getBookingFlight(array $args = []): array
    {
        $booking = $this->resolveArgBooking($args);
        if (is_array($booking)) {
            return $booking;
        }
        $booking->loadMissing('flight');

        $flight = $this->flightBrief($booking);
        if (! $flight) {
            return ['message' => 'This booking has no flight component on record.'];
        }

        // We only hold STORED booking info — no live provider status wired.
        $flight['live_status'] = 'unavailable';
        $flight['note'] = 'This is the flight information saved in your booking. Live flight status is not available here — please check with the airline for real-time status.';

        return ['flight' => $flight];
    }

    public function getBookingCab(array $args = []): array
    {
        $booking = $this->resolveArgBooking($args);
        if (is_array($booking)) {
            return $booking;
        }
        $booking->loadMissing('cab');

        $cab = $this->cabBrief($booking);
        if (! $cab) {
            return ['message' => 'This booking has no cab component on record.'];
        }

        return ['cab' => $cab, 'note' => 'Stored booking information.'];
    }

    public function getBookingPaymentStatus(array $args = []): array
    {
        $booking = $this->resolveArgBooking($args);
        if (is_array($booking)) {
            return $booking;
        }
        $booking->loadMissing('payments');

        $paid = $this->paidAmount($booking);
        $captured = $booking->payments
            ->whereIn('status', ['captured', 'authorized'])
            ->sortByDesc('paid_at')
            ->first();

        return [
            'booking_reference' => $booking->booking_reference,
            'total_amount' => (float) $booking->total_amount,
            'paid_amount' => $paid,
            'pending_amount' => max((float) $booking->total_amount - $paid, 0),
            'currency' => $booking->currency ?? 'INR',
            'payment_status' => $paid >= (float) $booking->total_amount && $paid > 0 ? 'paid' : ($paid > 0 ? 'partial' : 'pending'),
            'last_payment' => $captured ? [
                'amount' => (float) $captured->amount,
                'reference' => $captured->gateway_payment_id,
                'date' => optional($captured->paid_at)->format('d M Y'),
            ] : null,
        ];
    }

    // === Documents ======================================================

    public function getBookingInvoice(array $args = []): array
    {
        return $this->document($args, 'invoice');
    }

    public function getBookingReceipt(array $args = []): array
    {
        // Receipt == invoice document in this system (payment info is on it).
        return $this->document($args, 'invoice', 'receipt');
    }

    public function getBookingItinerary(array $args = []): array
    {
        return $this->document($args, 'itinerary');
    }

    public function getBookingDocuments(array $args = []): array
    {
        $booking = $this->resolveArgBooking($args);
        if (is_array($booking)) {
            return $booking;
        }

        try {
            $docs = $this->pdf->documentsFor($booking);
        } catch (\Throwable $e) {
            Log::warning('Assistant getBookingDocuments failed: ' . $e->getMessage());

            return ['message' => 'Could not generate the documents right now. Please try again shortly.'];
        }

        $attachments = [];
        foreach ($docs as $filename => $bytes) {
            $attachments[] = ['bytes' => $bytes, 'filename' => $filename, 'caption' => null];
        }

        return [
            '_documents' => $attachments,
            'sent_count' => count($attachments),
            'filenames' => array_keys($docs),
            'message' => count($attachments) . ' document(s) for booking ' . $booking->booking_reference . ' are being sent.',
        ];
    }

    private function document(array $args, string $method, string $label = null): array
    {
        $booking = $this->resolveArgBooking($args);
        if (is_array($booking)) {
            return $booking;
        }

        $label = $label ?: $method;

        try {
            $bytes = $this->pdf->{$method}($booking);
        } catch (\Throwable $e) {
            Log::warning("Assistant document({$method}) failed: " . $e->getMessage());

            return ['message' => "Could not generate the {$label} right now. Please try again shortly."];
        }

        $filename = ucfirst($label) . '-' . $booking->booking_reference . '.pdf';

        return [
            '_document' => ['bytes' => $bytes, 'filename' => $filename, 'caption' => ucfirst($label) . ' for ' . $booking->booking_reference],
            'message' => "The {$label} for {$booking->booking_reference} is being sent as a PDF.",
        ];
    }

    // === Cancellation policy + requests =================================

    public function getCancellationPolicy(array $args = []): array
    {
        $booking = $this->resolveArgBooking($args);
        if (is_array($booking)) {
            return $booking;
        }
        $booking->loadMissing(['packageBooking.package', 'cab.vehicle', 'bookingHotels']);

        $policy = null;
        if ($booking->product_type === 'package') {
            $policy = $booking->packageBooking?->package?->cancellation_policy;
        } elseif ($booking->product_type === 'cab') {
            $policy = $booking->cab?->vehicle?->cancellation_policy;
        } elseif ($booking->product_type === 'hotel') {
            $policy = $booking->bookingHotels->first()?->cancellation_policy_snapshot;
        }

        return [
            'booking_reference' => $booking->booking_reference,
            'cancellable' => $booking->isCancellable(),
            'cancellation_policy' => $policy ?: 'Standard cancellation policy applies. Our team will confirm the exact refund/charge on request.',
            'note' => 'The exact refund amount is confirmed by our team based on this policy — I can raise a cancellation request for you.',
        ];
    }

    public function requestBookingCancellation(array $args = []): array
    {
        $booking = $this->resolveArgBooking($args);
        if (is_array($booking)) {
            return $booking;
        }

        if (! (bool) ($args['confirm'] ?? false)) {
            return [
                'requires_confirmation' => true,
                'booking_reference' => $booking->booking_reference,
                'message' => 'Please confirm you want to request cancellation for ' . $booking->booking_reference . '. The refund/charge will be calculated by our team per the cancellation policy. Reply to confirm.',
            ];
        }

        if (! $booking->isCancellable()) {
            return ['message' => 'Booking ' . $booking->booking_reference . ' is not in a cancellable state (current status: ' . $booking->status . ').'];
        }

        try {
            $refund = $this->bookings->requestCancellation(
                $booking,
                'Customer cancellation request via WhatsApp assistant',
                $this->conversation->verified_user_id
            );
        } catch (\Throwable $e) {
            Log::warning('Assistant cancellation request failed: ' . $e->getMessage());

            return ['message' => 'Could not raise the cancellation request automatically. Our team has been notified and will assist you.'];
        }

        return [
            'status' => 'requested',
            'booking_reference' => $booking->booking_reference,
            'message' => 'Your cancellation request for ' . $booking->booking_reference . ' has been raised. Our team will review it against the cancellation policy and confirm the refund. This is a request — the booking is not cancelled until our team confirms.',
        ];
    }

    public function requestBookingModification(array $args = []): array
    {
        $booking = $this->resolveArgBooking($args);
        if (is_array($booking)) {
            return $booking;
        }

        $change = trim((string) ($args['requested_change'] ?? ''));

        // No automated modification engine exists — this creates a human-handled
        // request. Never claim the change is done.
        return [
            'status' => 'request_recorded',
            'booking_reference' => $booking->booking_reference,
            'requested_change' => $change !== '' ? $change : null,
            'handoff' => true,
            'message' => 'A modification request for ' . $booking->booking_reference . ($change !== '' ? ' (' . $change . ')' : '') . ' has been noted. Our team will check availability and any additional cost, then confirm with you before making any change.',
        ];
    }

    // === Payment link ===================================================

    public function getPaymentLink(array $args = []): array
    {
        $booking = $this->resolveArgBooking($args);
        if (is_array($booking)) {
            return $booking;
        }

        $paid = $this->paidAmount($booking);
        $pending = max((float) $booking->total_amount - $paid, 0);

        if (! in_array($booking->status, ['payment_pending', 'pending'], true) && $pending <= 0) {
            return ['message' => 'Booking ' . $booking->booking_reference . ' has no pending payment.'];
        }

        // Reuse the existing secure checkout route (owner/guest-guarded).
        $link = route('checkout.show', $booking->booking_reference);

        return [
            'booking_reference' => $booking->booking_reference,
            'pending_amount' => $pending,
            'currency' => $booking->currency ?? 'INR',
            'payment_link' => $link,
            'message' => 'Pending amount is ' . money($pending) . '. Secure payment link: ' . $link,
        ];
    }

    // === Human handoff ==================================================

    public function requestHumanAgent(array $args = []): array
    {
        // Pause the bot so a human takes over; existing inbox surfaces this.
        $this->conversation->update(['bot_paused' => true]);

        return [
            'status' => 'handoff',
            'handoff' => true,
            'message' => 'Connecting you with our team. A travel consultant will reply here shortly.',
        ];
    }

    // === New sales (public catalog, no auth needed) =====================

    public function searchPackages(array $args = []): array
    {
        $term = trim((string) ($args['query'] ?? ''));
        $destination = trim((string) ($args['destination'] ?? ''));

        $q = \App\Models\Package::query()->where('status', 'active');
        if ($term !== '') {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('short_description', 'like', "%{$term}%"));
        }
        if ($destination !== '') {
            $q->whereHas('destination', fn ($d) => $d->where('name', 'like', "%{$destination}%"));
        }

        $packages = $q->with('destination')->limit(6)->get();

        if ($packages->isEmpty()) {
            return ['packages' => [], 'message' => 'No matching packages found. Our team can help design a custom trip.'];
        }

        return [
            'packages' => $packages->map(fn ($p) => [
                'name' => $p->name,
                'destination' => $p->destination?->name,
                'duration' => trim(($p->duration_days ? $p->duration_days . 'D' : '') . ($p->duration_nights ? '/' . $p->duration_nights . 'N' : '')),
                'from_price' => $p->base_price ? (float) $p->base_price : null,
                'url' => $this->packageUrl($p),
            ])->all(),
            'note' => 'Prices are indicative starting prices from the catalog.',
        ];
    }

    // === helpers ========================================================

    private function verified(): bool
    {
        return $this->resolver->isVerified($this->conversation);
    }

    private function needsVerification(): array
    {
        return [
            'requires_verification' => true,
            'message' => 'I need to verify your identity first. Please share a booking reference (e.g. the one on your confirmation) linked to this WhatsApp number, and I can pull up your details.',
        ];
    }

    /** Base query over the conversation's owned bookings, or null if none. */
    private function ownedQuery()
    {
        $ids = $this->resolver->ownedBookingIds($this->conversation);
        if (empty($ids)) {
            return null;
        }

        return Booking::whereIn('id', $ids);
    }

    /**
     * Resolve the booking an arg refers to. If a reference is given it must be
     * owned. If omitted and exactly one booking is owned, use it. Otherwise ask
     * the customer to choose. Returns a Booking OR an array payload to return.
     */
    private function resolveArgBooking(array $args): Booking|array
    {
        if (! $this->verified()) {
            return $this->needsVerification();
        }

        $ref = trim((string) ($args['booking_reference'] ?? ''));
        if ($ref !== '') {
            $booking = $this->resolver->ownedBooking($this->conversation, $ref);
            if (! $booking) {
                return ['message' => 'I could not find booking ' . $ref . ' under your number. Please double-check the reference.'];
            }

            return $booking;
        }

        $ids = $this->resolver->ownedBookingIds($this->conversation);
        if (empty($ids)) {
            return ['message' => 'No bookings are linked to your number yet.'];
        }
        if (count($ids) === 1) {
            return Booking::find($ids[0]);
        }

        // Multiple — ask which one.
        $options = Booking::whereIn('id', $ids)->latest('id')->limit(10)->get()
            ->map(fn (Booking $b) => $this->bookingSummary($b))->all();

        return [
            'requires_selection' => true,
            'bookings' => $options,
            'message' => 'You have multiple bookings. Which one? Please tell me the booking reference.',
        ];
    }

    private function bookingSummary(Booking $b): array
    {
        return [
            'booking_reference' => $b->booking_reference,
            'product_type' => $b->product_type,
            'title' => $this->title($b),
            'destination' => $this->destination($b),
            'travel_date' => $this->travelDate($b),
            'status' => $b->status,
            'total_amount' => (float) $b->total_amount,
        ];
    }

    private function title(Booking $b): ?string
    {
        return match ($b->product_type) {
            'package' => $b->packageBooking?->package_name,
            'hotel' => $b->hotelBooking?->hotel_name,
            'cab' => $b->cab?->vehicle_name,
            'flight' => trim(($b->flight?->airline_code ?? '') . ' ' . ($b->flight?->flight_number ?? '')),
            default => ucfirst($b->product_type) . ' booking',
        };
    }

    private function destination(Booking $b): ?string
    {
        if ($b->product_type === 'package') {
            return $b->packageBooking?->package?->destination?->name;
        }

        return null;
    }

    private function travelDate(Booking $b): ?string
    {
        return match ($b->product_type) {
            'package' => optional($b->packageBooking?->departure_date)->format('Y-m-d'),
            'hotel' => optional($b->hotelBooking?->check_in)->format('Y-m-d'),
            'cab' => optional($b->cab?->pickup_datetime)->format('Y-m-d'),
            'flight' => data_get($b->flight?->journey, 'segments.0.from.date'),
            default => null,
        };
    }

    private function paxSummary(Booking $b): ?array
    {
        $pb = $b->packageBooking;
        if (! $pb) {
            return null;
        }
        $parts = [];
        if ($pb->adults) {
            $parts[] = $pb->adults . ' Adult(s)';
        }
        if ($pb->children) {
            $parts[] = $pb->children . ' Child(ren)';
        }

        return $parts ?: null;
    }

    private function hotelBrief(Booking $b): ?array
    {
        if ($b->product_type === 'hotel' && $b->hotelBooking) {
            $h = $b->hotelBooking;

            return [
                'hotel_name' => $h->hotel_name,
                'check_in' => optional($h->check_in)->format('d M Y'),
                'check_out' => optional($h->check_out)->format('d M Y'),
                'nights' => $h->nights,
                'rooms' => $h->rooms,
                'room_type' => $h->room_type,
                'meal_plan' => $h->meal_plan,
                'confirmation_number' => $h->supplier_booking_id,
            ];
        }

        // Package hotels (snapshots).
        if ($b->relationLoaded('bookingHotels') && $b->bookingHotels->isNotEmpty()) {
            return $b->bookingHotels->map(fn ($h) => [
                'hotel_name' => $h->hotel_name_snapshot,
                'segment' => $h->segment_label,
                'address' => $h->address_snapshot,
                'star_rating' => $h->star_rating_snapshot,
                'check_in' => optional($h->check_in)->format('d M Y'),
                'check_out' => optional($h->check_out)->format('d M Y'),
                'nights' => $h->nights,
                'rooms' => $h->rooms,
                'room_type' => $h->room_name_snapshot,
                'meal_plan' => $h->mealPlanLabel(),
                'confirmation_number' => $h->supplier_booking_id,
                'status' => $h->status,
            ])->all();
        }

        return null;
    }

    private function flightBrief(Booking $b): ?array
    {
        $f = $b->flight;
        if (! $f) {
            return null;
        }
        $seg = data_get($f->journey, 'segments.0', []);

        return [
            'airline_code' => $f->airline_code,
            'flight_number' => $f->flight_number,
            'from' => data_get($seg, 'from.city') ?? data_get($seg, 'from.code'),
            'to' => data_get($seg, 'to.city') ?? data_get($seg, 'to.code'),
            'departure' => data_get($seg, 'from.date'),
            'arrival' => data_get($seg, 'to.date'),
            'pnr' => $f->pnr,
            'ticket_number' => $f->ticket_number,
        ];
    }

    private function cabBrief(Booking $b): ?array
    {
        $c = $b->cab;
        if (! $c) {
            return null;
        }

        $driver = $c->driver_details;
        $hasDriver = is_array($driver) && ! empty(array_filter($driver));

        return [
            'vehicle' => $c->vehicle_name,
            'pickup_location' => $c->pickup_location,
            'drop_location' => $c->drop_location,
            'pickup_datetime' => optional($c->pickup_datetime)->format('d M Y, h:i A'),
            'booking_reference' => $b->booking_reference,
            'driver' => $hasDriver ? [
                'name' => data_get($driver, 'name'),
                'contact' => data_get($driver, 'phone') ?? data_get($driver, 'contact'),
            ] : null,
            'driver_note' => $hasDriver ? null : 'Your cab is confirmed, but driver details have not been assigned yet.',
        ];
    }

    private function paidAmount(Booking $b): float
    {
        $b->loadMissing('payments');

        return (float) $b->payments
            ->whereIn('status', ['captured', 'authorized'])
            ->sum('amount');
    }

    private function packageUrl($package): ?string
    {
        try {
            return route('packages.show', $package->slug ?? $package->id);
        } catch (\Throwable) {
            return null;
        }
    }
}
