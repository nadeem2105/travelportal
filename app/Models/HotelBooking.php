<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelBooking extends Model
{
    protected $fillable = [
        'booking_id', 'hotel_id', 'hotel_name', 'room_type', 'check_in', 'check_out',
        'nights', 'rooms', 'guests', 'meal_plan', 'supplier_booking_id', 'voucher_data',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'guests' => 'array',
            'voucher_data' => 'array',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
