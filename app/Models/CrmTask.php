<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class CrmTask extends Model
{
    protected $fillable = [
        'uuid', 'title', 'description', 'type', 'lead_id', 'contact_id',
        'booking_id', 'quotation_id', 'assigned_user_id', 'due_at',
        'priority', 'status', 'completed_at', 'completed_by',
    ];

    protected $casts = [
        'due_at' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $t) {
            if (empty($t->uuid)) {
                $t->uuid = (string) Str::uuid();
            }
        });
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_user_id');
    }

    public function scopePending($query)
    {
        return $query->whereIn('status', ['pending', 'in_progress']);
    }

    public function scopeOverdue($query)
    {
        return $query->pending()->whereNotNull('due_at')->where('due_at', '<', now());
    }

    public function scopeDueToday($query)
    {
        return $query->pending()->whereDate('due_at', today());
    }
}
