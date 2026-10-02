<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingPackageFlight extends Model
{
    protected $fillable = [
        'booking_id', 'package_id', 'package_flight_option_id',
        'supplier_id', 'supplier_fare_id', 'supplier_booking_id',
        'label_snapshot', 'airline_snapshot', 'origin_city', 'origin_airport_code', 'destination_airport_code',
        'trip_type', 'cabin_class', 'baggage_snapshot',
        'travellers', 'price_basis', 'price_per_person', 'price', 'tax',
        'refundable', 'cancellation_policy_snapshot', 'status',
    ];

    protected function casts(): array
    {
        return [
            'refundable' => 'boolean',
            'price_per_person' => 'decimal:2',
            'price' => 'decimal:2',
            'tax' => 'decimal:2',
        ];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function option()
    {
        return $this->belongsTo(PackageFlightOption::class, 'package_flight_option_id');
    }

    public function routeLabel(): string
    {
        $from = $this->origin_airport_code ?: $this->origin_city;
        $to = $this->destination_airport_code;

        if ($from && $to) {
            return $this->trip_type === 'round_trip' ? "{$from} ⇄ {$to}" : "{$from} → {$to}";
        }

        return $this->label_snapshot ?: 'Flight';
    }

    public function cabinLabel(): string
    {
        return ucwords(str_replace('_', ' ', (string) $this->cabin_class));
    }
}
