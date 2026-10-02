<?php

namespace Database\Seeders;

use App\Models\HomepageSection;
use Illuminate\Database\Seeder;

class HomepageSectionSeeder extends Seeder
{
    public function run(): void
    {
        $sections = [
            ['hero', 'Hero Banner', 'hero', null, null, 0, 'images/hero.svg'],
            ['destinations', 'Explore Destinations', 'destinations', 'Explore Kashmir', 'Popular destinations for your next trip', 1, null],
            ['packages', 'Popular Packages', 'packages', 'Popular Kashmir Tour Packages', 'Handpicked experiences for every traveller', 2, null],
            ['hotels', 'Handpicked Stays', 'hotels', 'Handpicked Stays', 'Luxury resorts, houseboats and cosy lodges', 3, null],
            ['offers', 'Offers Strip', 'offers', 'Offers & Coupons', 'Save more on your next Kashmir trip', 4, null],
            ['why_choose', 'Why Choose Us + App Promo', 'why_choose', 'Why Choose Leemroz Travels', null, 5, null],
            ['testimonials', 'Traveller Testimonials', 'testimonials', 'What Our Travellers Say', null, 6, null],
            ['cta', 'Plan My Trip CTA', 'cta', 'Not sure where to start?', 'Tell us your dates and budget — our Kashmir travel expert will craft a custom itinerary and call you back with a free quote.', 7, null],
            ['guide', 'Kashmir Guide Teasers', 'guide', 'Kashmir Travel Guide', 'Stories, tips and hidden gems', 8, null],
            ['faq', 'Frequently Asked Questions', 'faq', 'Frequently Asked Questions', 'Everything you need to know before you book', 9, null],
        ];

        foreach ($sections as [$key, $name, $type, $title, $subtitle, $order, $image]) {
            HomepageSection::updateOrCreate(
                ['key' => $key],
                [
                    'name' => $name,
                    'type' => $type,
                    'title' => $title,
                    'subtitle' => $subtitle,
                    'image' => $image,
                    'is_enabled' => true,
                    'sort_order' => $order,
                ]
            );
        }

        HomepageSection::where('key', 'why_choose')->update(['cta_text' => 'Download Our App']);
        HomepageSection::where('key', 'hotels')->update(['cta_text' => 'View All Hotels']);
        HomepageSection::where('key', 'cta')->update(['cta_text' => 'Get a Free Quote']);
    }
}
