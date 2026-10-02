<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Guide extends Model
{
    protected $fillable = [
        'title', 'slug', 'destination_id', 'excerpt', 'content', 'cover_image',
        'sort_order', 'status',
    ];

    public function destination()
    {
        return $this->belongsTo(Destination::class);
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
