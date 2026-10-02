<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AutomationRun extends Model
{
    protected $table = 'automation_runs';

    protected $fillable = [
        'workflow_id', 'lead_id', 'contact_id', 'trigger_event', 'status', 'log',
    ];

    protected function casts(): array
    {
        return ['log' => 'array'];
    }

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(AutomationWorkflow::class, 'workflow_id');
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }
}
