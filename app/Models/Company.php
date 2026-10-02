<?php

namespace App\Models;

use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Company extends Model
{
    use SoftDeletes;
    use HasTags;

    protected $fillable = [
        'uuid', 'name', 'legal_name', 'email', 'phone', 'address', 'city', 'state', 'country',
        'gst_number', 'tax_number', 'type', 'assigned_user_id', 'status', 'notes',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            if (empty($c->uuid)) {
                $c->uuid = (string) Str::uuid();
            }
        });
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_user_id');
    }

    public function contacts(): HasMany
    {
        return $this->hasMany(Contact::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class);
    }
}
