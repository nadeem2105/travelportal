@extends('layouts.site')

@section('page')
<section class="shell max-w-5xl pt-28">
    <h1 class="font-display text-3xl font-bold">Contact Us</h1>
    <p class="section-sub mt-1">We're always here for you</p>

    <div class="mt-6 grid gap-6 lg:grid-cols-[1fr_360px]">
        <form action="{{ route('contact.submit') }}" method="POST" class="card space-y-4 p-6">
            @csrf
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Your Name</label>
                    <input type="text" name="name" class="input" value="{{ old('name') }}" required>
                </div>
                <div>
                    <label class="label">Email</label>
                    <input type="email" name="email" class="input" value="{{ old('email') }}" required>
                </div>
                <div>
                    <label class="label">Phone</label>
                    <input type="tel" name="phone" class="input" value="{{ old('phone') }}">
                </div>
                <div>
                    <label class="label">Subject</label>
                    <input type="text" name="subject" class="input" value="{{ old('subject') }}" required>
                </div>
            </div>
            <div>
                <label class="label">Message</label>
                <textarea name="message" rows="5" class="input" required>{{ old('message') }}</textarea>
            </div>
            <button class="btn-primary btn-lg">Send Message</button>
        </form>

        <div class="space-y-4">
            <div class="card p-6">
                <h2 class="font-display text-lg font-bold">Get in Touch</h2>
                <ul class="mt-4 space-y-4 text-sm">
                    <li class="flex gap-3">
                        <span class="feature-icon !h-9 !w-9">📞</span>
                        <div><p class="font-bold">{{ settings('company_phone') }}</p><p class="text-xs text-ink-500">24/7 Support</p></div>
                    </li>
                    <li class="flex gap-3">
                        <span class="feature-icon !h-9 !w-9">✉️</span>
                        <div><p class="font-bold">{{ settings('company_email') }}</p><p class="text-xs text-ink-500">Replies within 24h</p></div>
                    </li>
                    <li class="flex gap-3">
                        <span class="feature-icon !h-9 !w-9">📍</span>
                        <div><p class="font-bold">Visit Us</p><p class="text-xs text-ink-500">{{ settings('company_address') }}</p></div>
                    </li>
                </ul>
            </div>
            <div class="card p-6">
                <h2 class="font-display text-lg font-bold">Need faster help?</h2>
                <p class="mt-1 text-sm text-ink-500">Raise a support ticket with your booking reference.</p>
                <a href="{{ route('support.index') }}" class="btn-ghost btn-md mt-3 w-full text-center">Open a Ticket</a>
            </div>
        </div>
    </div>
</section>
@endsection
