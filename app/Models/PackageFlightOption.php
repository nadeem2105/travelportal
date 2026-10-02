<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageFlightOption extends Model
{
    protected $fillable = [
        'package_id', 'label', 'origin_city', 'origin_airport_code', 'destination_airport_code',
        'airline', 'airline_code', 'trip_type', 'cabin_class', 'baggage',
        'supplier_id', 'supplier_fare_code',
        'price_basis', 'price', 'is_default',
        'refundable', 'cancellation_policy',
        'available_from', 'available_to', 'status', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'refundable' => 'boolean',
            'price' => 'decimal:2',
            'available_from' => 'date',
            'available_to' => 'date',
        ];
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function displayName(): string
    {
        return $this->label
            ?: trim(($this->origin_city ? 'Ex-' . $this->origin_city : 'Flight') . ' ' . ucfirst(str_replace('_', ' ', $this->trip_type)));
    }

    public function isManual(): bool
    {
        return $this->supplier_id === null;
    }
}
