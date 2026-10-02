<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingRecommendation extends Model
{
    protected $table = 'marketing_recommendations';

    protected $fillable = [
        'campaign_id',
        'category',
        'title',
        'reason',
        'evidence',
        'suggested_action',
        'expected_impact',
        'confidence',
        'risk',
        'status',
        'proposed_changes',
        'reviewed_by',
        'applied_at',
    ];

    protected function casts(): array
    {
        return [
            'evidence' => 'array',
            'proposed_changes' => 'array',
            'applied_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'reviewed_by');
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }
}
