<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Tax extends Model
{
    protected $fillable = [
        'name', 'product_type', 'calculation', 'value', 'is_inclusive', 'description', 'status',
    ];

    protected function casts(): array
    {
        return ['is_inclusive' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeForProduct($query, string $productType)
    {
        return $query->active()->whereIn('product_type', [$productType, 'all']);
    }
}
