@extends('layouts.site')

@section('page')
<x-agent.shell agentNav="profile">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold">Profile &amp; KYC</h1>
        <p class="mt-1 text-sm text-ink-500">Manage your agency details, documents and password.</p>
    </div>

    {{-- Profile details --}}
    <div class="card p-6">
        <h2 class="mb-4 font-display text-lg font-bold">Agency Details</h2>
        <form action="{{ route('agent.profile.update') }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Agency name</label>
                    <input type="text" name="agency_name" class="input" value="{{ old('agency_name', $agent->agency_name) }}" required>
                </div>
                <div>
                    <label class="label">Contact person</label>
                    <input type="text" name="contact_person" class="input" value="{{ old('contact_person', $agent->contact_person) }}" required>
                </div>
                <div>
                    <label class="label">Email</label>
                    <input type="email" class="input bg-ink-50" value="{{ $agent->email }}" disabled>
                    <p class="mt-1 text-xs text-ink-400">Contact support to change your email.</p>
                </div>
                <div>
                    <label class="label">Phone</label>
                    <input type="text" name="phone" class="input" value="{{ old('phone', $agent->phone) }}" required>
                </div>
                <div>
                    <label class="label">City</label>
                    <input type="text" name="city" class="input" value="{{ old('city', $agent->city) }}">
                </div>
                <div>
                    <label class="label">State</label>
                    <input type="text" name="state" class="input" value="{{ old('state', $agent->state) }}">
                </div>
                <div>
                    <label class="label">Pincode</label>
                    <input type="text" name="pincode" class="input" value="{{ old('pincode', $agent->pincode) }}">
                </div>
                <div>
                    <label class="label">IATA code</label>
                    <input type="text" name="iata_code" class="input" value="{{ old('iata_code', $agent->iata_code) }}">
                </div>
                <div class="sm:col-span-2">
                    <label class="label">Address</label>
                    <textarea name="address" rows="2" class="input">{{ old('address', $agent->address) }}</textarea>
                </div>
                <div>
                    <label class="label">PAN number</label>
                    <input type="text" name="pan_number" class="input" value="{{ old('pan_number', $agent->pan_number) }}">
                </div>
                <div>
                    <label class="label">GST number</label>
                    <input type="text" name="gst_number" class="input" value="{{ old('gst_number', $agent->gst_number) }}">
                </div>
            </div>
            <button class="btn-primary">Save Changes</button>
        </form>
    </div>

    {{-- KYC documents --}}
    <div class="card mt-6 p-6">
        <h2 class="mb-4 font-display text-lg font-bold">Documents &amp; Branding</h2>
        <form action="{{ route('agent.profile.documents') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Agency Logo</label>
                    @if ($agent->logoUrl())
                        <img src="{{ $agent->logoUrl() }}" class="mb-2 h-16 w-16 rounded-lg object-cover" alt="logo">
                    @endif
                    <input type="file" name="logo" accept="image/*" class="input">
                </div>
                <div>
                    <label class="label">PAN Document</label>
                    @if ($agent->pan_document)
                        <a href="{{ Storage::disk('public')->url($agent->pan_document) }}" target="_blank" class="mb-2 block text-xs font-semibold text-brand-600">View current</a>
                    @endif
                    <input type="file" name="pan_document" accept="image/*,application/pdf" class="input">
                </div>
                <div>
                    <label class="label">GST Document</label>
                    @if ($agent->gst_document)
                        <a href="{{ Storage::disk('public')->url($agent->gst_document) }}" target="_blank" class="mb-2 block text-xs font-semibold text-brand-600">View current</a>
                    @endif
                    <input type="file" name="gst_document" accept="image/*,application/pdf" class="input">
                </div>
                <div>
                    <label class="label">Business License</label>
                    @if ($agent->business_license)
                        <a href="{{ Storage::disk('public')->url($agent->business_license) }}" target="_blank" class="mb-2 block text-xs font-semibold text-brand-600">View current</a>
                    @endif
                    <input type="file" name="business_license" accept="image/*,application/pdf" class="input">
                </div>
            </div>
            <button class="btn-primary">Upload Documents</button>
        </form>
    </div>

    {{-- Change password --}}
    <div class="card mt-6 p-6">
        <h2 class="mb-4 font-display text-lg font-bold">Change Password</h2>
        <form action="{{ route('agent.profile.password') }}" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="grid gap-4 sm:grid-cols-3">
                <div>
                    <label class="label">Current password</label>
                    <input type="password" name="current_password" class="input" required>
                </div>
                <div>
                    <label class="label">New password</label>
                    <input type="password" name="password" class="input" required>
                </div>
                <div>
                    <label class="label">Confirm new password</label>
                    <input type="password" name="password_confirmation" class="input" required>
                </div>
            </div>
            <button class="btn-primary">Update Password</button>
        </form>
    </div>
</x-agent.shell>
@endsection
