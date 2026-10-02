<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackageHotelSegment extends Model
{
    protected $fillable = [
        'package_id', 'label', 'city', 'day_from', 'day_to', 'nights', 'sort_order',
    ];

    public function package()
    {
        return $this->belongsTo(Package::class);
    }

    public function options()
    {
        return $this->hasMany(PackageHotelOption::class, 'segment_id')->orderBy('sort_order');
    }

    public function defaultOption()
    {
        return $this->hasOne(PackageHotelOption::class, 'segment_id')
            ->where('is_default', true)
            ->where('status', 'active');
    }
}
