<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrmFollowUp extends Model
{
    use HasFactory;

    protected $fillable = [
        'lead_id',
        'admin_id',
        'note',
        'scheduled_at',
        'is_completed',
        'completed_at',
    ];

    protected $casts = [
        'scheduled_at' => 'datetime',
        'is_completed' => 'boolean',
        'completed_at' => 'datetime',
    ];

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function staff(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }
}
