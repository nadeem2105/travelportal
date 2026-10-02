@extends('layouts.site')

@section('page')
<section class="shell max-w-3xl py-16">
    <div class="mb-8 text-center">
        <div class="mb-3 inline-flex items-center gap-2 rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
            B2B Agent Programme
        </div>
        <h1 class="font-display text-3xl font-bold">Become a Travel Agent Partner</h1>
        <p class="mx-auto mt-2 max-w-xl text-sm text-ink-500">
            Join our agent network for exclusive net rates, wallet &amp; credit facilities, and commission on every booking. Submit your details below &mdash; our team reviews applications and activates approved accounts.
        </p>
    </div>

    <div class="card p-8">
        <form action="{{ route('agent.apply.submit') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">Agency details</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Agency name <span class="text-red-500">*</span></label>
                        <input type="text" name="agency_name" class="input" value="{{ old('agency_name') }}" required>
                    </div>
                    <div>
                        <label class="label">Contact person <span class="text-red-500">*</span></label>
                        <input type="text" name="contact_person" class="input" value="{{ old('contact_person') }}" required>
                    </div>
                    <div>
                        <label class="label">Email <span class="text-red-500">*</span></label>
                        <input type="email" name="email" class="input" value="{{ old('email') }}" required>
                    </div>
                    <div>
                        <label class="label">Phone <span class="text-red-500">*</span></label>
                        <input type="text" name="phone" class="input" value="{{ old('phone') }}" required>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">Location</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">City</label>
                        <input type="text" name="city" class="input" value="{{ old('city') }}">
                    </div>
                    <div>
                        <label class="label">State</label>
                        <input type="text" name="state" class="input" value="{{ old('state') }}">
                    </div>
                    <div>
                        <label class="label">Pincode</label>
                        <input type="text" name="pincode" class="input" value="{{ old('pincode') }}">
                    </div>
                    <div>
                        <label class="label">IATA code (if any)</label>
                        <input type="text" name="iata_code" class="input" value="{{ old('iata_code') }}">
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Address</label>
                        <textarea name="address" rows="2" class="input">{{ old('address') }}</textarea>
                    </div>
                </div>
            </div>

            <div>
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">KYC &amp; documents</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">PAN number <span class="text-red-500">*</span></label>
                        <input type="text" name="pan_number" class="input" value="{{ old('pan_number') }}" required>
                    </div>
                    <div>
                        <label class="label">GST number</label>
                        <input type="text" name="gst_number" class="input" value="{{ old('gst_number') }}">
                    </div>
                    <div>
                        <label class="label">PAN document <span class="text-red-500">*</span></label>
                        <input type="file" name="pan_document" class="input" accept="image/*,application/pdf" required>
                        <p class="mt-1 text-xs text-ink-400">JPG, PNG or PDF, up to 4 MB.</p>
                    </div>
                    <div>
                        <label class="label">GST document</label>
                        <input type="file" name="gst_document" class="input" accept="image/*,application/pdf">
                        <p class="mt-1 text-xs text-ink-400">Optional. JPG, PNG or PDF, up to 4 MB.</p>
                    </div>
                    <div>
                        <label class="label">Business license <span class="text-red-500">*</span></label>
                        <input type="file" name="business_license" class="input" accept="image/*,application/pdf" required>
                        <p class="mt-1 text-xs text-ink-400">JPG, PNG or PDF, up to 4 MB.</p>
                    </div>
                    <div class="sm:col-span-2">
                        <label class="label">Agency logo</label>
                        <input type="file" name="logo" class="input" accept="image/*">
                        <p class="mt-1 text-xs text-ink-400">Optional. JPG, PNG or WEBP, up to 2 MB.</p>
                    </div>
                </div>
                <p class="mt-2 text-xs text-ink-400">Our team verifies these documents before approving your account.</p>
            </div>

            <div>
                <h2 class="mb-3 text-sm font-bold uppercase tracking-wide text-ink-500">Set a password</h2>
                <div class="grid gap-4 sm:grid-cols-2">
                    <div>
                        <label class="label">Password <span class="text-red-500">*</span></label>
                        <input type="password" name="password" class="input" required>
                    </div>
                    <div>
                        <label class="label">Confirm password <span class="text-red-500">*</span></label>
                        <input type="password" name="password_confirmation" class="input" required>
                    </div>
                </div>
                <p class="mt-2 text-xs text-ink-400">You&rsquo;ll use this to sign in once your account is approved.</p>
            </div>

            <label class="flex items-start gap-2 text-sm text-ink-700">
                <input type="checkbox" name="terms" value="1" class="mt-1 accent-brand-600" {{ old('terms') ? 'checked' : '' }} required>
                <span>I agree to the partner terms &amp; conditions and confirm the details provided are accurate.</span>
            </label>

            <button class="btn-primary btn-lg w-full">Submit Application</button>
        </form>

        <p class="mt-5 text-center text-sm text-ink-500">
            Already a partner? <a href="{{ route('agent.login') }}" class="font-bold text-brand-600 hover:text-brand-800">Sign in</a>
        </p>
    </div>
</section>
@endsection
