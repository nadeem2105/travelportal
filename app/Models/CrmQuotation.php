<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CrmQuotation extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id', 'contact_id', 'quotation_number', 'uuid', 'public_token', 'title', 'package_id',
        'hotel_id', 'hotels', 'vehicle_id', 'pickup_location', 'dropoff_location', 'itinerary',
        'items', 'payment_schedule', 'terms', 'cancellation_policy', 'notes',
        'inclusions', 'exclusions',
        'subtotal', 'discount_amount', 'tax_amount', 'total_amount', 'currency',
        'valid_until', 'travel_date', 'status', 'created_by', 'converted_booking_id',
        'sent_at', 'viewed_at', 'accepted_at', 'rejected_at',
        'adults', 'children', 'infants', 'rooms',
    ];

    protected $casts = [
        'items' => 'array',
        'payment_schedule' => 'array',
        'inclusions' => 'array',
        'exclusions' => 'array',
        'hotels' => 'array',
        'itinerary' => 'array',
        'subtotal' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'tax_amount' => 'decimal:2',
        'total_amount' => 'decimal:2',
        'valid_until' => 'date',
        'travel_date' => 'date',
        'sent_at' => 'datetime',
        'viewed_at' => 'datetime',
        'accepted_at' => 'datetime',
        'rejected_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $q) {
            if (empty($q->uuid)) {
                $q->uuid = (string) Str::uuid();
            }
            if (empty($q->public_token)) {
                $q->public_token = static::generateToken();
            }
            if (empty($q->quotation_number)) {
                $q->quotation_number = static::generateNumber();
            }
        });
    }

    public static function generateToken(): string
    {
        do {
            $token = Str::random(48);
        } while (static::where('public_token', $token)->exists());

        return $token;
    }

    public static function generateNumber(): string
    {
        $year = now()->format('Y');
        do {
            $seq = str_pad((string) random_int(1, 999999), 6, '0', STR_PAD_LEFT);
            $number = "QT-{$year}-{$seq}";
        } while (static::where('quotation_number', $number)->exists());

        return $number;
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function package(): BelongsTo
    {
        return $this->belongsTo(Package::class);
    }

    public function hotel(): BelongsTo
    {
        return $this->belongsTo(Hotel::class);
    }

    public function vehicle(): BelongsTo
    {
        return $this->belongsTo(Vehicle::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function isExpired(): bool
    {
        return $this->valid_until && $this->valid_until->isPast() && ! in_array($this->status, ['accepted', 'converted'], true);
    }

    /** Human-readable traveller summary, e.g. "2 Adults, 1 Child, 1 Room". */
    public function paxSummary(): ?string
    {
        $parts = [];
        if ($this->adults) {
            $parts[] = $this->adults . ' ' . \Illuminate\Support\Str::plural('Adult', $this->adults);
        }
        if ($this->children) {
            $parts[] = $this->children . ' ' . \Illuminate\Support\Str::plural('Child', $this->children);
        }
        if ($this->infants) {
            $parts[] = $this->infants . ' ' . \Illuminate\Support\Str::plural('Infant', $this->infants);
        }
        if ($this->rooms) {
            $parts[] = $this->rooms . ' ' . \Illuminate\Support\Str::plural('Room', $this->rooms);
        }

        return $parts ? implode(', ', $parts) : null;
    }

    /** Inclusions to show — the quote's own, else the linked package's. */
    public function resolvedInclusions(): array
    {
        return ! empty($this->inclusions) ? $this->inclusions : (array) ($this->package?->inclusions ?? []);
    }

    /** Exclusions to show — the quote's own, else the linked package's. */
    public function resolvedExclusions(): array
    {
        return ! empty($this->exclusions) ? $this->exclusions : (array) ($this->package?->exclusions ?? []);
    }

    /**
     * Hotel stays to display — one entry per location/night. Uses the new
     * multi-hotel `hotels` array when present, else falls back to the legacy
     * single `hotel` relation so older quotations still render.
     *
     * @return array<int,array{hotel_id:?int,hotel_name:?string,city:?string,location:?string,nights:?int}>
     */
    public function resolvedHotels(): array
    {
        if (! empty($this->hotels)) {
            return array_values($this->hotels);
        }

        if ($this->hotel) {
            return [[
                'hotel_id' => $this->hotel->id,
                'hotel_name' => $this->hotel->name,
                'city' => $this->hotel->city,
                'location' => null,
                'nights' => null,
            ]];
        }

        return [];
    }

    /**
     * Day-by-day itinerary with the overnight stay per day. Uses the quote's
     * own itinerary when set, else falls back to the linked package's itinerary
     * so package-based quotes still show a schedule.
     *
     * @return array<int,array{day:int,title:?string,description:?string,stay:?string}>
     */
    public function resolvedItinerary(): array
    {
        if (! empty($this->itinerary)) {
            return array_values($this->itinerary);
        }

        $pkgItinerary = $this->package?->itineraries;
        if ($pkgItinerary && $pkgItinerary->count()) {
            return $pkgItinerary
                ->map(fn ($it) => [
                    'day' => $it->day_number,
                    'title' => $it->title,
                    'description' => $it->description,
                    'stay' => $it->stay ?? $it->hotel ?? null,
                ])
                ->all();
        }

        return [];
    }

    public function publicUrl(): string
    {
        // Backfill a token for quotations created before public links existed.
        if (empty($this->public_token)) {
            $this->public_token = static::generateToken();
            $this->save();
        }

        return route('quote.show', $this->public_token);
    }
}
