<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Hotel extends Model
{
    protected $fillable = [
        'supplier_id', 'supplier_code', 'name', 'slug', 'destination_id', 'address',
        'city', 'star_rating', 'short_description', 'description', 'amenities',
        'policies', 'photos', 'cover_image', 'starting_price', 'latitude', 'longitude',
        'is_featured', 'status',
    ];

    protected function casts(): array
    {
        return [
            'amenities' => 'array',
            'policies' => 'array',
            'photos' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    public function rooms()
    {
        return $this->hasMany(HotelRoom::class);
    }

    public function destination()
    {
        return $this->belongsTo(Destination::class);
    }

    public function supplier()
    {
        return $this->belongsTo(Supplier::class);
    }

    public function reviews()
    {
        return $this->morphMany(Review::class, 'reviewable');
    }

    public function averageRating(): float
    {
        return round((float) ($this->reviews()->approved()->avg('rating') ?? 0), 1);
    }
}
