<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignTemplate extends Model
{
    protected $table = 'marketing_campaign_templates';

    protected $fillable = [
        'name',
        'platform',
        'objective',
        'service',
        'destination',
        'default_daily_budget',
        'targeting',
        'creative_structure',
        'landing_page',
        'tracking',
        'automation',
        'kpis',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'targeting' => 'array',
            'creative_structure' => 'array',
            'tracking' => 'array',
            'automation' => 'array',
            'kpis' => 'array',
            'default_daily_budget' => 'decimal:2',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
