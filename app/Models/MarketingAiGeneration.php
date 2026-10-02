<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingAiGeneration extends Model
{
    protected $table = 'marketing_ai_generations';

    protected $fillable = [
        'provider',
        'model',
        'generation_type',
        'prompt_version',
        'input_context_hash',
        'input_tokens',
        'output_tokens',
        'estimated_cost',
        'result',
        'status',
        'campaign_id',
        'asset_id',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'result' => 'array',
            'estimated_cost' => 'decimal:4',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }
}
