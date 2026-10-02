<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FlightBooking extends Model
{
    protected $fillable = [
        'booking_id', 'pnr', 'airline_code', 'flight_number', 'journey',
        'traveller_details', 'fare_details', 'seat_selection', 'baggage_selection',
        'fare_rules', 'ticket_data', 'ticket_number', 'trip_type',
    ];

    protected function casts(): array
    {
        return [
            'journey' => 'array',
            'traveller_details' => 'array',
            'fare_details' => 'array',
            'seat_selection' => 'array',
            'baggage_selection' => 'array',
            'fare_rules' => 'array',
            'ticket_data' => 'array',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }
}
