<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingAccount;
use App\Models\Package;
use App\Services\Marketing\Ai\AiCampaignService;
use App\Services\Marketing\Ai\AiUnavailableException;
use App\Services\Marketing\CampaignService;
use Illuminate\Http\Request;

/**
 * AI-assisted campaign creation. The AI only drafts copy/targeting suggestions;
 * the admin reviews them, and saving still produces a local DRAFT (published
 * paused later). If no AI provider is configured we say so plainly rather than
 * fabricating output.
 */
class AiMarketingController extends Controller
{
    public function __construct(
        private AiCampaignService $ai,
        private CampaignService $campaigns,
    ) {
    }

    public function campaignForm(Request $request)
    {
        return view('admin.marketing.ai.campaign', $this->formData() + [
            'brief' => [],
            'generated' => null,
        ]);
    }

    public function generateCampaign(Request $request)
    {
        $brief = $request->validate([
            'provider' => 'required|in:google_ads,meta_ads',
            'account_id' => 'nullable|exists:marketing_accounts,id',
            'objective' => 'nullable|string|max:40',
            'destination' => 'nullable|string|max:120',
            'product' => 'nullable|string|max:200',
            'product_id' => 'nullable|integer',
            'landing_page' => 'nullable|url|max:500',
            'audience' => 'nullable|string|max:300',
            'budget' => 'nullable|string|max:60',
            'tone' => 'nullable|string|max:60',
            'extra' => 'nullable|string|max:1000',
        ]);

        if (! $this->ai->isConfigured()) {
            return back()->withInput()->with('error', 'No AI provider is configured. Set AI_DEFAULT_PROVIDER and the matching API key in your .env, then run php artisan config:clear.');
        }

        try {
            $generated = $this->ai->suggest($brief, auth('admin')->id());
        } catch (AiUnavailableException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            return back()->withInput()->with('error', 'AI generation failed: ' . $e->getMessage());
        }

        return view('admin.marketing.ai.campaign', $this->formData() + [
            'brief' => $brief,
            'generated' => $generated,
        ]);
    }

    private function formData(): array
    {
        return [
            'accounts' => MarketingAccount::where('is_active', true)->get(),
            'packages' => Package::orderBy('name')->limit(200)->get(['id', 'name', 'slug']),
            'objectives' => ['Leads', 'Sales', 'Website Traffic', 'Conversions', 'Awareness', 'WhatsApp Conversations'],
            'aiConfigured' => $this->ai->isConfigured(),
        ];
    }
}
