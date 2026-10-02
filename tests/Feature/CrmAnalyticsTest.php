<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\CrmQuotation;
use App\Services\Crm\LeadService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CrmAnalyticsTest extends TestCase
{
    use RefreshDatabase;

    protected bool $seed = true;

    public function test_analytics_page_renders_with_metrics(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true, 'status' => 'active']);

        // A couple of leads + a won quotation.
        $lead = app(LeadService::class)->create([
            'name' => 'Analytics Lead', 'phone' => '9876500777', 'email' => 'a@ex.com',
            'destination' => 'Kashmir', 'product_type' => 'package', 'source_slug' => 'website',
        ]);
        CrmQuotation::create([
            'lead_id' => $lead->id, 'contact_id' => $lead->contact_id,
            'title' => 'Test Quote', 'subtotal' => 20000, 'total_amount' => 21000,
            'status' => 'accepted', 'accepted_at' => now(),
        ]);

        $this->actingAs($admin, 'admin')
            ->get('/admin/crm-analytics?preset=30d')
            ->assertOk()
            ->assertSee('CRM Analytics')
            ->assertSee('Conversion Funnel')
            ->assertSee('Quotation Win Rate');
    }

    public function test_analytics_respects_preset_range(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true, 'status' => 'active']);

        $this->actingAs($admin, 'admin')
            ->get('/admin/crm-analytics?preset=7d')
            ->assertOk()
            ->assertSee('Agent Performance');
    }
}
