<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingAccount extends Model
{
    protected $table = 'marketing_accounts';

    protected $fillable = [
        'connection_id',
        'provider',
        'external_account_id',
        'account_name',
        'manager_customer_id',
        'business_id',
        'page_id',
        'currency',
        'timezone',
        'is_active',
        'last_synced_at',
        'last_sync_status',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'last_synced_at' => 'datetime',
        ];
    }

    public function connection(): BelongsTo
    {
        return $this->belongsTo(MarketingConnection::class, 'connection_id');
    }

    public function campaigns(): HasMany
    {
        return $this->hasMany(MarketingCampaign::class, 'account_id');
    }
}
