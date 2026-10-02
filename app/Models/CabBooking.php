<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CabBooking extends Model
{
    protected $fillable = [
        'booking_id', 'vehicle_id', 'vehicle_name', 'pickup_location', 'drop_location',
        'pickup_datetime', 'trip_type', 'distance_km', 'fare_breakdown', 'driver_details',
    ];

    protected function casts(): array
    {
        return [
            'pickup_datetime' => 'datetime',
            'fare_breakdown' => 'array',
            'driver_details' => 'array',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function vehicle()
    {
        return $this->belongsTo(Vehicle::class);
    }
}
