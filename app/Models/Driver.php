<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Driver extends Model
{
    protected $fillable = [
        'name', 'phone', 'alt_phone', 'license_number', 'vehicle_number',
        'vehicle_type', 'vehicle_model', 'vendor_name', 'cab_vendor_id',
        'photo_path', 'home_base', 'rating', 'notes', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rating' => 'decimal:1',
        ];
    }

    public function assignments()
    {
        return $this->hasMany(DriverAssignment::class);
    }

    public function activeAssignments()
    {
        return $this->hasMany(DriverAssignment::class)->whereIn('status', ['assigned', 'reassigned']);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /** Normalised digits for WhatsApp / tel links. */
    public function waNumber(): string
    {
        return preg_replace('/\D+/', '', (string) $this->phone);
    }

    public function vehicleLabel(): string
    {
        return trim(implode(' · ', array_filter([
            $this->vehicle_model ?: ($this->vehicle_type ? label_case($this->vehicle_type) : null),
            $this->vehicle_number,
        ]))) ?: '—';
    }
}
