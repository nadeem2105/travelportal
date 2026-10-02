<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code', 'description', 'discount_type', 'discount_value', 'product_types',
        'min_booking_amount', 'max_discount', 'usage_limit', 'per_user_limit',
        'used_count', 'first_booking_only', 'user_ids', 'starts_at', 'ends_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'product_types' => 'array',
            'user_ids' => 'array',
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')
            ->where(fn ($q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn ($q) => $q->whereNull('ends_at')->orWhere('ends_at', '>=', now()));
    }
}
