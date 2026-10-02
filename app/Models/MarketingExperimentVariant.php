<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingExperimentVariant extends Model
{
    protected $table = 'marketing_experiment_variants';

    protected $fillable = [
        'experiment_id',
        'label',
        'config',
        'metrics',
    ];

    protected function casts(): array
    {
        return [
            'config' => 'array',
            'metrics' => 'array',
        ];
    }

    public function experiment(): BelongsTo
    {
        return $this->belongsTo(MarketingExperiment::class, 'experiment_id');
    }
}
