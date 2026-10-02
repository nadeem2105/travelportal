<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAiAudit extends Model
{
    protected $table = 'marketing_ai_audits';

    protected $fillable = [
        'user_id',
        'provider',
        'model',
        'action',
        'input_summary',
        'output_summary',
        'campaign_id',
        'changes_proposed',
        'changes_applied',
        'approval_status',
    ];

    protected function casts(): array
    {
        return [
            'changes_proposed' => 'array',
            'changes_applied' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'user_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }
}
