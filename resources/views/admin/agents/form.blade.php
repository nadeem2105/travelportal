@extends('layouts.admin')
@section('pageTitle', 'Register B2B Agent')

@section('content')
    <div class="max-w-2xl">
        <a href="{{ route('admin.agents.index') }}" class="text-xs text-brand-600 hover:underline">← Back to Agents</a>
        <h1 class="mt-2 font-display text-xl font-bold">Register B2B Travel Agent</h1>

        <form action="{{ route('admin.agents.store') }}" method="POST" class="admin-card mt-4 space-y-4">
            @csrf
            <div>
                <label class="label">Agency Name *</label>
                <input type="text" name="agency_name" class="input" required value="{{ old('agency_name') }}" placeholder="e.g. Kashmir Horizon Travels">
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Contact Person *</label>
                    <input type="text" name="contact_person" class="input" required value="{{ old('contact_person') }}">
                </div>
                <div>
                    <label class="label">Official Email *</label>
                    <input type="email" name="email" class="input" required value="{{ old('email') }}">
                </div>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="label">Phone / WhatsApp *</label>
                    <input type="text" name="phone" class="input" required value="{{ old('phone') }}">
                </div>
                <div>
                    <label class="label">City</label>
                    <input type="text" name="city" class="input" value="{{ old('city') }}" placeholder="e.g. Srinagar">
                </div>
            </div>

            <div>
                <label class="label">Registered Address</label>
                <textarea name="address" class="input" rows="2">{{ old('address') }}</textarea>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="label">PAN Number</label>
                    <input type="text" name="pan_number" class="input" value="{{ old('pan_number') }}">
                </div>
                <div>
                    <label class="label">GST Number</label>
                    <input type="text" name="gst_number" class="input" value="{{ old('gst_number') }}">
                </div>
                <div>
                    <label class="label">IATA Code</label>
                    <input type="text" name="iata_code" class="input" value="{{ old('iata_code') }}">
                </div>
            </div>

            <div class="grid grid-cols-3 gap-4">
                <div>
                    <label class="label">Credit Limit (₹)</label>
                    <input type="number" step="0.01" name="credit_limit" class="input" value="{{ old('credit_limit', '0') }}">
                </div>
                <div>
                    <label class="label">Commission Rate (%)</label>
                    <input type="number" step="0.01" name="commission_rate" class="input" value="{{ old('commission_rate', '0') }}">
                </div>
                <div>
                    <label class="label">Markup Rate (%)</label>
                    <input type="number" step="0.01" name="markup_rate" class="input" value="{{ old('markup_rate', '0') }}">
                </div>
            </div>

            <div class="flex justify-end gap-2 pt-2">
                <a href="{{ route('admin.agents.index') }}" class="btn-ghost btn-md">Cancel</a>
                <button class="btn-primary btn-md">Register & Approve Agent</button>
            </div>
        </form>
    </div>
@endsection
