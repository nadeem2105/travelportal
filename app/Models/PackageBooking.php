<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageBooking extends Model
{
    protected $fillable = [
        'booking_id', 'package_id', 'package_name', 'departure_date', 'adults',
        'children', 'room_count', 'price_breakdown', 'voucher_data',
    ];

    protected function casts(): array
    {
        return [
            'departure_date' => 'date',
            'price_breakdown' => 'array',
            'voucher_data' => 'array',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
