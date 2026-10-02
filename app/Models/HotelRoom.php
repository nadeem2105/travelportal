<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HotelRoom extends Model
{
    protected $fillable = [
        'hotel_id', 'room_type', 'description', 'max_adults', 'max_children',
        'base_price', 'extra_bed_price', 'child_price', 'meal_plan', 'amenities', 'total_rooms',
        'photo', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'base_price' => 'decimal:2',
            'extra_bed_price' => 'decimal:2',
            'child_price' => 'decimal:2',
        ];
    }

    public function hotel()
    {
        return $this->belongsTo(Hotel::class);
    }
}
