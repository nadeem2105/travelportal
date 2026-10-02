<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageItinerary extends Model
{
    protected $fillable = [
        'package_id', 'day_number', 'title', 'description', 'meals',
        'activities', 'overnight_stay', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['meals' => 'array', 'activities' => 'array'];
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
