<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingConnection extends Model
{
    protected $table = 'marketing_connections';

    protected $fillable = [
        'provider',
        'name',
        'credentials',
        'external_user_id',
        'status',
        'token_expires_at',
        'last_synced_at',
        'last_sync_status',
        'last_error',
        'connected_by',
    ];

    protected function casts(): array
    {
        return [
            'credentials' => 'encrypted:array',
            'token_expires_at' => 'datetime',
            'last_synced_at' => 'datetime',
        ];
    }

    public function accounts(): HasMany
    {
        return $this->hasMany(MarketingAccount::class, 'connection_id');
    }

    public function connectedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'connected_by');
    }

    public function scopeConnected($query)
    {
        return $query->where('status', 'connected');
    }

    public function isExpired(): bool
    {
        return $this->token_expires_at !== null && $this->token_expires_at->isPast();
    }
}
