<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Http\Requests\LeadCaptureRequest;
use App\Services\Crm\LeadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;

/**
 * Public website lead capture — funnels every inquiry form (contact, package,
 * hotel, flight, cab, custom, get-quote, callback, WhatsApp) into the CRM via
 * LeadService, which handles contact dedup, attribution, assignment and scoring.
 */
class LeadCaptureController extends Controller
{
    public function store(LeadCaptureRequest $request, LeadService $leads): RedirectResponse|JsonResponse
    {
        $data = $request->safe()->except(['company_website']);
        $data['notes'] = $request->input('message');
        $data['source'] = 'website';
        $data['source_slug'] = $request->input('source_slug', 'website');
        $data['message'] = null;
        unset($data['message']);

        try {
            $lead = $leads->create($data, $request);
        } catch (\Throwable $e) {
            report($e);

            if ($request->expectsJson()) {
                return response()->json(['ok' => false, 'message' => 'We could not submit your inquiry. Please try again.'], 500);
            }

            return back()->with('error', 'We could not submit your inquiry. Please try again.');
        }

        if ($request->expectsJson()) {
            return response()->json([
                'ok' => true,
                'message' => 'Thank you! Our travel expert will contact you shortly.',
                'lead_number' => $lead->lead_number,
            ]);
        }

        return back()->with('success', 'Thank you! Our travel expert will contact you shortly.');
    }
}
