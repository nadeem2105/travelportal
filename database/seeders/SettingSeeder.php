<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $defaults = [
            // general
            'company_name' => ['Leemroz Travels', 'text', true],
            'company_tagline' => ['Explore · Book · Experience', 'text', true],
            'company_logo' => ['images/logo.svg', 'image', true],
            'company_favicon' => ['images/favicon.svg', 'image', true],
            'company_phone' => ['+91 70069 76447', 'text', true],
            'company_email' => ['hello@leemroztravels.com', 'text', true],
            'company_address' => ['Ishber Nishat Gupt Ganga, Srinagar J&K -190025', 'textarea', true],
            'company_gst' => ['01ABCDE1234F1Z5', 'text', false],
            'website_url' => ['https://www.leemroztravels.com', 'text', false],
            'business_type' => ['ota', 'text', false],
            'company_registration' => ['U63040JK2024PTC012345', 'text', false],
            'site_name' => ['Leemroz Travels', 'text', true],
            'site_email' => ['support@leemroztravels.com', 'text', false],
            'default_language' => ['en', 'text', false],
            'timezone' => ['Asia/Kolkata', 'text', false],
            'date_format' => ['d M Y', 'text', false],
            'time_format' => ['H:i', 'text', false],
            'currency_code' => ['INR', 'text', false],
            'footer_about' => ['Discover the magic of Kashmir with flights, hotels, cabs and curated tour packages — all in one place.', 'textarea', true],
            'footer_copyright' => ['All rights reserved.', 'text', true],
            'hero_title_line1' => ['More than a Trip', 'text', true],
            'hero_title_line2' => ['A Beautiful Story', 'text', true],
            'hero_subtitle' => ['Discover the magic of Kashmir with flights, hotels, cabs and curated tour packages — all in one place.', 'textarea', true],
            'hero_script_line1' => ['Kashmir', 'text', true],
            'hero_script_line2' => ['Waits for You', 'text', true],
            'app_download_url' => ['#download-app', 'text', true],
            'trust_best_price' => ['Best Price Guarantee', 'text', true],
            'trust_easy_booking' => ['Easy Booking', 'text', true],
            'trust_support' => ['24/7 Support', 'text', true],
            'trust_secure' => ['Safe & Secure Payments', 'text', true],
            'why_script_line1' => ['Explore', 'text', true],
            'why_script_line2' => ['Book', 'text', true],
            'why_script_line3' => ['Belong', 'text', true],
            'newsletter_title' => ['Get Travel Deals Before Anyone Else', 'text', true],
            'newsletter_subtitle' => ['Exclusive offers on flights, hotels, cabs and Kashmir packages.', 'text', true],
            // booking
            'booking_id_prefix' => ['TQC', 'text', false],
            'booking_expiry_minutes' => ['30', 'number', false],
            'refund_processing_days' => ['5-7', 'text', false],
            'auto_confirm_packages' => ['1', 'boolean', false],
            // email
            'mail_mailer' => ['smtp', 'text', false],
            'mail_host' => ['127.0.0.1', 'text', false],
            'mail_port' => ['587', 'number', false],
            'mail_encryption' => ['tls', 'text', false],
            'mail_username' => ['', 'text', false],
            'mail_password' => ['', 'encrypt', false],
            'mail_from_name' => ['Leemroz Travels', 'text', false],
            'mail_from_address' => ['hello@leemroztravels.com', 'text', false],
            'mail_notifications_enabled' => ['1', 'boolean', false],
            // seo
            'seo_meta_description' => ['Book Kashmir tour packages, flights, hotels and cabs with Leemroz Travels — trusted local experts in Srinagar, Gulmarg, Pahalgam and beyond.', 'textarea', false],
            'og_default_image' => ['images/hero.svg', 'image', false],
            // social
            'social_facebook' => ['https://facebook.com/travelquecashmir', 'text', true],
            'social_instagram' => ['https://instagram.com/travelquecashmir', 'text', true],
            'social_youtube' => ['https://youtube.com/@travelquecashmir', 'text', true],
            // app
            'app_android_url' => ['https://play.google.com/store', 'text', true],
            'app_ios_url' => ['https://apps.apple.com', 'text', true],
            // legal
            'invoice_terms' => ['Thank you for booking with Leemroz Travels. This is a system generated invoice.', 'textarea', false],
            // maintenance
            'maintenance_enabled' => ['0', 'boolean', false],
            'maintenance_message' => ['We are performing scheduled maintenance. Please check back shortly.', 'textarea', true],
        ];

        foreach ($defaults as $key => [$value, $type, $isPublic]) {
            Setting::updateOrCreate(
                ['key' => $key],
                [
                    'value' => $value,
                    'type' => $type,
                    'is_public' => $isPublic,
                    'group' => $this->groupFor($key),
                ]
            );
        }
    }

    protected function groupFor(string $key): string
    {
        return match (true) {
            str_starts_with($key, 'hero_'), str_starts_with($key, 'why_'), str_starts_with($key, 'trust_'), str_starts_with($key, 'newsletter_') => 'general',
            str_starts_with($key, 'booking_') => 'booking',
            str_starts_with($key, 'mail_') => 'email',
            str_starts_with($key, 'seo_'), $key === 'og_default_image' => 'seo',
            str_starts_with($key, 'social_') => 'social',
            str_starts_with($key, 'app_') => 'app',
            $key === 'invoice_terms' => 'legal',
            str_starts_with($key, 'maintenance_') => 'maintenance',
            str_starts_with($key, 'company_') || in_array($key, ['currency_code', 'footer_about', 'footer_copyright', 'website_url', 'business_type', 'company_registration', 'site_name', 'site_email', 'default_language', 'timezone', 'date_format', 'time_format']) => 'general',
            default => 'general',
        };
    }
}
