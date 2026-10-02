<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Review extends Model
{
    protected $fillable = [
        'user_id', 'reviewable_type', 'reviewable_id', 'booking_id', 'rating',
        'title', 'content', 'is_verified_booking', 'status', 'is_featured',
    ];

    protected function casts(): array
    {
        return [
            'is_verified_booking' => 'boolean',
            'is_featured' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function reviewable()
    {
        return $this->morphTo();
    }

    public function scopeApproved($query)
    {
        return $query->where('status', 'approved');
    }
}
