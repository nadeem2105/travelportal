<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Page extends Model
{
    protected $fillable = [
        'title', 'slug', 'content', 'template', 'show_in_footer',
        'sort_order', 'published_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'show_in_footer' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published');
    }
}
