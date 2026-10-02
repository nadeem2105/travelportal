<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripDay extends Model
{
    protected $fillable = [
        'trip_id', 'day_number', 'date', 'title', 'summary',
        'hotel_snapshot', 'meals', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['date' => 'date'];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function events()
    {
        return $this->hasMany(TripEvent::class)->orderBy('sort_order')->orderBy('time');
    }
}
