<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationAction extends Model
{
    protected $table = 'automation_actions';

    protected $fillable = [
        'workflow_id', 'type', 'config', 'delay_minutes', 'position',
    ];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'workflow_id');
    }
}
