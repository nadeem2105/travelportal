<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\CrmQuotation;
use App\Services\Crm\CrmActivityService;
use Illuminate\Http\Request;

/**
 * Public, token-authenticated quotation view. Customers open a quote via a
 * non-guessable 48-char token (no auth required) to view, accept, or reject it.
 * No admin/internal data is exposed here.
 */
class QuotationController extends Controller
{
    public function __construct(private CrmActivityService $activity)
    {
    }

    protected function resolve(string $token): CrmQuotation
    {
        return CrmQuotation::where('public_token', $token)
            ->with(['package.destination', 'package.itineraries', 'lead.assignee', 'contact'])
            ->firstOrFail();
    }

    public function show(string $token)
    {
        $quotation = $this->resolve($token);

        // Record first view (once) + advance status from sent -> viewed.
        if (is_null($quotation->viewed_at)) {
            $quotation->forceFill(['viewed_at' => now()]);
            if ($quotation->status === 'sent') {
                $quotation->status = 'viewed';
            }
            $quotation->save();

            if ($quotation->lead) {
                $this->activity->forLead($quotation->lead, 'quotation_viewed',
                    "Quotation {$quotation->quotation_number} viewed by customer",
                    ['performed_by' => null]);
            }
        }

        return view('site.quotation.show', [
            'quotation' => $quotation,
            'package' => $quotation->package,
            'expired' => $quotation->isExpired(),
            'seo' => ['title' => "Quotation {$quotation->quotation_number}", 'description' => ''],
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $quotation = $this->resolve($token);

        if ($quotation->isExpired()) {
            return back()->with('error', 'This quotation has expired. Please contact us for an updated quote.');
        }

        if (in_array($quotation->status, ['accepted', 'converted'], true)) {
            return back()->with('info', 'This quotation has already been accepted.');
        }

        $quotation->forceFill([
            'status' => 'accepted',
            'accepted_at' => now(),
        ])->save();

        if ($quotation->lead) {
            $this->activity->forLead($quotation->lead, 'quotation_accepted',
                "Quotation {$quotation->quotation_number} accepted by customer",
                ['performed_by' => null]);
            app(\App\Services\Crm\AutomationEngine::class)->dispatch('quotation_accepted', $quotation->lead);
        }

        return back()->with('success', 'Thank you! Your quotation has been accepted. Our team will reach out to finalise your booking.');
    }

    public function reject(Request $request, string $token)
    {
        $quotation = $this->resolve($token);

        if (in_array($quotation->status, ['accepted', 'converted'], true)) {
            return back()->with('info', 'This quotation has already been accepted and cannot be declined.');
        }

        $reason = trim((string) $request->input('reason', ''));

        $quotation->forceFill([
            'status' => 'rejected',
            'rejected_at' => now(),
            'notes' => $reason !== ''
                ? trim(($quotation->notes ? $quotation->notes . "\n" : '') . 'Customer decline reason: ' . $reason)
                : $quotation->notes,
        ])->save();

        if ($quotation->lead) {
            $this->activity->forLead($quotation->lead, 'quotation_rejected',
                "Quotation {$quotation->quotation_number} declined by customer" . ($reason !== '' ? ": {$reason}" : ''),
                ['performed_by' => null]);
            app(\App\Services\Crm\AutomationEngine::class)->dispatch('quotation_rejected', $quotation->lead);
        }

        return back()->with('info', 'Your response has been recorded. Thank you for letting us know.');
    }

    public function pdf(string $token)
    {
        $quotation = $this->resolve($token);

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('admin.crm.quotation_pdf', [
            'quotation' => $quotation,
            'lead' => $quotation->lead,
            'package' => $quotation->package,
            'agent' => $quotation->lead?->assignee,
        ])->setPaper('a4', 'portrait');

        return $pdf->stream("Quotation-{$quotation->quotation_number}.pdf");
    }
}
