<?php

namespace App\Services\Marketing\Creative;

/**
 * Central catalog of the studio's platforms, formats (with pixel dimensions),
 * objectives, audiences, styles and languages. Single source of truth shared by
 * controllers, views and the compositor so nothing drifts.
 */
class CreativeFormats
{
    /** format key => [label, width, height, platform, aspect] */
    public const FORMATS = [
        'ig_1x1'   => ['Instagram Post (1:1)', 1080, 1080, 'instagram', '1:1'],
        'ig_4x5'   => ['Instagram Portrait (4:5)', 1080, 1350, 'instagram', '4:5'],
        'ig_9x16'  => ['Instagram Story/Reel (9:16)', 1080, 1920, 'instagram', '9:16'],
        'fb_1x1'   => ['Facebook Post (1:1)', 1080, 1080, 'facebook', '1:1'],
        'fb_4x5'   => ['Facebook Feed (4:5)', 1080, 1350, 'facebook', '4:5'],
        'fb_9x16'  => ['Facebook Story (9:16)', 1080, 1920, 'facebook', '9:16'],
        'wa_9x16'  => ['WhatsApp Status (9:16)', 1080, 1920, 'whatsapp', '9:16'],
        'wa_1x1'   => ['WhatsApp Square (1:1)', 1080, 1080, 'whatsapp', '1:1'],
        'yt_16x9'  => ['YouTube (16:9)', 1920, 1080, 'youtube', '16:9'],
        'yt_9x16'  => ['YouTube Short (9:16)', 1080, 1920, 'youtube', '9:16'],
        'yt_thumb' => ['YouTube Thumbnail', 1280, 720, 'youtube', '16:9'],
        'web_hero' => ['Website Hero Banner', 1920, 800, 'website', 'wide'],
        'web_pkg'  => ['Website Package Banner', 1200, 628, 'website', 'wide'],
    ];

    public const PLATFORMS = [
        'instagram' => 'Instagram',
        'facebook' => 'Facebook',
        'whatsapp' => 'WhatsApp',
        'youtube' => 'YouTube',
        'website' => 'Website',
    ];

    public const OBJECTIVES = [
        'package_booking' => 'Package Booking',
        'hotel_booking' => 'Hotel Booking',
        'flight_booking' => 'Flight Booking',
        'lead_generation' => 'Lead Generation',
        'whatsapp_enquiry' => 'WhatsApp Enquiry',
        'website_traffic' => 'Website Traffic',
        'destination_awareness' => 'Destination Awareness',
        'offer_promotion' => 'Offer Promotion',
        'remarketing' => 'Remarketing',
        'social_engagement' => 'Social Engagement',
    ];

    /** objective => default CTA */
    public const OBJECTIVE_CTA = [
        'package_booking' => 'Book Now',
        'hotel_booking' => 'Book Now',
        'flight_booking' => 'Book Now',
        'lead_generation' => 'Get Quote',
        'whatsapp_enquiry' => 'WhatsApp Us',
        'website_traffic' => 'Explore Now',
        'destination_awareness' => 'Discover More',
        'offer_promotion' => 'Grab Offer',
        'remarketing' => 'Complete Booking',
        'social_engagement' => 'Learn More',
    ];

    public const AUDIENCES = [
        'honeymoon' => 'Honeymoon',
        'couples' => 'Couples',
        'families' => 'Families',
        'friends' => 'Friends',
        'adventure' => 'Adventure Travelers',
        'luxury' => 'Luxury Travelers',
        'budget' => 'Budget Travelers',
        'corporate' => 'Corporate Groups',
        'students' => 'Students',
        'seniors' => 'Senior Travelers',
        'international' => 'International Travelers',
        'domestic' => 'Domestic Travelers',
        'custom' => 'Custom Audience',
    ];

    public const STYLES = [
        'cinematic' => 'Cinematic', 'luxury' => 'Luxury', 'premium' => 'Premium',
        'minimal' => 'Minimal', 'romantic' => 'Romantic', 'family' => 'Family',
        'adventure' => 'Adventure', 'nature' => 'Nature', 'winter' => 'Winter',
        'summer' => 'Summer', 'festival' => 'Festival', 'corporate' => 'Corporate',
        'social' => 'Modern Social Media', 'tourism' => 'High-end Tourism',
    ];

    public const LANGUAGES = [
        'en' => 'English', 'hi' => 'Hindi', 'ur' => 'Urdu', 'hinglish' => 'Hinglish',
    ];

    public const VARIATION_FOCUS = [
        'price' => 'Price focused',
        'destination' => 'Destination focused',
        'experience' => 'Experience focused',
        'luxury' => 'Luxury focused',
        'emotional' => 'Emotional / storytelling',
    ];

    public static function label(string $format): string
    {
        return self::FORMATS[$format][0] ?? $format;
    }

    public static function dimensions(string $format): array
    {
        $f = self::FORMATS[$format] ?? self::FORMATS['ig_1x1'];

        return ['width' => $f[1], 'height' => $f[2]];
    }

    public static function platformOf(string $format): string
    {
        return self::FORMATS[$format][3] ?? 'instagram';
    }

    /** Nearest supported AI image size for a format's aspect ratio. */
    public static function imageSize(string $format): string
    {
        ['width' => $w, 'height' => $h] = self::dimensions($format);
        if ($w === $h) {
            return '1024x1024';
        }

        return $w > $h ? '1536x1024' : '1024x1536';
    }
}
