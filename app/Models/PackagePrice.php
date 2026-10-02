<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PackagePrice extends Model
{
    protected $fillable = [
        'package_id', 'season', 'label', 'price_per_person', 'price_per_couple',
        'price_per_child', 'starts_at', 'ends_at', 'is_default',
    ];

    protected function casts(): array
    {
        return ['starts_at' => 'date', 'ends_at' => 'date', 'is_default' => 'boolean'];
    }

    public function package()
    {
        return $this->belongsTo(Package::class);
    }
}
