<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaignGroup extends Model
{
    protected $table = 'marketing_campaign_groups';

    protected $fillable = [
        'campaign_id',
        'external_id',
        'name',
        'status',
        'external_status',
        'budget',
        'targeting',
    ];

    protected function casts(): array
    {
        return [
            'targeting' => 'array',
            'budget' => 'decimal:2',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function keywords(): HasMany
    {
        return $this->hasMany(MarketingKeyword::class, 'group_id');
    }
}
