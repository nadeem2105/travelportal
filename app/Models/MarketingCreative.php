<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A single ad creative in the Ad Creative Studio. Adopted + extended from the
 * previously-orphaned marketing_creatives table. Factual fields (price, dates,
 * hotel, contact) are resolved from the portal at generation time and stored in
 * `spec` — the studio never invents them.
 */
class MarketingCreative extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_creatives';

    protected $fillable = [
        'campaign_id', 'brand_kit_id', 'template_id',
        'name', 'type', 'product_type', 'product_id', 'destination',
        'objective', 'audience', 'audience_detail', 'platform', 'format',
        'language', 'style', 'headline', 'copy', 'spec', 'tracking', 'warnings',
        'asset_id', 'render_path', 'thumb_path', 'width', 'height',
        'ai_generated', 'tags', 'status', 'generation_status', 'generation_error',
        'approval_status', 'approval_note', 'approved_by',
        'variation_group', 'variation_focus', 'parent_id', 'version',
        'ai_generation_id', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'copy' => 'array',
            'spec' => 'array',
            'tracking' => 'array',
            'warnings' => 'array',
            'tags' => 'array',
            'ai_generated' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function approver(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'approved_by');
    }

    public function brandKit(): BelongsTo
    {
        return $this->belongsTo(CreativeBrandKit::class, 'brand_kit_id');
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(CreativeTemplate::class, 'template_id');
    }

    public function asset(): BelongsTo
    {
        return $this->belongsTo(MarketingAsset::class, 'asset_id');
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'campaign_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    public function scopeDrafts($q)
    {
        return $q->where('approval_status', 'draft');
    }

    public function scopePublished($q)
    {
        return $q->where('approval_status', 'published');
    }

    /** Best available preview URL (composited render, else thumb, else asset). */
    public function previewUrl(): ?string
    {
        $path = $this->render_path ?: $this->thumb_path;

        return $path ? img($path) : null;
    }
}
