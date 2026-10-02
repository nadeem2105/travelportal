<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Airport extends Model
{
    protected $fillable = [
        'code', 'name', 'city', 'country', 'timezone', 'is_popular', 'sort_order', 'status',
    ];

    protected function casts(): array
    {
        return ['is_popular' => 'boolean'];
    }

    public function scopeActive($query)
    {
        return $query->where('status', 'active')->orderByDesc('is_popular')->orderBy('sort_order');
    }
}
