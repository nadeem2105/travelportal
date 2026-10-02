<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageDeparture extends Model
{
    protected $fillable = [
        'package_id', 'departure_date', 'inventory', 'booked', 'price_override', 'status',
    ];

    protected function casts(): array
    {
        return ['departure_date' => 'date'];
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function seatsLeft(): int
    {
        return max(0, $this->inventory - $this->booked);
    }
}
