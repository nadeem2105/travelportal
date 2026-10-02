<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TripOperationComm extends Model
{
    protected $fillable = [
        'trip_id', 'driver_assignment_id', 'channel', 'recipient_type',
        'recipient', 'event_key', 'status', 'error', 'meta', 'sent_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'sent_at' => 'datetime',
        ];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }
}
