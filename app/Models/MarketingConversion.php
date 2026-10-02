<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingConversion extends Model
{
    protected $table = 'marketing_conversions';

    protected $fillable = [
        'event',
        'campaign_id',
        'lead_id',
        'contact_id',
        'quotation_id',
        'booking_id',
        'value',
        'currency',
        'sent_to_provider',
        'occurred_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:2',
            'sent_to_provider' => 'boolean',
            'occurred_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}
