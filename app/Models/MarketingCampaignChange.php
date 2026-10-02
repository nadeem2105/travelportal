<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingCampaignChange extends Model
{
    protected $table = 'marketing_campaign_changes';

    protected $fillable = [
        'campaign_id',
        'field',
        'old_value',
        'new_value',
        'source',
        'changed_by',
    ];

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'changed_by');
    }
}
