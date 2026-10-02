<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DriverAssignment extends Model
{
    public const SCOPE_ENTIRE = 'entire_trip';
    public const SCOPE_DAY = 'day';
    public const SCOPE_TRANSFER = 'transfer';

    public const SCOPES = [
        self::SCOPE_ENTIRE => 'Entire Trip',
        self::SCOPE_DAY => 'Specific Day',
        self::SCOPE_TRANSFER => 'Single Transfer',
    ];

    protected $fillable = [
        'trip_id', 'driver_id', 'scope', 'trip_day_id', 'trip_event_id',
        'pickup_location', 'drop_location', 'pickup_datetime', 'notes',
        'status', 'assigned_by', 'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'pickup_datetime' => 'datetime',
            'assigned_at' => 'datetime',
        ];
    }

    public function trip()
    {
        return $this->belongsTo(Trip::class);
    }

    public function driver()
    {
        return $this->belongsTo(Driver::class);
    }

    public function day()
    {
        return $this->belongsTo(TripDay::class, 'trip_day_id');
    }

    public function event()
    {
        return $this->belongsTo(TripEvent::class, 'trip_event_id');
    }

    public function histories()
    {
        return $this->hasMany(DriverAssignmentHistory::class)->latest();
    }

    public function isActive(): bool
    {
        return in_array($this->status, ['assigned', 'reassigned'], true);
    }

    public function scopeLabel(): string
    {
        $base = self::SCOPES[$this->scope] ?? label_case((string) $this->scope);
        if ($this->scope === self::SCOPE_DAY && $this->day) {
            return $base . ' — Day ' . $this->day->day_number;
        }
        if ($this->scope === self::SCOPE_TRANSFER && $this->event) {
            return $base . ' — ' . $this->event->title;
        }

        return $base;
    }
}
