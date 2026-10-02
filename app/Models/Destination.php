<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Destination extends Model
{
    protected $fillable = [
        'name', 'slug', 'region', 'cover_image', 'gallery', 'short_description',
        'description', 'best_time', 'altitude', 'famous_for', 'places_to_visit',
        'things_to_do', 'latitude', 'longitude', 'is_featured', 'sort_order', 'status',
    ];

    protected function casts(): array
    {
        return [
            'gallery' => 'array',
            'places_to_visit' => 'array',
            'things_to_do' => 'array',
            'is_featured' => 'boolean',
        ];
    }

    public function packages()
    {
        return $this->hasMany(Package::class);
    }

    public function hotels()
    {
        return $this->hasMany(Hotel::class);
    }

    public function guides()
    {
        return $this->hasMany(Guide::class);
    }

    public function seo()
    {
        return $this->morphOne(SeoMetadata::class, 'entity', 'entity_type', 'entity_id')
            ->where('entity_type', 'destination');
    }
}
