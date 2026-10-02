<?php

namespace App\Http\Controllers\Admin;

use App\Events\Crm\StageChanged;
use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Models\CrmFollowUp;
use App\Models\CrmLead;
use App\Models\CrmQuotation;
use App\Models\Hotel;
use App\Models\LeadSource;
use App\Models\Package;
use App\Models\PipelineStage;
use App\Models\Vehicle;
use App\Services\ActivityLogger;
use App\Services\Crm\CrmActivityService;
use App\Services\Crm\LeadService;
use Illuminate\Http\Request;

class CrmController extends Controller
{
    /** Map of filter-bar sort keys to [column, direction]. */
    protected array $sortMap = [
        'recent' => ['created_at', 'desc'],
        'oldest' => ['created_at', 'asc'],
        'score_high' => ['score', 'desc'],
        'followup' => ['next_follow_up_at', 'asc'],
        'name' => ['name', 'asc'],
    ];

    public function index(Request $request)
    {
        $q = trim((string) $request->query('q'));
        [$sortCol, $sortDir] = $this->sortMap[$request->query('sort')] ?? $this->sortMap['recent'];

        $leads = CrmLead::query()
            ->with(['contact', 'leadSource', 'stage', 'assignee'])
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('name', 'like', "%{$q}%")
                ->orWhere('email', 'like', "%{$q}%")
                ->orWhere('phone', 'like', "%{$q}%")
                ->orWhere('lead_number', 'like', "%{$q}%")
                ->orWhere('destination', 'like', "%{$q}%")))
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->query('status')))
            ->when($request->filled('source_id'), fn ($query) => $query->where('source_id', $request->query('source_id')))
            ->when($request->filled('stage_id'), fn ($query) => $query->where('stage_id', $request->query('stage_id')))
            ->when($request->filled('assigned_to'), fn ($query) => $query->where('assigned_to', $request->query('assigned_to')))
            ->when($request->filled('priority'), fn ($query) => $query->where('priority', $request->query('priority')))
            ->orderBy($sortCol, $sortDir)
            ->paginate((int) $request->query('per_page', 15))
            ->withQueryString();

        $staff = Admin::where('status', 'active')->orderBy('name')->get();
        $sources = LeadSource::active()->orderBy('sort_order')->orderBy('name')->get();
        $stages = PipelineStage::with('pipeline')->orderBy('pipeline_id')->orderBy('sort_order')->get();

        return view('admin.crm.index', compact('leads', 'staff', 'sources', 'stages'));
    }

    public function storeLead(Request $request, LeadService $leadService)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:100',
            'phone' => 'required|string|max:20',
            'email' => 'nullable|email|max:120',
            'destination' => 'nullable|string|max:100',
            'product_type' => 'required|in:package,flight,hotel,cab,custom',
            'budget' => 'nullable|numeric|min:0',
            'travellers_count' => 'nullable|integer|min:1',
            'travel_date' => 'nullable|date',
            'source' => 'required|in:website,referral,phone,social,campaign',
            'assigned_to' => 'nullable|exists:admins,id',
            'notes' => 'nullable|string|max:1000',
        ]);

        $lead = $leadService->create($validated + ['performed_by' => auth('admin')->id()], $request);
        ActivityLogger::log('create', 'crm', "Created CRM Lead: {$lead->name} ({$lead->phone})");

        return back()->with('success', "Lead {$lead->lead_number} captured successfully.");
    }

    public function showLead(CrmLead $lead)
    {
        $lead->load([
            'assignee', 'contact', 'company', 'leadSource', 'stage', 'pipeline.stages',
            'followUps.staff', 'quotations', 'activities.performer', 'tasks.assignee',
        ]);
        $staff = Admin::where('status', 'active')->orderBy('name')->get();
        $packages = Package::where('status', 'active')->get();
        $hotels = Hotel::where('status', 'active')->orderBy('name')->get();
        $cabs = Vehicle::where('status', 'active')->with('type')->orderBy('name')->get();
        $stages = $lead->pipeline?->stages ?? collect();

        return view('admin.crm.show', compact('lead', 'staff', 'packages', 'hotels', 'cabs', 'stages'));
    }

    public function updateStatus(Request $request, CrmLead $lead)
    {
        $validated = $request->validate([
            'status' => 'required|in:new,contacted,quotation_sent,negotiating,converted,lost',
            'assigned_to' => 'nullable|exists:admins,id',
        ]);

        $lead->update($validated);
        ActivityLogger::log('update', 'crm', "Updated Lead {$lead->name} status to {$validated['status']}");

        return back()->with('success', 'Lead status updated.');
    }

    public function changeStage(Request $request, CrmLead $lead, CrmActivityService $activity)
    {
        $validated = $request->validate([
            'stage_id' => 'required|exists:pipeline_stages,id',
        ]);

        $stage = PipelineStage::findOrFail($validated['stage_id']);

        // Only allow stages that belong to the lead's own pipeline.
        if ($lead->pipeline_id && $stage->pipeline_id !== $lead->pipeline_id) {
            return back()->with('error', 'Selected stage does not belong to this lead\'s pipeline.');
        }

        if ((int) $lead->stage_id === (int) $stage->id) {
            return back()->with('error', 'Lead is already in that stage.');
        }

        $from = $lead->stage;
        $lead->update(['stage_id' => $stage->id]);

        $activity->forLead($lead, 'stage_changed', 'Stage changed', [
            'description' => ($from?->name ?? '—') . ' → ' . $stage->name,
            'data' => ['from_stage_id' => $from?->id, 'to_stage_id' => $stage->id],
            'performed_by' => auth('admin')->id(),
        ]);

        event(new StageChanged($lead->fresh(), $from, $stage));
        app(\App\Services\Crm\AutomationEngine::class)->dispatch('stage_changed', $lead->fresh());
        ActivityLogger::log('update', 'crm', "Lead {$lead->lead_number} moved to stage {$stage->name}");

        return back()->with('success', "Stage updated to {$stage->name}.");
    }

    public function storeNote(Request $request, CrmLead $lead, CrmActivityService $activity)
    {
        $validated = $request->validate([
            'note' => 'required|string|max:2000',
        ]);

        $activity->forLead($lead, 'note', 'Note added', [
            'description' => $validated['note'],
            'is_internal' => true,
            'performed_by' => auth('admin')->id(),
        ]);

        return back()->with('success', 'Internal note added to the timeline.');
    }

    public function storeFollowUp(Request $request, CrmLead $lead)
    {
        $validated = $request->validate([
            'note' => 'required|string|max:1000',
            'scheduled_at' => 'nullable|date',
        ]);

        CrmFollowUp::create([
            'lead_id' => $lead->id,
            'admin_id' => auth('admin')->id(),
            'note' => $validated['note'],
            'scheduled_at' => $validated['scheduled_at'] ?? null,
        ]);

        return back()->with('success', 'Follow-up note logged.');
    }

    public function storeQuotation(Request $request, CrmLead $lead)
    {
        $validated = $request->validate([
            'title' => 'required|string|max:150',
            'package_id' => 'nullable|exists:packages,id',
            'hotel_id' => 'nullable|exists:hotels,id',
            'hotels' => 'nullable|array',
            'hotels.*.hotel_id' => 'nullable|exists:hotels,id',
            'hotels.*.location' => 'nullable|string|max:150',
            'hotels.*.nights' => 'nullable|integer|min:0|max:60',
            'itinerary' => 'nullable|array',
            'itinerary.*.title' => 'nullable|string|max:200',
            'itinerary.*.description' => 'nullable|string|max:2000',
            'itinerary.*.stay' => 'nullable|string|max:200',
            'vehicle_id' => 'nullable|exists:vehicles,id',
            'pickup_location' => 'nullable|string|max:200',
            'dropoff_location' => 'nullable|string|max:200',
            'subtotal' => 'required|numeric|min:0',
            'tax_amount' => 'nullable|numeric|min:0',
            'discount_amount' => 'nullable|numeric|min:0',
            'currency' => 'nullable|string|size:3',
            'valid_until' => 'nullable|date|after:today',
            'travel_date' => 'nullable|date',
            'terms' => 'nullable|string',
            'cancellation_policy' => 'nullable|string',
            'notes' => 'nullable|string',
            'items' => 'nullable|array',
            'items.*.label' => 'required_with:items|string|max:200',
            'items.*.amount' => 'required_with:items|numeric',
            'payment_schedule' => 'nullable|array',
            'adults' => 'nullable|integer|min:0|max:99',
            'children' => 'nullable|integer|min:0|max:99',
            'infants' => 'nullable|integer|min:0|max:99',
            'rooms' => 'nullable|integer|min:0|max:99',
            'inclusions_raw' => 'nullable|string|max:4000',
            'exclusions_raw' => 'nullable|string|max:4000',
        ]);

        // Inclusions/exclusions: one item per line → array (null = fall back to package).
        $toList = fn (?string $raw) => collect(preg_split('/\r\n|\r|\n/', (string) $raw))
            ->map(fn ($l) => trim(ltrim($l, "-•*\t ")))->filter()->values()->all() ?: null;
        $inclusions = $toList($validated['inclusions_raw'] ?? null);
        $exclusions = $toList($validated['exclusions_raw'] ?? null);

        // Server-side total: prefer explicit line items when supplied, else the subtotal field.
        $itemsSum = collect($validated['items'] ?? [])->sum(fn ($i) => (float) $i['amount']);
        $subtotal = ! empty($validated['items']) ? $itemsSum : (float) $validated['subtotal'];
        $tax = (float) ($validated['tax_amount'] ?? 0);
        $discount = (float) ($validated['discount_amount'] ?? 0);
        $total = max(0, $subtotal + $tax - $discount);

        // Multiple hotel stays (one per location/night). Snapshot the hotel name +
        // city at save time so the quote stays stable if a hotel is later edited.
        // Blank rows (no hotel and no location) are dropped.
        $hotelStays = collect($validated['hotels'] ?? [])
            ->map(function ($row) {
                $hotelId = $row['hotel_id'] ?? null;
                $hotel = $hotelId ? Hotel::find($hotelId) : null;

                return array_filter([
                    'hotel_id' => $hotelId ? (int) $hotelId : null,
                    'hotel_name' => $hotel?->name,
                    'city' => $hotel?->city,
                    'location' => isset($row['location']) ? trim((string) $row['location']) : null,
                    'nights' => isset($row['nights']) && $row['nights'] !== '' ? (int) $row['nights'] : null,
                ], fn ($v) => $v !== null && $v !== '');
            })
            ->filter(fn ($e) => ! empty($e['hotel_id']) || ! empty($e['location']))
            ->values()
            ->all();

        // Day-by-day itinerary with the overnight stay per day. Day numbers are
        // re-sequenced from 1. Rows with no title/description/stay are dropped.
        $itinerary = collect($validated['itinerary'] ?? [])
            ->map(fn ($row) => [
                'title' => isset($row['title']) ? trim((string) $row['title']) : null,
                'description' => isset($row['description']) ? trim((string) $row['description']) : null,
                'stay' => isset($row['stay']) ? trim((string) $row['stay']) : null,
            ])
            ->filter(fn ($r) => $r['title'] || $r['description'] || $r['stay'])
            ->values()
            ->map(fn ($r, $i) => array_merge(['day' => $i + 1], $r))
            ->all();

        $quotation = CrmQuotation::create([
            'lead_id' => $lead->id,
            'contact_id' => $lead->contact_id,
            // quotation_number / uuid / public_token auto-generated by the model.
            'title' => $validated['title'],
            'package_id' => $validated['package_id'] ?? null,
            // Legacy single-hotel column points at the first stay for back-compat.
            'hotel_id' => $hotelStays[0]['hotel_id'] ?? ($validated['hotel_id'] ?? null),
            'hotels' => $hotelStays ?: null,
            'itinerary' => $itinerary ?: null,
            'vehicle_id' => $validated['vehicle_id'] ?? null,
            'pickup_location' => $validated['pickup_location'] ?? null,
            'dropoff_location' => $validated['dropoff_location'] ?? null,
            'items' => $validated['items'] ?? null,
            'payment_schedule' => $validated['payment_schedule'] ?? null,
            'terms' => $validated['terms'] ?? null,
            'cancellation_policy' => $validated['cancellation_policy'] ?? null,
            'notes' => $validated['notes'] ?? null,
            'subtotal' => $subtotal,
            'tax_amount' => $tax,
            'discount_amount' => $discount,
            'total_amount' => $total,
            'currency' => strtoupper($validated['currency'] ?? 'INR'),
            'valid_until' => $validated['valid_until'] ?? null,
            'travel_date' => $validated['travel_date'] ?? null,
            'status' => 'draft',
            'created_by' => auth('admin')->id(),
            // Traveller counts — fall back to the lead's trip details when omitted.
            'adults' => $validated['adults'] ?? $lead->adults ?? $lead->travellers_count,
            'children' => $validated['children'] ?? $lead->children,
            'infants' => $validated['infants'] ?? $lead->infants,
            'rooms' => $validated['rooms'] ?? $lead->rooms,
            'inclusions' => $inclusions,
            'exclusions' => $exclusions,
        ]);

        $lead->update(['status' => 'quotation_sent']);

        // Send email to lead if email is present
        $emailSent = false;
        if (! empty($lead->email)) {
            $mailConfig = app(\App\Services\MailConfigService::class);
            if ($mailConfig->isEnabled()) {
                $mailConfig->apply();
                try {
                    \Illuminate\Support\Facades\Mail::to($lead->email)->send(new \App\Mail\CrmQuotationMail($quotation));
                    $emailSent = true;

                    $quotation->forceFill(['status' => 'sent', 'sent_at' => now()])->save();

                    CrmFollowUp::create([
                        'lead_id' => $lead->id,
                        'admin_id' => auth('admin')->id(),
                        'note' => "Quotation {$quotation->quotation_number} dispatched to {$lead->email}",
                        'scheduled_at' => null,
                    ]);
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::error("Failed to email quotation {$quotation->quotation_number}: " . $e->getMessage());
                }
            }
        }

        // WhatsApp quotation (approved template) — sends the public quote link.
        // Body vars {{1}}=name {{2}}=quotation number {{3}}=total {{4}}=quote link
        $waSent = false;
        if (! empty($lead->phone)) {
            // Optionally attach the quotation PDF (needs a doc-header template).
            $document = null;
            if (config('services.whatsapp.attach_documents.quotation_sent')) {
                try {
                    $document = ['bytes' => app(\App\Services\PdfDocumentService::class)->quotation($quotation), 'filename' => "Quotation-{$quotation->quotation_number}.pdf"];
                } catch (\Throwable $e) {
                    \Illuminate\Support\Facades\Log::warning('Quotation PDF for WhatsApp failed: ' . $e->getMessage());
                }
            }

            $result = app(\App\Services\WhatsApp\WhatsAppService::class)->notifyEvent(
                'quotation_sent',
                $lead->phone,
                [$lead->name ?? 'there', $quotation->quotation_number, '₹' . number_format((float) $quotation->total_amount, 2), $quotation->publicUrl()],
                $lead->name,
                $document,
            );
            $waSent = (bool) ($result['success'] ?? false);
            if ($waSent && is_null($quotation->sent_at)) {
                $quotation->forceFill(['status' => 'sent', 'sent_at' => now()])->save();
            }
        }

        $channels = array_filter([$emailSent ? "emailed to {$lead->email}" : null, $waSent ? 'sent via WhatsApp' : null]);
        $msg = "Quotation {$quotation->quotation_number} generated" . ($channels ? ' and ' . implode(' & ', $channels) . '.' : '.');
        return back()->with('success', $msg);
    }

    public function sendQuotationEmail(Request $request, CrmQuotation $quotation)
    {
        $lead = $quotation->lead;
        if (! $lead || empty($lead->email)) {
            return back()->with('error', 'Lead does not have a valid email address.');
        }

        $mailConfig = app(\App\Services\MailConfigService::class);
        $mailConfig->apply();

        try {
            \Illuminate\Support\Facades\Mail::to($lead->email)->send(new \App\Mail\CrmQuotationMail($quotation));

            if (in_array($quotation->status, ['draft'], true) || is_null($quotation->sent_at)) {
                $quotation->forceFill([
                    'status' => $quotation->status === 'draft' ? 'sent' : $quotation->status,
                    'sent_at' => $quotation->sent_at ?? now(),
                ])->save();
            }

            CrmFollowUp::create([
                'lead_id' => $lead->id,
                'admin_id' => auth('admin')->id(),
                'note' => "Quotation {$quotation->quotation_number} resent to {$lead->email}",
                'scheduled_at' => null,
            ]);

            ActivityLogger::log('email', 'crm', "Resent Quotation {$quotation->quotation_number} to {$lead->email}");

            // Also (re)send over WhatsApp with the public quote link.
            $waSent = false;
            if (! empty($lead->phone)) {
                // Attach the quotation PDF when the template supports it.
                $document = null;
                if (config('services.whatsapp.attach_documents.quotation_sent')) {
                    try {
                        $document = [
                            'bytes' => app(\App\Services\PdfDocumentService::class)->quotation($quotation),
                            'filename' => "Quotation-{$quotation->quotation_number}.pdf",
                        ];
                    } catch (\Throwable $e) {
                        \Illuminate\Support\Facades\Log::warning('Quotation PDF for WhatsApp resend failed: ' . $e->getMessage());
                    }
                }

                $result = app(\App\Services\WhatsApp\WhatsAppService::class)->notifyEvent(
                    'quotation_sent',
                    $lead->phone,
                    [$lead->name ?? 'there', $quotation->quotation_number, '₹' . number_format((float) $quotation->total_amount, 2), $quotation->publicUrl()],
                    $lead->name,
                    $document,
                );
                $waSent = (bool) ($result['success'] ?? false);
            }

            $extra = $waSent ? ' and WhatsApp' : '';
            return back()->with('success', "Quotation {$quotation->quotation_number} successfully dispatched to {$lead->email}{$extra}.");
        } catch (\Throwable $e) {
            return back()->with('error', 'Failed to dispatch quotation email: ' . $e->getMessage());
        }
    }

    public function viewQuotationPdf(CrmQuotation $quotation)
    {
        $quotation->loadMissing(['lead.assignee', 'package.itineraries', 'package.destination']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.crm.quotation_pdf', [
            'quotation' => $quotation,
            'lead' => $quotation->lead,
            'package' => $quotation->package,
            'agent' => $quotation->lead?->assignee,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Quotation-{$quotation->quotation_number}.pdf");
    }

    public function downloadQuotationPdf(CrmQuotation $quotation)
    {
        $quotation->loadMissing(['lead.assignee', 'package.itineraries', 'package.destination']);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.crm.quotation_pdf', [
            'quotation' => $quotation,
            'lead' => $quotation->lead,
            'package' => $quotation->package,
            'agent' => $quotation->lead?->assignee,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("Quotation-{$quotation->quotation_number}.pdf");
    }

    public function convertQuotation(CrmQuotation $quotation, \App\Services\Crm\QuotationConversionService $converter)
    {
        try {
            $booking = $converter->convert($quotation, auth('admin')->id());
        } catch (\DomainException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            \Illuminate\Support\Facades\Log::error("Quotation conversion failed for {$quotation->quotation_number}: " . $e->getMessage());

            return back()->with('error', 'Could not convert this quotation to a booking. Please check the details and try again.');
        }

        $checkoutUrl = route('checkout.show', $booking->booking_reference);

        return redirect()
            ->route('admin.bookings.show', $booking)
            ->with('success', "Quotation {$quotation->quotation_number} converted to booking {$booking->booking_reference} (payment pending).")
            ->with('checkout_link', $checkoutUrl);
    }
}
