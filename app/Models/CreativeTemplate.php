<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A reusable, dynamic creative template. Headline / primary-text / image-prompt
 * templates support {{package_name}}, {{destination}}, {{price}}, {{discount}},
 * {{hotel_name}}, {{phone}}, {{website}}, {{whatsapp}}, {{cta}}, {{booking_url}}.
 * `layers` holds overlay layout config consumed by the compositor.
 */
class CreativeTemplate extends Model
{
    use SoftDeletes;

    protected $table = 'creative_templates';

    protected $fillable = [
        'name', 'category', 'subcategory', 'platform', 'format', 'style',
        'description', 'headline_template', 'primary_text_template',
        'image_prompt_template', 'layers', 'variables', 'preview_path',
        'status', 'is_system', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'layers' => 'array',
            'variables' => 'array',
            'is_system' => 'boolean',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function scopeActive($q)
    {
        return $q->where('status', 'active');
    }

    /** Replace {{var}} tokens in a string from a flat data map. */
    public static function render(?string $template, array $data): string
    {
        if (! $template) {
            return '';
        }

        return preg_replace_callback('/\{\{\s*([a-z_]+)\s*\}\}/i', function ($m) use ($data) {
            return (string) ($data[$m[1]] ?? '');
        }, $template);
    }
}
