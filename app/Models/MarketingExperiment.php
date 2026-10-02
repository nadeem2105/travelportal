<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingExperiment extends Model
{
    protected $table = 'marketing_experiments';

    protected $fillable = [
        'campaign_id',
        'name',
        'dimension',
        'status',
        'started_at',
        'ended_at',
        'winner',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function variants(): HasMany
    {
        return $this->hasMany(MarketingExperimentVariant::class, 'experiment_id');
    }
}
