<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * A brand profile applied to generated creatives (logo, colors, fonts, contact,
 * default CTA/disclaimer). Multiple kits are supported (multi-brand/tenant). One
 * is flagged default. Seeded from the portal's settings() on first use.
 */
class CreativeBrandKit extends Model
{
    use SoftDeletes;

    protected $table = 'creative_brand_kits';

    protected $fillable = [
        'name', 'is_default', 'brand_name', 'logo_path',
        'primary_color', 'secondary_color', 'accent_color', 'text_color',
        'font_family', 'website', 'phone', 'whatsapp', 'email', 'address',
        'socials', 'default_cta', 'default_disclaimer', 'status', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'socials' => 'array',
        ];
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Admin::class, 'created_by');
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? img($this->logo_path) : null;
    }

    /** The active default kit, creating one from portal settings if none exists. */
    public static function active(): self
    {
        $kit = static::where('is_default', true)->where('status', 'active')->first()
            ?? static::where('status', 'active')->first();

        if ($kit) {
            return $kit;
        }

        return static::create([
            'name' => 'Default Brand Kit',
            'is_default' => true,
            'brand_name' => settings('company_name', 'Leemroz Travels'),
            'logo_path' => settings('company_logo', 'images/logo.svg'),
            'primary_color' => '#2563eb',
            'secondary_color' => '#0b1f3a',
            'accent_color' => '#f59e0b',
            'text_color' => '#ffffff',
            'website' => settings('company_website', settings('website')),
            'phone' => settings('company_phone'),
            'whatsapp' => settings('company_whatsapp', settings('company_phone')),
            'email' => settings('company_email'),
            'address' => settings('company_address'),
            'default_cta' => 'Book Now',
            'default_disclaimer' => 'T&C apply. Prices subject to availability.',
            'status' => 'active',
        ]);
    }
}
