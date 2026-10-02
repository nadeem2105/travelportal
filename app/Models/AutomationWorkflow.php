<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AutomationWorkflow extends Model
{
    protected $table = 'automation_workflows';

    protected $fillable = [
        'name', 'trigger_event', 'conditions', 'trigger_config',
        'is_active', 'description', 'run_count', 'last_run_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'conditions' => 'array',
            'trigger_config' => 'array',
            'is_active' => 'boolean',
            'last_run_at' => 'datetime',
        ];
    }

    public function actions(): HasMany
    {
        return $this->hasMany(AutomationAction::class, 'workflow_id')->orderBy('position');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(AutomationRun::class, 'workflow_id')->latest();
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
