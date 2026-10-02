<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Testimonial extends Model
{
    protected $fillable = [
        'customer_name', 'customer_photo', 'city', 'destination', 'rating',
        'content', 'is_featured', 'status',
    ];

    protected function casts(): array
    {
        return ['is_featured' => 'boolean'];
    }
}
