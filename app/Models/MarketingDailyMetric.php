<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingDailyMetric extends Model
{
    protected $table = 'marketing_daily_metrics';

    protected $fillable = [
        'date',
        'provider',
        'account_id',
        'campaign_id',
        'group_id',
        'ad_id',
        'spend',
        'impressions',
        'reach',
        'clicks',
        'leads',
        'conversions',
        'conversion_value',
        'frequency',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'spend' => 'decimal:2',
            'conversion_value' => 'decimal:2',
            'frequency' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MarketingAccount::class, 'account_id');
    }
}
