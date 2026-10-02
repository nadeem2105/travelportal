<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A single page hit. High-volume, append-only: no updated_at, created_at is
 * DB-defaulted. time_on_page / scroll_depth / is_exit are patched in later by a
 * client beacon (sendBeacon on unload) keyed by the page view id.
 */
class AnalyticsPageView extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'page_view_id',
        'session_id',
        'anonymous_id',
        'user_id',
        'url',
        'path',
        'title',
        'referrer',
        'device_type',
        'time_on_page',
        'scroll_depth',
        'is_exit',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'is_exit' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    public function session()
    {
        return $this->belongsTo(AnalyticsSession::class, 'session_id', 'session_id');
    }
}
