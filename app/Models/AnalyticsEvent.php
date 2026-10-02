<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AnalyticsEvent extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'event_type',
        'event_id',
        'product_type',
        'product_id',
        'user_id',
        'session_id',
        'anonymous_id',
        'correlation_id',
        'channel',
        'value',
        'currency',
        'source',
        'meta',
        'ip_address',
        'user_agent',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'meta' => 'array',
            'value' => 'decimal:2',
            'created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function session()
    {
        return $this->belongsTo(AnalyticsSession::class, 'session_id', 'session_id');
    }
}
