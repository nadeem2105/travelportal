<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Api\Concerns\ApiResponse;
use App\Http\Controllers\Controller;
use App\Services\Crm\LeadService;
use Illuminate\Http\Request;

/**
 * Lead / enquiry intake from the mobile app. Funnels every "get a quote",
 * "request callback" or "contact us" submission into the CRM through the same
 * LeadService the website uses (contact dedup, attribution, assignment,
 * scoring, automation), so app leads land in the exact same pipeline.
 *
 * Works for both guests and logged-in users. When authenticated we prefill the
 * contact details from the account but still let the client override them.
 */
class LeadController extends Controller
{
    use ApiResponse;

    public function store(Request $request, LeadService $leads)
    {
        $data = $request->validate([
            'name' => 'required|string|max:120',
            'phone' => 'required|string|max:30',
            'email' => 'nullable|email|max:150',
            'destination' => 'nullable|string|max:120',
            'product_type' => 'nullable|in:package,flight,hotel,cab,custom',
            'service_type' => 'nullable|string|max:60',
            'travel_date' => 'nullable|date',
            'travel_start_date' => 'nullable|date',
            'travel_end_date' => 'nullable|date|after_or_equal:travel_start_date',
            'adults' => 'nullable|integer|min:0|max:50',
            'children' => 'nullable|integer|min:0|max:50',
            'infants' => 'nullable|integer|min:0|max:20',
            'rooms' => 'nullable|integer|min:0|max:30',
            'budget' => 'nullable|numeric|min:0',
            'budget_min' => 'nullable|numeric|min:0',
            'budget_max' => 'nullable|numeric|min:0',
            'message' => 'nullable|string|max:2000',
            'marketing_opt_in' => 'nullable|boolean',
            'whatsapp_opt_in' => 'nullable|boolean',
        ]);

        // Attribution: this enquiry came from the mobile app.
        $data['notes'] = $data['message'] ?? null;
        unset($data['message']);
        $data['source'] = 'website';
        $data['source_slug'] = $request->input('source_slug', 'mobile-app');
        $data['source_detail'] = 'mobile-app';

        // If the caller is authenticated, tie the lead to their account.
        if ($user = $request->user()) {
            $data['email'] = $data['email'] ?: $user->email;
            $data['name'] = $data['name'] ?: $user->name;
            $data['phone'] = $data['phone'] ?: $user->phone;
        }

        try {
            $lead = $leads->create($data, $request);
        } catch (\Throwable $e) {
            report($e);

            return $this->fail('We could not submit your enquiry. Please try again.', 500);
        }

        return $this->ok(
            ['lead_number' => $lead->lead_number],
            'Thank you! Our travel expert will contact you shortly.',
            201
        );
    }
}
