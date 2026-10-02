@extends('layouts.site')

@section('page')
<section class="shell max-w-3xl pt-28 pb-16">
    <h1 class="font-display text-3xl font-bold">Cookie Policy</h1>
    <p class="mt-2 text-sm text-ink-500">Last updated: {{ now()->format('d M Y') }}</p>

    <div class="prose prose-slate mt-6 max-w-none text-ink-700">
        <p>This Cookie Policy explains how {{ settings('company_name', 'Leemroz Travels') }} ("we", "us")
        uses cookies and similar technologies when you visit our website. It should be read together with our
        <a href="{{ route('privacy-policy') }}" class="text-brand-600 underline">Privacy Policy</a>.</p>

        <h2 class="font-display mt-8 text-lg font-bold">What are cookies?</h2>
        <p>Cookies are small text files stored on your device. Similar technologies (such as local storage and
        pixels) work in comparable ways. They help the site function, remember your choices, and let us measure
        how the site is used.</p>

        <h2 class="font-display mt-8 text-lg font-bold">Categories we use</h2>
        <ul class="mt-2 list-disc pl-5">
            <li><strong>Necessary</strong> — required for core functions such as security, sessions, and completing a booking. These are always on.</li>
            <li><strong>Functional</strong> — remember preferences to personalize your experience.</li>
            <li><strong>Analytics</strong> — help us understand traffic and improve the site. We use Google Analytics 4 and our own first-party analytics.</li>
            <li><strong>Marketing</strong> — measure and improve advertising. We use the Meta Pixel and Google Ads.</li>
        </ul>

        <h2 class="font-display mt-8 text-lg font-bold">Your choices</h2>
        <p>Non-essential cookies (analytics and marketing) are only set after you consent. You can accept all,
        reject non-essential, or choose specific categories at any time.</p>
        <p>
            <button type="button" onclick="window.lzOpenConsent && window.lzOpenConsent()"
                class="btn-primary btn-md not-prose">Open cookie settings</button>
        </p>
        <p>You can also block or delete cookies in your browser settings, though some features may stop working.</p>

        <h2 class="font-display mt-8 text-lg font-bold">Third-party tools</h2>
        <p>When enabled with your consent, the following may set cookies or receive event data: Google Analytics 4
        / Google Tag Manager, Google Ads, and Meta (Facebook) Pixel. Their use of data is governed by their own
        policies. We never send your password, OTP, card details, or identity-document numbers to these tools.</p>

        <h2 class="font-display mt-8 text-lg font-bold">Contact</h2>
        <p>Questions about this policy? <a href="{{ route('contact') }}" class="text-brand-600 underline">Contact us</a>.</p>
    </div>
</section>
@endsection
