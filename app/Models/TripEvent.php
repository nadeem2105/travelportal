<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripEvent extends Model
{
    public const TYPES = [
        'arrival' => 'Arrival',
        'transfer' => 'Transfer',
        'checkin' => 'Hotel Check-in',
        'checkout' => 'Hotel Check-out',
        'activity' => 'Activity / Sightseeing',
        'meal' => 'Meal',
        'departure' => 'Departure',
        'custom' => 'Custom',
    ];

    protected $fillable = [
        'trip_id', 'trip_day_id', 'day_number', 'event_type', 'time', 'title',
        'location', 'lat', 'lng', 'maps_url', 'description', 'visibility', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'lat' => 'decimal:7',
            'lng' => 'decimal:7',
        ];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function day()
    {
        return $this->belongsTo(TripDay::class, 'trip_day_id');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->event_type] ?? label_case((string) $this->event_type);
    }

    public function isCustomerVisible(): bool
    {
        return in_array($this->visibility, ['customer', 'both'], true);
    }

    /** Best-effort maps link: explicit url, else coords, else a search on the location text. */
    public function mapsLink(): ?string
    {
        if ($this->maps_url) {
            return $this->maps_url;
        }
        if ($this->lat !== null && $this->lng !== null) {
            return "https://www.google.com/maps/search/?api=1&query={$this->lat},{$this->lng}";
        }
        if ($this->location) {
            return 'https://www.google.com/maps/search/?api=1&query=' . urlencode($this->location);
        }

        return null;
    }

    public function timeLabel(): ?string
    {
        return $this->time ? \Illuminate\Support\Carbon::parse($this->time)->format('h:i A') : null;
    }
}
