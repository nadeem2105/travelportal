<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingSyncRun extends Model
{
    protected $table = 'marketing_sync_runs';

    protected $fillable = [
        'provider',
        'entity',
        'account_id',
        'status',
        'records',
        'cursor',
        'error',
        'started_at',
        'finished_at',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MarketingAccount::class, 'account_id');
    }
}
