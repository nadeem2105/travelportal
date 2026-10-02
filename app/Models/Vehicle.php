<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Vehicle extends Model
{
    protected $fillable = [
        'vendor_id', 'vehicle_type_id', 'name', 'image', 'passenger_capacity',
        'luggage_capacity', 'is_ac', 'base_price', 'per_km_rate', 'per_hour_rate',
        'extra_charges', 'cancellation_policy', 'is_featured', 'status',
    ];

    protected function casts(): array
    {
        return [
            'extra_charges' => 'array',
            'is_ac' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function vendor()
    {
        return $this->belongsTo(CabVendor::class, 'vendor_id');
    }

    public function type()
    {
        return $this->belongsTo(VehicleType::class, 'vehicle_type_id');
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }
}
