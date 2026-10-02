<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BookingTraveller extends Model
{
    protected $fillable = [
        'booking_id', 'traveller_type', 'title', 'first_name', 'last_name',
        'dob', 'gender', 'nationality', 'id_type', 'id_number', 'is_primary',
    ];

    protected function casts(): array
    {
        return ['dob' => 'date', 'is_primary' => 'boolean'];
    }

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function getFullNameAttribute(): string
    {
        return trim(($this->title ? $this->title . '. ' : '') . $this->first_name . ' ' . $this->last_name);
    }
}
