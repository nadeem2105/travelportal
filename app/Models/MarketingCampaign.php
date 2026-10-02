<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    protected $table = 'marketing_campaigns';

    protected $fillable = [
        'account_id',
        'provider',
        'external_campaign_id',
        'name',
        'objective',
        'status',
        'external_status',
        'approval_status',
        'product_type',
        'product_id',
        'destination',
        'landing_page',
        'budget_type',
        'daily_budget',
        'lifetime_budget',
        'currency',
        'bidding_strategy',
        'start_at',
        'end_at',
        'target_cpl',
        'target_roas',
        'target_leads',
        'utm_campaign',
        'naming_parts',
        'meta',
        'created_by',
        'approved_by',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'naming_parts' => 'array',
            'meta' => 'array',
            'daily_budget' => 'decimal:2',
            'lifetime_budget' => 'decimal:2',
            'target_cpl' => 'decimal:2',
            'target_roas' => 'decimal:2',
            'start_at' => 'datetime',
            'end_at' => 'datetime',
            'published_at' => 'datetime',
        ];
    }

    public function account(): BelongsTo
    {
        return $this->belongsTo(MarketingAccount::class, 'account_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function groups(): HasMany
    {
        return $this->hasMany(MarketingCampaignGroup::class, 'campaign_id');
    }

    public function ads(): HasMany
    {
        return $this->hasMany(MarketingAd::class, 'campaign_id');
    }

    public function changes(): HasMany
    {
        return $this->hasMany(MarketingCampaignChange::class, 'campaign_id');
    }

    public function isPublished(): bool
    {
        return $this->approval_status === 'published' || $this->published_at !== null;
    }
}
