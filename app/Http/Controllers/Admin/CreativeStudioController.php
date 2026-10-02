<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CreativeBrandKit;
use App\Models\CreativeTemplate;
use App\Models\Destination;
use App\Models\Hotel;
use App\Models\MarketingCampaign;
use App\Models\MarketingCreative;
use App\Models\Package;
use App\Services\ActivityLogger;
use App\Services\Marketing\Ai\AiImageProviderManager;
use App\Services\Marketing\Creative\CreativeCopyService;
use App\Services\Marketing\Creative\CreativeFormats;
use App\Services\Marketing\Creative\CreativeStudioService;
use App\Jobs\GenerateCreativeJob;
use Illuminate\Http\Request;

/**
 * Ad Creative Studio — turns a portal package/hotel/destination into a
 * ready-to-publish ad creative (real business data + AI copy + AI/portal image
 * + brand overlay). Gated by marketing.assets / marketing.ai permissions. Never
 * invents facts; heavy generation runs on the queue.
 */
class CreativeStudioController extends Controller
{
    public function __construct(
        private CreativeStudioService $studio,
        private CreativeCopyService $copy,
        private AiImageProviderManager $imageProvider,
    ) {
    }

    /** Dashboard: KPIs, recent campaigns, recent creatives, quick actions. */
    public function index()
    {
        $stats = [
            'campaigns' => MarketingCampaign::count(),
            'active_campaigns' => MarketingCampaign::where('status', 'active')->count(),
            'creatives' => MarketingCreative::count(),
            'drafts' => MarketingCreative::where('approval_status', 'draft')->count(),
            'published' => MarketingCreative::where('approval_status', 'published')->count(),
        ];

        $recentCampaigns = MarketingCampaign::latest()->limit(6)->get();
        $recentCreatives = MarketingCreative::with('brandKit')->latest()->limit(8)->get();

        return view('admin.marketing.studio.index', [
            'pageTitle' => 'Ad Creative Studio',
            'stats' => $stats,
            'recentCampaigns' => $recentCampaigns,
            'recentCreatives' => $recentCreatives,
            'imageReady' => $this->imageProvider->isConfigured(),
            'copyReady' => $this->copy->isConfigured(),
        ]);
    }

