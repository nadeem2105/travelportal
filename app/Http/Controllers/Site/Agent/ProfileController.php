<?php

namespace App\Http\Controllers\Site\Agent;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rules\Password;

class ProfileController extends Controller
{
    public function edit()
    {
        return view('agent.profile', [
            'seo' => ['title' => 'Profile & KYC'],
            'agent' => auth('agent')->user(),
        ]);
    }

    public function update(Request $request)
    {
        $agent = auth('agent')->user();

        $validated = $request->validate([
            'agency_name' => 'required|string|max:150',
            'contact_person' => 'required|string|max:120',
            'phone' => 'required|string|max:25',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|max:60',
            'pincode' => 'nullable|string|max:12',
            'address' => 'nullable|string|max:500',
            'pan_number' => 'nullable|string|max:20',
            'gst_number' => 'nullable|string|max:20',
            'iata_code' => 'nullable|string|max:20',
        ]);

        $agent->update($validated);

        return back()->with('success', 'Profile updated successfully.');
    }

    public function uploadDocuments(Request $request)
    {
        $agent = auth('agent')->user();

        $request->validate([
            'logo' => 'nullable|image|mimes:jpeg,jpg,png,webp|max:2048',
            'pan_document' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:4096',
            'gst_document' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:4096',
            'business_license' => 'nullable|file|mimes:jpeg,jpg,png,pdf|max:4096',
        ]);

        $updates = [];

        foreach ([
            'logo' => 'logo_path',
            'pan_document' => 'pan_document',
            'gst_document' => 'gst_document',
            'business_license' => 'business_license',
        ] as $field => $column) {
            if ($request->hasFile($field)) {
                // Remove previous file if present.
                if (! empty($agent->{$column})) {
                    Storage::disk('public')->delete($agent->{$column});
                }
                $updates[$column] = $request->file($field)->store('agents/'.$agent->id, 'public');
            }
        }

        if ($updates) {
            $agent->update($updates);
        }

        return back()->with('success', 'Documents uploaded successfully.');
    }

    public function updatePassword(Request $request)
    {
        $agent = auth('agent')->user();

        $validated = $request->validate([
            'current_password' => 'required|string',
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        if (! Hash::check($validated['current_password'], $agent->password)) {
            return back()->withErrors(['current_password' => 'The current password is incorrect.']);
        }

        $agent->update(['password' => $validated['password']]);

        return back()->with('success', 'Password changed successfully.');
    }
}
