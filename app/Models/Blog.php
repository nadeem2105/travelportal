<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Blog extends Model
{
    protected $fillable = [
        'admin_id', 'title', 'slug', 'category', 'excerpt', 'content',
        'cover_image', 'tags', 'views', 'published_at', 'status',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'published_at' => 'datetime',
        ];
    }

    public function author()
    {
        return $this->belongsTo(Admin::class, 'admin_id');
    }

    public function scopePublished($query)
    {
        return $query->where('status', 'published')->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    public function toSearchResult(string $type): array
    {
        return [
            'type' => $type,
            'title' => $this->title,
            'excerpt' => $this->excerpt,
            'cover_image' => $this->cover_image,
            'slug' => $this->slug,
        ];
    }
}