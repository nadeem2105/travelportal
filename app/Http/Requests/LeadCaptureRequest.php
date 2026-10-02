<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LeadCaptureRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // public capture endpoint
    }

    public function rules(): array
    {
        return [
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
            'source_slug' => 'nullable|string|max:60',
            'marketing_opt_in' => 'nullable|boolean',
            'whatsapp_opt_in' => 'nullable|boolean',
            // honeypot — must remain empty
            'company_website' => 'nullable|prohibited',
        ];
    }

    public function messages(): array
    {
        return [
            'company_website.prohibited' => 'Spam detected.',
        ];
    }
}
