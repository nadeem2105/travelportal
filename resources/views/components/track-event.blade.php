@props([
    'event' => null,      // GA4/dataLayer event name, e.g. 'view_item', 'begin_checkout', 'purchase', 'search'
    'fb' => null,         // Meta Pixel standard event, e.g. 'ViewContent', 'InitiateCheckout', 'Purchase', 'Search'
    'data' => [],         // associative payload (value, currency, items, etc.)
])
{{--
  Fires a conversion event into GTM/GA4 (window.dataLayer) and Meta Pixel (window.fbq)
  IF they are present on the page. No-op otherwise, so it never errors when tracking
  isn't configured. Values come from server-side (authoritative), never guessed.
--}}
@if ($event || $fb)
@push('scripts')
<script>
    (function () {
        var payload = @json($data);
        @if ($event)
        if (window.dataLayer) { window.dataLayer.push(Object.assign({ event: @json($event) }, payload)); }
        if (window.analytics && window.analytics.track) { window.analytics.track(@json($event), payload); }
        @endif
        @if ($fb)
        if (window.fbq) { window.fbq('track', @json($fb), payload); }
        @endif
    })();
</script>
@endpush
@endif
