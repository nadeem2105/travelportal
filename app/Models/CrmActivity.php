<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

class CrmActivity extends Model
{
    protected $fillable = [
        'uuid', 'subject_type', 'subject_id', 'contact_id', 'lead_id', 'booking_id',
        'type', 'title', 'description', 'data', 'performed_by', 'is_internal', 'occurred_at',
    ];

    protected $casts = [
        'data' => 'array',
        'is_internal' => 'boolean',
        'occurred_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $a) {
            if (empty($a->uuid)) {
                $a->uuid = (string) Str::uuid();
            }
            if (empty($a->occurred_at)) {
                $a->occurred_at = now();
            }
        });
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class);
    }

    public function lead(): BelongsTo
    {
        return $this->belongsTo(CrmLead::class, 'lead_id');
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'performed_by');
    }
}
