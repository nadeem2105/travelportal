<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingKeyword extends Model
{
    protected $table = 'marketing_keywords';

    protected $fillable = [
        'group_id',
        'text',
        'match_type',
        'is_negative',
        'external_id',
    ];

    protected function casts(): array
    {
        return [
            'is_negative' => 'boolean',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaignGroup::class, 'group_id');
    }
}
