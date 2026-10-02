<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

class Trip extends Model
{
    public const STATUS_UPCOMING = 'upcoming';
    public const STATUS_ARRIVING_TODAY = 'arriving_today';
    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    public const STATUSES = [
        self::STATUS_UPCOMING,
        self::STATUS_ARRIVING_TODAY,
        self::STATUS_IN_PROGRESS,
        self::STATUS_COMPLETED,
        self::STATUS_CANCELLED,
    ];

    protected $fillable = [
        'booking_id', 'trip_reference', 'status', 'arrival_date', 'departure_date',
        'total_days', 'lead_customer_name', 'lead_customer_phone', 'lead_customer_email',
        'destination_label', 'product_type', 'itinerary_version', 'itinerary_generated',
        'notes', 'started_at', 'completed_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'arrival_date' => 'date',
            'departure_date' => 'date',
            'itinerary_generated' => 'boolean',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function days()
    {
        return $this->hasMany(TripDay::class)->orderBy('day_number');
    }

    public function events()
    {
        return $this->hasMany(TripEvent::class)->orderBy('day_number')->orderBy('sort_order')->orderBy('time');
    }

    public function customerEvents()
    {
        return $this->events()->whereIn('visibility', ['customer', 'both']);
    }

    public function assignments()
    {
        return $this->hasMany(DriverAssignment::class);
    }

    public function activeAssignments()
    {
        return $this->hasMany(DriverAssignment::class)->whereIn('status', ['assigned', 'reassigned']);
    }

    public function comms()
    {
        return $this->hasMany(TripOperationComm::class);
    }

    /* ------------------------------------------------------------------ */

    public function scopeStatus($query, ?string $status)
    {
        return $query->when($status, fn ($q) => $q->where('status', $status));
    }

    public function scopeUpcoming($query)
    {
        return $query->whereIn('status', [self::STATUS_UPCOMING, self::STATUS_ARRIVING_TODAY]);
    }

    public function scopeArrivingOn($query, $date)
    {
        return $query->whereDate('arrival_date', $date);
    }

    public function isCancelled(): bool
    {
        return $this->status === self::STATUS_CANCELLED;
    }

    public function isActive(): bool
    {
        return in_array($this->status, [self::STATUS_ARRIVING_TODAY, self::STATUS_IN_PROGRESS], true);
    }

    /** Derive the operational status from booking + dates without persisting. */
    public function deriveStatus(): string
    {
        $bookingStatus = $this->booking?->status;
        if (in_array($bookingStatus, ['cancelled', 'refunded', 'refund_initiated'], true)) {
            return self::STATUS_CANCELLED;
        }

        $today = Carbon::today();
        $arrival = $this->arrival_date;
        $departure = $this->departure_date ?? $arrival;

        if (! $arrival) {
            return self::STATUS_UPCOMING;
        }
        if ($departure && $today->greaterThan($departure->copy()->endOfDay())) {
            return self::STATUS_COMPLETED;
        }
        if ($today->isSameDay($arrival)) {
            return self::STATUS_ARRIVING_TODAY;
        }
        if ($today->betweenIncluded($arrival, $departure ?? $arrival)) {
            return self::STATUS_IN_PROGRESS;
        }
        if ($today->lessThan($arrival)) {
            return self::STATUS_UPCOMING;
        }

        return self::STATUS_IN_PROGRESS;
    }

    public function daysUntilArrival(): ?int
    {
        return $this->arrival_date ? Carbon::today()->diffInDays($this->arrival_date, false) : null;
    }

    public function customerName(): string
    {
        return $this->lead_customer_name
            ?: ($this->booking?->user?->name)
            ?: ($this->booking?->contact['first_name'] ?? 'Guest');
    }

    public function customerPhone(): ?string
    {
        return $this->lead_customer_phone ?: ($this->booking?->contact['phone'] ?? null);
    }

    public function customerEmail(): ?string
    {
        return $this->lead_customer_email
            ?: ($this->booking?->user?->email)
            ?: ($this->booking?->contact['email'] ?? null);
    }
}
