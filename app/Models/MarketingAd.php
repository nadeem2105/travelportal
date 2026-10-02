<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAd extends Model
{
    protected $table = 'marketing_ads';

    protected $fillable = [
        'campaign_id',
        'group_id',
        'creative_id',
        'external_id',
        'name',
        'status',
        'external_status',
        'copy',
    ];

    protected function casts(): array
    {
        return [
            'copy' => 'array',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaignGroup::class, 'group_id');
    }
}
