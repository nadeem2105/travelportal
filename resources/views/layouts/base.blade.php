<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>{{ $seo['title'] ?? settings('company_name', config('app.name')) }}</title>
    <meta name="description" content="{{ $seo['description'] ?? '' }}">
    @if(!empty($seo['keywords']))
        <meta name="keywords" content="{{ $seo['keywords'] }}">
    @endif
    <meta name="robots" content="{{ $seo['robots'] ?? 'index,follow' }}">

    @if(!empty($seo['canonical']))
        <link rel="canonical" href="{{ $seo['canonical'] }}">
    @endif

    <!-- Open Graph -->
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ settings('company_name', config('app.name')) }}">
    <meta property="og:title" content="{{ $seo['og_title'] ?? $seo['title'] ?? '' }}">
    <meta property="og:description" content="{{ $seo['og_description'] ?? $seo['description'] ?? '' }}">
    @if(!empty($seo['og_image']))
        <meta property="og:image" content="{{ img($seo['og_image']) }}">
    @endif

    <link rel="icon" type="image/svg+xml" href="{{ asset(img(settings('company_favicon', 'images/favicon.svg'))) }}">

    <!-- Fonts: Inter / Poppins / Dancing Script -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&family=Poppins:wght@500;600;700;800&family=Dancing+Script:wght@600;700&display=swap" rel="stylesheet">

    @php($lzAnalytics = (array) config('services.analytics'))
    @if(!empty($lzAnalytics['search_console_verification']))
        <meta name="google-site-verification" content="{{ $lzAnalytics['search_console_verification'] }}">
    @endif

    <script>
        window.LeemrozRoutes = {
            wishlistToggle: @json(route('wishlist.toggle')),
            login: @json(route('login')),
        };
        window.LeemrozAnalytics = {
            enabled: {{ ($lzAnalytics['enabled'] ?? true) ? 'true' : 'false' }},
            api: @json(url('/api/v1/analytics')),
            sessionId: @json(request()->cookie('lz_sid')),
            ga4Id: @json($lzAnalytics['ga4']['measurement_id'] ?? null),
            gtmId: @json($lzAnalytics['gtm']['container_id'] ?? null),
            metaPixelId: @json($lzAnalytics['meta']['pixel_id'] ?? null),
            googleAdsId: @json($lzAnalytics['google_ads']['conversion_id'] ?? null),
        };
    </script>
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    @stack('styles')
    @if(!empty($seo['schema']))
        <script type="application/ld+json">{!! $seo['schema'] !!}</script>
    @endif
    @if(settings('seo_analytics_code'))
        {!! settings('seo_analytics_code') !!}
    @endif
</head>
<body>
    @if(!empty($lzAnalytics['gtm']['container_id']))
        <noscript><iframe src="https://www.googletagmanager.com/ns.html?id={{ $lzAnalytics['gtm']['container_id'] }}"
            height="0" width="0" style="display:none;visibility:hidden"></iframe></noscript>
    @endif

    @includeWhen(file_exists(base_path('resources/views/partials/flash.blade.php')), 'partials.flash')

    @yield('content')
    {{ $slot ?? '' }}

    @include('partials.consent-banner')

    @stack('scripts')
</body>
</html>
