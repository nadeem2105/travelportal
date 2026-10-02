<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\MarketingAccount;
use App\Models\MarketingCampaign;
use App\Models\Package;
use App\Services\Marketing\AdApiUnavailableException;
use App\Services\Marketing\CampaignSafetyService;
use App\Services\Marketing\CampaignService;
use Illuminate\Http\Request;

/**
 * Manual campaign builder + lifecycle. Everything here produces a local DRAFT;
 * pushing to a provider (publish) creates the campaign PAUSED, and activation is
 * a separate, permission-gated action. All provider writes may throw
 * AdApiUnavailableException, which we surface rather than fake.
 */
class MarketingCampaignController extends Controller
{
    public function __construct(
        private CampaignService $campaigns,
        private CampaignSafetyService $safety,
    ) {
    }

    public function index(Request $request)
    {
        $campaigns = MarketingCampaign::query()
            ->with('account')
            ->when($request->filled('provider'), fn ($q) => $q->where('provider', $request->query('provider')))
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->query('status')))
            ->when($request->filled('q'), fn ($q) => $q->where('name', 'like', '%' . $request->query('q') . '%'))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('admin.marketing.campaigns.index', compact('campaigns'));
    }

    public function create(Request $request)
    {
        return view('admin.marketing.campaigns.create', $this->formData($request));
    }

    /** Preload a draft from an existing package. */
    public function createFromProduct(Request $request, Package $package)
    {
        $data = $this->formData($request);
        $data['prefill'] = [
            'name' => $this->campaigns->buildName(['META', strtoupper($package->destination->name ?? 'TRIP'), 'PACKAGE', 'LEADS', now()->format('M-Y')]),
            'product_type' => 'package',
            'product_id' => $package->id,
            'destination' => $package->destination->name ?? null,
            'landing_page' => url('/packages/' . $package->slug),
            'objective' => 'Leads',
        ];

        return view('admin.marketing.campaigns.create', $data);
    }

    public function store(Request $request)
    {
        $validated = $this->validateCampaign($request);
        $validated['meta'] = $this->buildMeta($request);
        $campaign = $this->campaigns->createDraft($validated, auth('admin')->id());

        return redirect()->route('admin.marketing.campaigns.show', $campaign)
            ->with('success', 'Campaign draft saved. Review, then publish (it will be created paused).');
    }

    /**
     * Build the ad-set + creative spec (stored on campaign->meta) that publish
     * uses to create the full Meta tree. Handles the uploaded image if present.
     */
    private function buildMeta(Request $request): array
    {
        $request->validate([
            'age_min' => 'nullable|integer|min:13|max:65',
            'age_max' => 'nullable|integer|min:13|max:65',
            'genders' => 'nullable|array',
            'genders.*' => 'in:1,2',
            'countries' => 'nullable|string|max:200', // comma-separated ISO codes
            'interests' => 'nullable|string|max:500',  // comma-separated keywords
            'optimization_goal' => 'nullable|string|max:40',
            'billing_event' => 'nullable|string|max:40',
            'page_id' => 'nullable|string|max:40',
            'primary_text' => 'nullable|string|max:2000',
            'headline' => 'nullable|string|max:255',
            'description' => 'nullable|string|max:255',
            'cta' => 'nullable|string|max:40',
            'creative_type' => 'nullable|in:image,video',
            'creative_image' => 'nullable|image|max:5120', // 5MB (also used as video thumbnail)
            'package_image_url' => 'nullable|url|max:500',
            'creative_video' => 'nullable|file|mimetypes:video/mp4,video/quicktime|max:153600', // 150MB
        ]);

        $split = fn ($s) => collect(explode(',', (string) $s))->map(fn ($v) => trim($v))->filter()->values()->all();

        $meta = ['adset' => [], 'creative' => []];

        $meta['adset'] = array_filter([
            'age_min' => $request->integer('age_min') ?: 18,
            'age_max' => $request->integer('age_max') ?: 65,
            'genders' => $request->input('genders', []),
            'countries' => $split($request->input('countries')) ?: ['IN'],
            'interests' => $split($request->input('interests')),
            'optimization_goal' => $request->input('optimization_goal'),
            'billing_event' => $request->input('billing_event', 'IMPRESSIONS'),
        ]);

        // Store uploaded media on the public disk; keep paths for publish time.
        $imagePath = $request->hasFile('creative_image')
            ? $request->file('creative_image')->store('marketing/creatives', 'public')
            : null;
        $videoPath = $request->hasFile('creative_video')
            ? $request->file('creative_video')->store('marketing/creatives', 'public')
            : null;

        $meta['creative'] = array_filter([
            'type' => $request->input('creative_type', 'image'),
            'page_id' => $request->input('page_id'),
            'message' => $request->input('primary_text'),
            'headline' => $request->input('headline'),
            'description' => $request->input('description'),
            'cta' => $request->input('cta', 'LEARN_MORE'),
            'link' => $request->input('landing_page'),
            'image_path' => $imagePath,           // image ad, or video thumbnail
            'image_url' => $request->input('package_image_url'),
            'video_path' => $videoPath,           // video ad
        ]);

        return $meta;
    }

    public function show(MarketingCampaign $campaign)
    {
        $campaign->load('account', 'groups', 'ads', 'changes.changedBy');
        $issues = $this->safety->prePublishIssues($campaign);
        $trackingUrl = $this->campaigns->trackingUrl($campaign);

        return view('admin.marketing.campaigns.show', compact('campaign', 'issues', 'trackingUrl'));
    }

    public function publish(MarketingCampaign $campaign)
    {
        try {
            $this->campaigns->publish($campaign, auth('admin')->id());

            return back()->with('success', 'Campaign published to the provider in PAUSED state. Activate it explicitly to start spending.');
        } catch (AdApiUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function activate(MarketingCampaign $campaign)
    {
        try {
            $this->campaigns->activate($campaign, auth('admin')->id());

            return back()->with('success', 'Campaign activated — it is now live and may spend budget.');
        } catch (AdApiUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    public function pause(MarketingCampaign $campaign)
    {
        try {
            $this->campaigns->pause($campaign, auth('admin')->id());

            return back()->with('success', 'Campaign paused.');
        } catch (AdApiUnavailableException $e) {
            return back()->with('error', $e->getMessage());
        }
    }

    // ---- helpers ------------------------------------------------------------

    private function formData(Request $request): array
    {
        return [
            'accounts' => MarketingAccount::where('is_active', true)->get(),
            'packages' => Package::orderBy('name')->limit(200)->get(['id', 'name', 'slug']),
            'objectives' => ['Leads', 'Sales', 'Website Traffic', 'Conversions', 'Awareness', 'WhatsApp Conversations'],
            'prefill' => [],
        ];
    }

    private function validateCampaign(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:180',
            'provider' => 'required|in:google_ads,meta_ads',
            'account_id' => 'nullable|exists:marketing_accounts,id',
            'objective' => 'nullable|string|max:40',
            'product_type' => 'nullable|in:package,hotel,flight,cab,custom',
            'product_id' => 'nullable|integer',
            'destination' => 'nullable|string|max:120',
            'landing_page' => 'nullable|url|max:500',
            'budget_type' => 'required|in:daily,lifetime',
            'daily_budget' => 'nullable|numeric|min:0',
            'lifetime_budget' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'bidding_strategy' => 'nullable|string|max:40',
            'start_at' => 'nullable|date',
            'end_at' => 'nullable|date|after_or_equal:start_at',
            'target_cpl' => 'nullable|numeric|min:0',
            'target_roas' => 'nullable|numeric|min:0',
            'target_leads' => 'nullable|integer|min:0',
        ]);
    }

}
