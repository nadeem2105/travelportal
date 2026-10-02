<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingOptimizationRule extends Model
{
    protected $table = 'marketing_optimization_rules';

    protected $fillable = [
        'name',
        'platform',
        'metric',
        'operator',
        'threshold',
        'minimum_data',
        'action',
        'action_config',
        'approval_required',
        'cooldown_hours',
        'enabled',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'action_config' => 'array',
            'threshold' => 'decimal:4',
            'approval_required' => 'boolean',
            'enabled' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(MarketingOptimizationRun::class, 'rule_id');
    }
}
