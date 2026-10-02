<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingItem extends Model
{
    protected $fillable = [
        'booking_id', 'item_type', 'item_id', 'name', 'quantity',
        'unit_price', 'total_price', 'details',
    ];

    protected function casts(): array
    {
        return ['details' => 'array'];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
