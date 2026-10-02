<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Booking extends Model
{
    use HasFactory;
    public const STATUSES = [
        'pending', 'payment_pending', 'confirmed', 'failed', 'cancelled',
        'refund_initiated', 'refunded', 'completed', 'payment_success_booking_failed',
    ];

    protected $fillable = [
        'booking_reference', 'user_id', 'agent_id', 'agent_commission', 'product_type', 'product_id', 'supplier_id',
        'supplier_booking_id', 'coupon_id', 'status', 'cancellation_status', 'refund_status',
        'supplier_cost', 'subtotal', 'markup_amount', 'discount_amount', 'tax_amount',
        'total_amount', 'commission_amount', 'currency', 'contact', 'price_breakdown',
        'notes', 'admin_notes', 'expires_at', 'booked_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'contact' => 'array',
            'price_breakdown' => 'array',
            'expires_at' => 'datetime',
            'booked_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function agent()
    {
        return $this->belongsTo(Agent::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function items()
    {
        return $this->hasMany(BookingItem::class);
    }

    public function travellers()
    {
        return $this->hasMany(BookingTraveller::class);
    }

    public function payments()
    {
        return $this->hasMany(Payment::class);
    }

    public function refunds()
    {
        return $this->hasMany(Refund::class);
    }

    public function flight()
    {
        return $this->hasOne(FlightBooking::class);
    }

    public function hotelBooking()
    {
        return $this->hasOne(HotelBooking::class);
    }

    public function cab()
    {
        return $this->hasOne(CabBooking::class);
    }

    public function packageBooking()
    {
        return $this->hasOne(PackageBooking::class);
    }

    /** Operational trip overlay (Trip Operations module). */
    public function trip()
    {
        return $this->hasOne(Trip::class);
    }

    /** Hotel snapshots for a package booking (one per booked segment). */
    public function bookingHotels()
    {
        return $this->hasMany(BookingHotel::class);
    }

    /** Flight snapshot(s) added to a package booking. */
    public function packageFlights()
    {
        return $this->hasMany(BookingPackageFlight::class);
    }

    /**
     * The name of the hotel actually booked for a given itinerary day number,
     * matched by the day's date against each snapshot's check-in/out range.
     * Returns null when there's no matching snapshot (caller falls back to the
     * package's overnight_stay text).
     */
    public function hotelStayForDay(int $dayNumber): ?string
    {
        $dep = $this->packageBooking?->departure_date;
        if (! $dep || $this->bookingHotels->isEmpty()) {
            return null;
        }

        $dayDate = \Illuminate\Support\Carbon::parse($dep)->addDays($dayNumber - 1);

        foreach ($this->bookingHotels as $bh) {
            if (! $bh->check_in) {
                continue;
            }
            $in = \Illuminate\Support\Carbon::parse($bh->check_in);
            $out = $bh->check_out ? \Illuminate\Support\Carbon::parse($bh->check_out) : null;

            if ($dayDate->isSameDay($in) || ($out && $dayDate->greaterThanOrEqualTo($in) && $dayDate->lessThan($out))) {
                return $bh->hotel_name_snapshot;
            }
        }

        return null;
    }

    public function successfulPayment()
    {
        return $this->hasOne(Payment::class)->whereIn('status', ['captured', 'authorized'])->latest();
    }

    public function productDetail()
    {
        return match ($this->product_type) {
            'flight' => $this->flight,
            'hotel' => $this->hotelBooking,
            'cab' => $this->cab,
            'package' => $this->packageBooking,
            default => null,
        };
    }

    /**
     * Guest bookings are tracked via session so anonymous customers can
     * complete checkout without an account.
     */
    public function sessionOwned(): bool
    {
        return in_array($this->booking_reference, (array) session("guest_bookings", []));
    }

    public function isCancellable(): bool
    {
        return in_array($this->status, ['confirmed', 'completed']);
    }

    public function scopeFilterStatus($query, $status)
    {
        return $query->when($status, fn ($q) => $q->where('status', $status));
    }
}
