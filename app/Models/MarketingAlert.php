<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAlert extends Model
{
    protected $table = 'marketing_alerts';

    protected $fillable = [
        'type',
        'severity',
        'campaign_id',
        'title',
        'message',
        'data',
        'is_resolved',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'data' => 'array',
            'is_resolved' => 'boolean',
            'resolved_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}
