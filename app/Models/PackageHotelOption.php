<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageHotelOption extends Model
{
    protected $fillable = [
        'package_id', 'segment_id',
        'hotel_id', 'hotel_room_id',
        'supplier_id', 'supplier_hotel_code', 'supplier_room_code',
        'label', 'room_type', 'meal_plan', 'star_rating',
        'base_adults', 'max_adults', 'max_children', 'extra_bed_allowed',
        'is_default', 'price_basis', 'upgrade_price',
        'extra_adult_price', 'extra_child_price', 'extra_bed_price',
        'refundable', 'cancellation_policy',
        'available_from', 'available_to', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'extra_bed_allowed' => 'boolean',
            'is_default' => 'boolean',
            'refundable' => 'boolean',
            'upgrade_price' => 'decimal:2',
            'extra_adult_price' => 'decimal:2',
            'extra_child_price' => 'decimal:2',
            'extra_bed_price' => 'decimal:2',
            'available_from' => 'date',
            'available_to' => 'date',
        ];
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function segment()
    {
        return $this->belongsTo(PackageHotelSegment::class, 'segment_id');
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }

    public function room()
    {
        return $this->belongsTo(HotelRoom::class, 'hotel_room_id');
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    /** Available for the given travel date (honours the availability window). */
    public function scopeAvailableOn($query, $date)
    {
        $date = $date ? \Illuminate\Support\Carbon::parse($date)->toDateString() : now()->toDateString();

        return $query
            ->where(fn ($q) => $q->whereNull('available_from')->orWhere('available_from', '<=', $date))
            ->where(fn ($q) => $q->whereNull('available_to')->orWhere('available_to', '>=', $date));
    }

    /** Best-effort display name — the linked hotel, else the label. */
    public function displayName(): string
    {
        return $this->hotel?->name ?? $this->label ?? 'Hotel';
    }

    public function isManual(): bool
    {
        return $this->supplier_id === null;
    }
}
