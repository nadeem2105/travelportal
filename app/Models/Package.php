<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Package extends Model
{
    protected $fillable = [
        'name', 'slug', 'destination_id', 'duration_days', 'duration_nights',
        'cover_image', 'gallery', 'short_description', 'description', 'highlights',
        'inclusions', 'exclusions', 'terms', 'cancellation_policy', 'base_price',
        'child_price', 'discount_percent', 'package_type', 'hotel_mode', 'flight_mode', 'is_featured',
        'max_travellers', 'status',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'highlights' => 'array',
            'inclusions' => 'array',
            'exclusions' => 'array',
            'terms' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    public function itineraries()
    {
        return $this->hasMany(PackageItinerary::class)->orderBy('day_number');
    }

    public function seasonalPrices()
    {
        return $this->hasMany(PackagePrice::class);
    }

    public function departures()
    {
        return $this->hasMany(PackageDeparture::class)->orderBy('departure_date');
    }

    public function hotels()
    {
        return $this->hasMany(PackageHotel::class)->orderBy('sort_order');
    }

    /** Segments (legs of stay) used for itinerary-based hotel selection. */
    public function hotelSegments()
    {
        return $this->hasMany(PackageHotelSegment::class)->orderBy('sort_order');
    }

    /** All selectable hotel options across every segment. */
    public function hotelOptions()
    {
        return $this->hasMany(PackageHotelOption::class)->orderBy('sort_order');
    }

    public function offersHotelSelection(): bool
    {
        return in_array($this->hotel_mode, ['optional', 'required'], true);
    }

    public function requiresHotelSelection(): bool
    {
        return $this->hotel_mode === 'required';
    }

    /** Selectable flight options (MakeMyTrip-style with/without flights). */
    public function flightOptions()
    {
        return $this->hasMany(PackageFlightOption::class)->orderBy('sort_order');
    }

    public function offersFlightSelection(): bool
    {
        return in_array($this->flight_mode, ['optional', 'required'], true);
    }

    public function requiresFlightSelection(): bool
    {
        return $this->flight_mode === 'required';
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function averageRating(): float
    {
        return round((float) ($this->reviews()->approved()->avg('rating') ?? 0), 1);
    }

    public function discountedPrice(): float
    {
        return round($this->base_price * (1 - $this->discount_percent / 100), 0);
    }

    /**
     * Effective per-person price for a given date, honouring seasonal pricing.
     */
    public function effectivePrice(?string $date = null): float
    {
        $date = $date ? \Illuminate\Support\Carbon::parse($date) : now();

        $seasonal = $this->seasonalPrices->first(function ($price) use ($date) {
            return $price->price_per_person !== null
                && (!$price->starts_at || $date->gte($price->starts_at))
                && (!$price->ends_at || $date->lte($price->ends_at));
        });

        $base = $seasonal?->price_per_person ?? $this->base_price;

        return round($base * (1 - $this->discount_percent / 100), 0);
    }
}
