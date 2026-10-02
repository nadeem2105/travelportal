<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactGroup;
use App\Models\WhatsAppCampaign;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\CampaignService;
use Illuminate\Http\Request;

class WhatsAppCampaignController extends Controller
{
    public function __construct(private CampaignService $campaigns)
    {
    }

    public function index()
    {
        $campaigns = WhatsAppCampaign::with('group')->latest()->paginate(20);

        return view('admin.crm.campaigns.index', compact('campaigns'));
    }

    public function create()
    {
        return view('admin.crm.campaigns.form', [
            'campaign' => new WhatsAppCampaign(['template_language' => 'en_US']),
            'groups' => ContactGroup::orderBy('name')->get(),
            'templates' => WhatsAppTemplate::approved()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request)
    {
        $campaign = WhatsAppCampaign::create($this->validated($request) + [
            'status' => 'draft',
            'created_by' => auth('admin')->id(),
        ]);

        return redirect()->route('admin.whatsapp-campaigns.show', $campaign)->with('success', 'Campaign saved as draft.');
    }

    public function show(WhatsAppCampaign $whatsappCampaign)
    {
        $whatsappCampaign->load('group');
        $this->campaigns->refreshProgress($whatsappCampaign);
        $recipients = $whatsappCampaign->recipients()->with('contact')->latest()->paginate(50);

        // Preview reachable audience size for a not-yet-sent campaign.
        $estimated = $whatsappCampaign->group
            ? $whatsappCampaign->group->resolveContacts(whatsappReachableOnly: true)->filter(fn ($c) => (bool) $c->whatsapp_opt_in)->count()
            : 0;

        return view('admin.crm.campaigns.show', compact('whatsappCampaign', 'recipients', 'estimated'));
    }

    public function sendNow(WhatsAppCampaign $whatsappCampaign)
    {
        if (! $whatsappCampaign->isEditable()) {
            return back()->with('error', 'This campaign has already been dispatched.');
        }
        $this->campaigns->dispatchNow($whatsappCampaign);

        return back()->with('success', 'Campaign dispatch started. Progress will update as messages go out.');
    }

    public function schedule(Request $request, WhatsAppCampaign $whatsappCampaign)
    {
        $validated = $request->validate(['scheduled_at' => 'required|date|after:now']);
        if (! $whatsappCampaign->isEditable()) {
            return back()->with('error', 'This campaign has already been dispatched.');
        }
        $this->campaigns->schedule($whatsappCampaign, \Illuminate\Support\Carbon::parse($validated['scheduled_at']));

        return back()->with('success', 'Campaign scheduled.');
    }

    public function cancel(WhatsAppCampaign $whatsappCampaign)
    {
        if (in_array($whatsappCampaign->status, ['completed', 'cancelled'], true)) {
            return back()->with('error', 'Campaign cannot be cancelled.');
        }
        $whatsappCampaign->update(['status' => 'cancelled']);

        return back()->with('success', 'Campaign cancelled. Pending messages will not be sent.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => 'required|string|max:150',
            'contact_group_id' => 'required|exists:contact_groups,id',
            'template_name' => 'required|string|max:150',
            'template_language' => 'nullable|string|max:12',
            'template_params' => 'nullable|array',
            'template_params.*' => 'nullable|string|max:500',
        ]);
    }
}
