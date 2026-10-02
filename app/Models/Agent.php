<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;

class Agent extends Authenticatable
{
    use HasFactory, Notifiable;

    protected $guard = 'agent';

    protected $fillable = [
        'user_id',
        'agency_name',
        'agency_code',
        'contact_person',
        'email',
        'password',
        'phone',
        'city',
        'state',
        'pincode',
        'address',
        'pan_number',
        'gst_number',
        'iata_code',
        'logo_path',
        'pan_document',
        'gst_document',
        'business_license',
        'status',
        'wallet_balance',
        'credit_limit',
        'credit_balance',
        'commission_rate',
        'markup_rate',
        'approved_by',
        'approved_at',
        'applied_at',
        'rejection_reason',
        'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'password' => 'hashed',
        'wallet_balance' => 'decimal:2',
        'credit_limit' => 'decimal:2',
        'credit_balance' => 'decimal:2',
        'commission_rate' => 'decimal:2',
        'markup_rate' => 'decimal:2',
        'approved_at' => 'datetime',
        'applied_at' => 'datetime',
        'last_login_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(AgentTransaction::class);
    }

    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }

    public function scopeApproved(Builder $query): Builder
    {
        return $query->where('status', 'approved');
    }

    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', 'pending');
    }

    public function isApproved(): bool
    {
        return $this->status === 'approved';
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }

    public function availableCredit(): float
    {
        return (float) $this->credit_limit - (float) $this->credit_balance;
    }

    public function totalPurchasingPower(): float
    {
        return (float) $this->wallet_balance + max(0, $this->availableCredit());
    }

    public function logoUrl(): ?string
    {
        if (empty($this->logo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->logo_path);
    }

    /**
     * Send the password reset notification via the agent broker.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new \App\Notifications\AgentResetPassword($token));
    }
}
