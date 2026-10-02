{{-- Section header: title + subtitle + optional CTA pill --}}
@php($cta = $cta ?? true)
<div class="flex flex-wrap items-end justify-between gap-3">
    <div class="flex flex-wrap items-baseline gap-x-4 gap-y-1">
        <h2 class="section-title">{{ $title }}</h2>
        @if ($subtitle)
            <p class="section-sub">{{ $subtitle }}</p>
        @endif
    </div>
    @if ($cta && ! empty($ctaText) && ! empty($ctaUrl))
        <a href="{{ $ctaUrl }}" class="btn-pill-link">
            {{ $ctaText }}
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
        </a>
    @endif
</div>
