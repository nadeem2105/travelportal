<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingOptimizationRun extends Model
{
    protected $table = 'marketing_optimization_runs';

    protected $fillable = [
        'rule_id',
        'campaign_id',
        'status',
        'snapshot',
        'before',
        'after',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'before' => 'array',
            'after' => 'array',
        ];
    }

    public function rule(): BelongsTo
    {
        return $this->belongsTo(MarketingOptimizationRule::class, 'rule_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}
