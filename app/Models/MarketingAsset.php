<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MarketingAsset extends Model
{
    use SoftDeletes;

    protected $table = 'marketing_assets';

    protected $fillable = [
        'name',
        'path',
        'thumb_path',
        'mime_type',
        'size',
        'dimensions',
        'width',
        'height',
        'duration',
        'category',
        'source',
        'product_type',
        'product_id',
        'checksum',
        'license_status',
        'ai_generated',
        'provider',
        'ai_generation_id',
        'tags',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'ai_generated' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function url(): ?string
    {
        return $this->path ? img($this->path) : null;
    }

    public function thumbUrl(): ?string
    {
        return img($this->thumb_path ?: $this->path);
    }
}
