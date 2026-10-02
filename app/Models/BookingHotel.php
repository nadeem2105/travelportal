<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingHotel extends Model
{
    protected $fillable = [
        'booking_id', 'package_id', 'package_hotel_option_id',
        'hotel_id', 'supplier_id', 'supplier_hotel_id', 'supplier_room_id', 'supplier_booking_id',
        'segment_label', 'hotel_name_snapshot', 'address_snapshot', 'star_rating_snapshot',
        'room_name_snapshot', 'meal_plan',
        'check_in', 'check_out', 'nights', 'rooms', 'adults', 'children', 'infants',
        'occupancy_snapshot',
        'is_included', 'base_price', 'upgrade_price', 'extra_guest_price', 'tax', 'total',
        'refundable', 'cancellation_policy_snapshot', 'status',
    ];

    protected function casts(): array
    {
        return [
            'check_in' => 'date',
            'check_out' => 'date',
            'occupancy_snapshot' => 'array',
            'is_included' => 'boolean',
            'refundable' => 'boolean',
            'base_price' => 'decimal:2',
            'upgrade_price' => 'decimal:2',
            'extra_guest_price' => 'decimal:2',
            'tax' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function option()
    {
        return $this->belongsTo(PackageHotelOption::class, 'package_hotel_option_id');
    }

    public function guestSummary(): string
    {
        $parts = [];
        if ($this->adults) {
            $parts[] = $this->adults . ' Adult' . ($this->adults > 1 ? 's' : '');
        }
        if ($this->children) {
            $parts[] = $this->children . ' Child' . ($this->children > 1 ? 'ren' : '');
        }
        if ($this->infants) {
            $parts[] = $this->infants . ' Infant' . ($this->infants > 1 ? 's' : '');
        }

        return implode(', ', $parts) ?: '—';
    }

    public function mealPlanLabel(): string
    {
        return ucwords(str_replace('_', ' ', (string) $this->meal_plan));
    }
}
