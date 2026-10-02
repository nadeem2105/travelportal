<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One row per visitor session: device/geo environment plus first-touch AND
 * last-touch traffic attribution. Written once at session start by the
 * TrackVisit middleware and touched (last_activity_at + rollups) on subsequent
 * requests. IP is stored hashed only. No PII.
 */
class AnalyticsSession extends Model
{
    protected $fillable = [
        'session_id',
        'anonymous_id',
        'user_id',
        'is_returning',
        'device_type',
        'browser',
        'os',
        'screen',
        'language',
        'country',
        'region',
        'city',
        'ip_hash',
        'user_agent',
        'landing_page',
        'referrer',
        'channel',
        'first_touch',
        'last_touch',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_term',
        'utm_content',
        'gclid',
        'fbclid',
        'page_views',
        'events_count',
        'started_at',
        'last_activity_at',
    ];

    protected function casts(): array
    {
        return [
            'is_returning' => 'boolean',
            'first_touch' => 'array',
            'last_touch' => 'array',
            'started_at' => 'datetime',
            'last_activity_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function events()
    {
        return $this->hasMany(AnalyticsEvent::class, 'session_id', 'session_id');
    }

    public function pageViews()
    {
        return $this->hasMany(AnalyticsPageView::class, 'session_id', 'session_id');
    }
}
