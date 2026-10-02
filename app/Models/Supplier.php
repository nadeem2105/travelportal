<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Supplier extends Model
{
    protected $fillable = [
        'name', 'slug', 'type', 'adapter', 'description', 'logo', 'environment',
        'priority', 'timeout_seconds', 'retry_attempts',
        'default_markup_percent', 'default_commission_percent', 'default_service_fee',
        'settings', 'status',
    ];

    protected function casts(): array
    {
        return ['settings' => 'array'];
    }

    public function credentials()
    {
        return $this->hasMany(SupplierCredential::class);
    }

    public function bookings()
    {
        return $this->hasMany(Booking::class);
    }

    public function credentialFor(string $environment): ?SupplierCredential
    {
        return $this->credentials->firstWhere('environment', $environment);
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->orderBy('priority');
    }

    public function scopeForType($query, string $type)
    {
        return $query->where('type', $type)->active();
    }
}
