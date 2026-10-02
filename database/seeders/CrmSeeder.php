<?php

namespace Database\Seeders;

use App\Models\LeadSource;
use App\Models\Pipeline;
use App\Models\Tag;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Seeds default CRM configuration: lead sources, the default sales pipeline
 * with stages, and starter tags. Idempotent (updateOrCreate / firstOrCreate).
 *
 * Run:  php artisan db:seed --class=CrmSeeder
 */
class CrmSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedSources();
        $this->seedPipeline();
        $this->seedTags();
    }

    protected function seedSources(): void
    {
        $sources = [
            ['Meta Ads', 'meta', 'paid'], ['Google Ads', 'google', 'paid'],
            ['Website', 'website', 'organic'], ['WhatsApp', 'whatsapp', 'messaging'],
            ['Instagram', 'instagram', 'social'], ['Facebook', 'facebook', 'social'],
            ['Organic Search', 'seo', 'organic'], ['Referral', 'referral', 'referral'],
            ['Phone', 'phone', 'direct'], ['Walk-in', 'walk-in', 'direct'],
            ['Existing Customer', 'existing-customer', 'repeat'], ['Partner', 'partner', 'partner'],
            ['B2B Agent', 'b2b-agent', 'b2b'], ['Corporate', 'corporate', 'corporate'],
            ['Email', 'email', 'email'], ['Other', 'other', 'other'],
        ];

        foreach ($sources as $i => [$name, $platform, $medium]) {
            LeadSource::updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name, 'platform' => $platform, 'medium' => $medium, 'is_active' => true, 'sort_order' => $i]
            );
        }
    }

    protected function seedPipeline(): void
    {
        $pipeline = Pipeline::updateOrCreate(
            ['slug' => 'sales-pipeline'],
            ['name' => 'Sales Pipeline', 'key' => 'default', 'is_default' => true, 'is_active' => true, 'sort_order' => 0]
        );

        // name, probability, is_won, is_lost
        $stages = [
            ['New Lead', 5, false, false],
            ['Contacted', 15, false, false],
            ['Qualified', 30, false, false],
            ['Requirement Collected', 40, false, false],
            ['Quotation Sent', 55, false, false],
            ['Negotiation', 70, false, false],
            ['Follow-up', 65, false, false],
            ['Payment Pending', 85, false, false],
            ['Booking Confirmed', 95, false, false],
            ['Travel Completed', 100, true, false],
            ['Converted', 100, true, false],
            ['Lost', 0, false, true],
        ];

        foreach ($stages as $i => [$name, $prob, $won, $lost]) {
            $pipeline->stages()->updateOrCreate(
                ['key' => Str::slug($name)],
                ['name' => $name, 'sort_order' => $i, 'probability' => $prob, 'is_won' => $won, 'is_lost' => $lost]
            );
        }
    }

    protected function seedTags(): void
    {
        foreach ([
            ['VIP', '#f59e0b'], ['Honeymoon', '#ec4899'], ['Family', '#3b82f6'],
            ['Corporate', '#6366f1'], ['High Value', '#10b981'], ['Repeat Customer', '#14b8a6'],
            ['Hot Lead', '#ef4444'], ['Urgent', '#f43f5e'], ['Payment Pending', '#eab308'],
        ] as [$name, $color]) {
            Tag::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name, 'color' => $color]);
        }
    }
}
