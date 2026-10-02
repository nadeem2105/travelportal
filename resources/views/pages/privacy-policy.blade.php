@extends('layouts.site')

@section('page')
<section class="shell max-w-3xl pt-28 pb-16">
    <h1 class="font-display text-3xl font-bold">Privacy Policy</h1>
    <p class="mt-2 text-sm text-ink-500">Last updated: {{ now()->format('d M Y') }}</p>

    <div class="prose prose-slate mt-6 max-w-none text-ink-700">
        <p>{{ settings('company_name', 'Leemroz Travels') }} ("we", "us") respects your privacy. This policy
        explains what we collect, why, and your choices. See also our
        <a href="{{ route('cookie-policy') }}" class="text-brand-600 underline">Cookie Policy</a>.</p>

        <h2 class="font-display mt-8 text-lg font-bold">Information we collect</h2>
        <ul class="mt-2 list-disc pl-5">
            <li><strong>You provide</strong> — name, email, phone, travel preferences, and booking details when you enquire or book.</li>
            <li><strong>Automatically</strong> — device, browser, operating system, approximate location (from IP), pages viewed, and how you reached us (referrer, campaign). IP addresses used for analytics are stored hashed, not in the clear.</li>
        </ul>

        <h2 class="font-display mt-8 text-lg font-bold">How we use it</h2>
        <ul class="mt-2 list-disc pl-5">
            <li>To provide and process your bookings and enquiries.</li>
            <li>To operate, secure, and improve our website.</li>
            <li>To measure traffic and, with your consent, advertising performance.</li>
            <li>To communicate with you about your booking and, where permitted, offers.</li>
        </ul>

        <h2 class="font-display mt-8 text-lg font-bold">Analytics &amp; advertising</h2>
        <p>With your consent we use Google Analytics 4, Google Tag Manager, Google Ads, and the Meta Pixel /
        Conversions API. For advertising measurement, identifiers such as email or phone are
        <strong>hashed</strong> before being shared, so raw values are not transmitted. You control this through
        our cookie settings.</p>
        <p><strong>We never collect or share sensitive data with analytics or advertising tools</strong>, including
        passwords, OTPs, full card numbers, CVV, bank details, passport, or other identity-document numbers.</p>

        <h2 class="font-display mt-8 text-lg font-bold">Your choices &amp; rights</h2>
        <p>You can accept or reject non-essential cookies at any time via
        <button type="button" onclick="window.lzOpenConsent && window.lzOpenConsent()" class="text-brand-600 underline not-prose">cookie settings</button>.
        Subject to applicable law, you may request access to, correction of, or deletion of your personal data by
        contacting us.</p>

        <h2 class="font-display mt-8 text-lg font-bold">Data retention &amp; security</h2>
        <p>We keep personal data only as long as needed for the purposes above or as required by law, and apply
        reasonable safeguards to protect it.</p>

        <h2 class="font-display mt-8 text-lg font-bold">Contact</h2>
        <p>To exercise your rights or ask questions, <a href="{{ route('contact') }}" class="text-brand-600 underline">contact us</a>.</p>
    </div>
</section>
@endsection
