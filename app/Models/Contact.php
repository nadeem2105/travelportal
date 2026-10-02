<?php

namespace App\Models;

use App\Models\Concerns\HasTags;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Contact extends Model
{
    use SoftDeletes;
    use HasTags;

    protected $fillable = [
        'uuid', 'user_id', 'company_id', 'assigned_user_id', 'name',
        'phone', 'phone_raw', 'alternate_phone', 'email',
        'country', 'state', 'city', 'address',
        'preferred_language', 'preferred_contact_channel', 'timezone',
        'marketing_opt_in', 'whatsapp_opt_in', 'email_opt_in', 'sms_opt_in',
        'lifecycle_stage', 'source_id', 'notes', 'last_activity_at',
    ];

    protected $casts = [
        'marketing_opt_in' => 'boolean',
        'whatsapp_opt_in' => 'boolean',
        'email_opt_in' => 'boolean',
        'sms_opt_in' => 'boolean',
        'last_activity_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $c) {
            if (empty($c->uuid)) {
                $c->uuid = (string) Str::uuid();
            }
        });
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function company(): BelongsTo
    {
        return $this->belongsTo(Company::class);
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'assigned_user_id');
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(LeadSource::class, 'source_id');
    }

    public function leads(): HasMany
    {
        return $this->hasMany(CrmLead::class, 'contact_id')->latest();
    }

    public function contactGroups(): \Illuminate\Database\Eloquent\Relations\BelongsToMany
    {
        return $this->belongsToMany(ContactGroup::class, 'contact_group_members', 'contact_id', 'contact_group_id')
            ->withTimestamps();
    }

    public function activities(): HasMany
    {
        return $this->hasMany(CrmActivity::class, 'contact_id')->latest('occurred_at');
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(CrmTask::class, 'contact_id');
    }
}
