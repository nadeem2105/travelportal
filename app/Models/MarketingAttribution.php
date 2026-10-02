<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAttribution extends Model
{
    protected $table = 'marketing_attributions';

    protected $fillable = [
        'lead_id',
        'contact_id',
        'campaign_id',
        'provider',
        'external_campaign_id',
        'external_group_id',
        'external_ad_id',
        'creative_ref',
        'model',
        'quoted_value',
        'booking_value',
        'paid_value',
        'refunded_value',
        'net_value',
    ];

    protected function casts(): array
    {
        return [
            'quoted_value' => 'decimal:2',
            'booking_value' => 'decimal:2',
            'paid_value' => 'decimal:2',
            'refunded_value' => 'decimal:2',
            'net_value' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}