    /** Guided create wizard. Optional prefilled source via query. */
    public function create(Request $request)
    {
        $prefill = [
            'product_type' => $request->query('product_type'),
            'product_id' => $request->query('product_id'),
        ];

        return view('admin.marketing.studio.create', [
            'pageTitle' => 'Create Creative',
            'formats' => CreativeFormats::FORMATS,
            'platforms' => CreativeFormats::PLATFORMS,
            'objectives' => CreativeFormats::OBJECTIVES,
            'audiences' => CreativeFormats::AUDIENCES,
            'styles' => CreativeFormats::STYLES,
            'languages' => CreativeFormats::LANGUAGES,
            'focuses' => CreativeFormats::VARIATION_FOCUS,
            'packages' => Package::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'hotels' => Hotel::where('status', 'active')->orderBy('name')->get(['id', 'name']),
            'destinations' => Destination::orderBy('name')->get(['id', 'name']),
            'brandKits' => CreativeBrandKit::where('status', 'active')->get(),
            'templates' => CreativeTemplate::active()->get(),
            'prefill' => $prefill,
            'imageReady' => $this->imageProvider->isConfigured(),
            'copyReady' => $this->copy->isConfigured(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'product_type' => 'required|in:package,hotel,destination,custom',
            'product_id' => 'nullable|integer',
            'objective' => 'required|in:' . implode(',', array_keys(CreativeFormats::OBJECTIVES)),
            'audience' => 'nullable|in:' . implode(',', array_keys(CreativeFormats::AUDIENCES)),
            'audience_detail' => 'nullable|string|max:300',
            'format' => 'required|in:' . implode(',', array_keys(CreativeFormats::FORMATS)),
            'language' => 'nullable|in:' . implode(',', array_keys(CreativeFormats::LANGUAGES)),
            'style' => 'nullable|in:' . implode(',', array_keys(CreativeFormats::STYLES)),
            'image_source' => 'nullable|in:portal,ai,combination',
            'brand_kit_id' => 'nullable|integer|exists:creative_brand_kits,id',
            'template_id' => 'nullable|integer|exists:creative_templates,id',
            'variation_focus' => 'nullable|in:' . implode(',', array_keys(CreativeFormats::VARIATION_FOCUS)),
            'name' => 'nullable|string|max:150',
            'generate_all_formats' => 'nullable|boolean',
        ]);

        $creative = $this->studio->create($data, auth('admin')->id());
        ActivityLogger::log('create', 'creative_studio', 'Creative created: ' . $creative->name, ['id' => $creative->id]);

        // Optionally fan out to sibling formats (same content, other placements).
        if ($request->boolean('generate_all_formats')) {
            $this->fanOutFormats($creative, $data);
        }

        return redirect()->route('admin.studio.show', $creative)
            ->with('success', 'Creative queued for generation. It will be ready in a few moments.');
    }

    public function show(MarketingCreative $creative)
    {
        $creative->load(['brandKit', 'template', 'asset', 'approver']);
        $siblings = $creative->variation_group
            ? MarketingCreative::where('variation_group', $creative->variation_group)->where('id', '!=', $creative->id)->get()
            : collect();

        return view('admin.marketing.studio.show', [
            'pageTitle' => $creative->name,
            'creative' => $creative,
            'siblings' => $siblings,
            'focuses' => CreativeFormats::VARIATION_FOCUS,
        ]);
    }

    /** Poll endpoint for generation progress (JSON). */
    public function status(MarketingCreative $creative)
    {
        return response()->json([
            'generation_status' => $creative->generation_status,
            'preview' => $creative->previewUrl(),
            'warnings' => $creative->warnings ?? [],
            'error' => $creative->generation_error,
        ]);
    }

    /** Re-run generation (e.g. after a failure or a price change). */
    public function regenerate(MarketingCreative $creative)
    {
        $creative->update(['generation_status' => 'queued', 'generation_error' => null]);
        GenerateCreativeJob::dispatch($creative->id);
        ActivityLogger::log('generate', 'creative_studio', 'Creative regenerated: ' . $creative->name, ['id' => $creative->id]);

        return back()->with('success', 'Re-generating this creative…');
    }

    /** Generate sibling copy/visual variations by focus. */
    public function variations(Request $request, MarketingCreative $creative)
    {
        $data = $request->validate([
            'focuses' => 'required|array|min:1',
            'focuses.*' => 'in:' . implode(',', array_keys(CreativeFormats::VARIATION_FOCUS)),
        ]);

        $created = $this->studio->generateVariations($creative, $data['focuses'], auth('admin')->id());
        ActivityLogger::log('generate', 'creative_studio', 'Generated ' . count($created) . ' variations for ' . $creative->name, ['base' => $creative->id]);

        return back()->with('success', count($created) . ' variation(s) queued for generation.');
    }

    /** Approval workflow. */
    public function submit(MarketingCreative $creative)
    {
        $creative->update(['approval_status' => 'pending']);
        ActivityLogger::log('update', 'creative_studio', 'Creative submitted for approval: ' . $creative->name, ['id' => $creative->id]);

        return back()->with('success', 'Submitted for approval.');
    }

    public function approve(MarketingCreative $creative)
    {
        $creative->update(['approval_status' => 'approved', 'approved_by' => auth('admin')->id(), 'approval_note' => null]);
        ActivityLogger::log('approve', 'creative_studio', 'Creative approved: ' . $creative->name, ['id' => $creative->id]);

        return back()->with('success', 'Creative approved.');
    }

    public function reject(Request $request, MarketingCreative $creative)
    {
        $data = $request->validate(['note' => 'nullable|string|max:500']);
        $creative->update(['approval_status' => 'rejected', 'approval_note' => $data['note'] ?? null]);
        ActivityLogger::log('reject', 'creative_studio', 'Creative rejected: ' . $creative->name, ['id' => $creative->id]);

        return back()->with('success', 'Creative rejected.');
    }

    public function publish(MarketingCreative $creative)
    {
        if ($creative->generation_status !== 'completed') {
            return back()->with('error', 'Creative must finish generating before publishing.');
        }
        $creative->update(['approval_status' => 'published']);
        ActivityLogger::log('publish', 'creative_studio', 'Creative published: ' . $creative->name, ['id' => $creative->id]);

        return back()->with('success', 'Creative marked as published.');
    }

    /** Duplicate as a new editable draft (version-safe). */
    public function duplicate(MarketingCreative $creative)
    {
        $copy = $creative->replicate(['render_path', 'thumb_path', 'approved_by']);
        $copy->name = $creative->name . ' (copy)';
        $copy->approval_status = 'draft';
        $copy->generation_status = 'queued';
        $copy->parent_id = $creative->id;
        $copy->version = $creative->version + 1;
        $copy->created_by = auth('admin')->id();
        $copy->save();

        GenerateCreativeJob::dispatch($copy->id);

        return redirect()->route('admin.studio.show', $copy)->with('success', 'Duplicated. Re-generating the copy…');
    }

    public function destroy(MarketingCreative $creative)
    {
        if ($creative->approval_status === 'published') {
            return back()->with('error', 'Published creatives cannot be deleted. Archive it instead.');
        }
        $creative->delete();
        ActivityLogger::log('delete', 'creative_studio', 'Creative deleted: ' . $creative->name, ['id' => $creative->id]);

        return redirect()->route('admin.studio.index')->with('success', 'Creative deleted.');
    }

    /** Download the rendered creative. */
    public function download(MarketingCreative $creative)
    {
        abort_unless($creative->render_path, 404);
        $disk = config('creative.disk', 'public');

        return \Illuminate\Support\Facades\Storage::disk($disk)->download(
            $creative->render_path,
            \Illuminate\Support\Str::slug($creative->name) . '.png'
        );
    }

    private function fanOutFormats(MarketingCreative $base, array $data): void
    {
        $targets = ['ig_1x1', 'ig_9x16', 'fb_4x5', 'wa_9x16'];
        foreach ($targets as $format) {
            if ($format === $base->format) {
                continue;
            }
            $this->studio->create(array_merge($data, [
                'format' => $format,
                'variation_group' => $base->variation_group,
                'name' => ($data['name'] ?? $base->product_type) . ' — ' . CreativeFormats::label($format),
            ]), auth('admin')->id());
        }
    }
}
