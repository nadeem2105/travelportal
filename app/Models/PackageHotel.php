<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageHotel extends Model
{
    protected $fillable = [
        'package_id', 'city', 'hotel_id', 'hotel_name', 'room_type', 'nights', 'category', 'sort_order',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
