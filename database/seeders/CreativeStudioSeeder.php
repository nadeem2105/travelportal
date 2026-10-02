<?php

namespace Database\Seeders;

use App\Models\CreativeBrandKit;
use App\Models\CreativeTemplate;
use Illuminate\Database\Seeder;

/**
 * Seeds the Ad Creative Studio with a default brand kit (from portal settings)
 * and a starter set of dynamic system templates. Idempotent — safe to re-run.
 */
class CreativeStudioSeeder extends Seeder
{
    public function run(): void
    {
        // Default brand kit (pulls live company details from settings()).
        CreativeBrandKit::active();

        $templates = [
            // Travel packages
            ['Kashmir Package', 'package', 'kashmir', 'tourism', '{{destination}} — {{duration}}', 'Discover {{package_name}}. From {{price}} per person. {{discount}}. Book now: {{phone}}'],
            ['Honeymoon Special', 'package', 'honeymoon', 'romantic', 'A {{destination}} Honeymoon to Remember', 'Romantic {{duration}} at {{package_name}}. From {{price}}. {{discount}} — enquire on WhatsApp {{whatsapp}}'],
            ['Family Holiday', 'package', 'family', 'family', 'Family Fun in {{destination}}', '{{package_name}} · {{duration}}. Great for families. From {{price}}. Call {{phone}}'],
            ['Luxury Holiday', 'package', 'luxury', 'luxury', 'Luxury {{destination}} Escape', 'Indulge in {{package_name}}. {{duration}} of premium travel. From {{price}}.'],
            ['Adventure Tour', 'package', 'adventure', 'adventure', '{{destination}} Adventure Awaits', 'Thrill-packed {{package_name}} · {{duration}}. From {{price}}. {{cta}}'],
            ['Group Tour', 'package', 'group', 'social', 'Group Getaway to {{destination}}', '{{package_name}} for groups. {{duration}}. Special rates from {{price}}.'],
            ['Weekend Trip', 'package', 'weekend', 'social', 'Weekend in {{destination}}', 'Quick escape: {{package_name}}. {{duration}}. From {{price}}. Book: {{website}}'],
            // Hotels
            ['Hotel Offer', 'hotel', 'offer', 'premium', 'Stay at {{hotel_name}}', '{{hotel_name}} in {{destination}}. From {{price}}/night. {{discount}}'],
            ['Luxury Hotel', 'hotel', 'luxury', 'luxury', 'Luxury Stay · {{hotel_name}}', 'Experience {{hotel_name}}, {{destination}}. From {{price}}/night.'],
            // Promotions
            ['Flash Sale', 'promotion', 'flash_sale', 'social', '⚡ Flash Sale: {{destination}}', '{{package_name}} now {{price}} — {{discount}}. Limited period. {{cta}}'],
            ['Seasonal Offer', 'promotion', 'seasonal', 'festival', '{{destination}} Seasonal Offer', 'Book {{package_name}} this season. From {{price}}. {{discount}}'],
            ['Early Bird', 'promotion', 'early_bird', 'minimal', 'Early Bird: {{destination}}', 'Plan ahead & save. {{package_name}} from {{price}}. {{discount}}'],
            // Social
            ['Instagram Story', 'social', 'story', 'social', '{{destination}} 🌸', 'Swipe up for {{package_name}} · From {{price}}'],
            ['WhatsApp Status', 'social', 'whatsapp', 'social', '{{destination}} Deal', '{{package_name}} · {{duration}} · {{price}}. WhatsApp {{whatsapp}}'],
        ];

        foreach ($templates as [$name, $cat, $sub, $style, $headline, $primary]) {
            CreativeTemplate::updateOrCreate(
                ['name' => $name, 'is_system' => true],
                [
                    'category' => $cat,
                    'subcategory' => $sub,
                    'style' => $style,
                    'headline_template' => $headline,
                    'primary_text_template' => $primary,
                    'status' => 'active',
                    'is_system' => true,
                    'variables' => ['package_name', 'destination', 'duration', 'price', 'discount', 'hotel_name', 'phone', 'website', 'whatsapp', 'cta'],
                ]
            );
        }
    }
}
