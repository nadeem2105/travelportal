@props(['package', 'badge' => null])

@php
    $badge = $badge ?? ($package->is_featured ? ['label' => 'Best Seller', 'class' => 'badge-best'] : null);
    $price = $package->effectivePrice(request('date'));
    $inWishlist = auth('web')->check() && auth('web')->user()->hasWishlist(\App\Models\Package::class, $package->id);
@endphp

<div class="pkg-card">
    <a href="{{ route('packages.show', $package->slug) }}" class="pkg-media">
        <img src="{{ asset(img($package->cover_image, 'images/packages/' . $package->slug . '.svg')) }}" alt="{{ $package->name }}" loading="lazy" decoding="async" width="400" height="250">
        @if ($badge)
            <span class="pkg-badge {{ $badge['class'] }}">
                @if ($badge['class'] === 'badge-best')
                    <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2l-6.1 3.4 1.4-6.8L2.2 9.1l6.9-.8L12 2Z"/></svg>
                @endif
                {{ $badge['label'] }}
            </span>
        @endif
    </a>
    <button type="button" class="pkg-wish {{ $inWishlist ? 'active' : '' }}" aria-label="Save to wishlist"
            @click="toggleWishlist($el, {{ $package->id }}, 'package')" x-data>
        <svg class="h-4.5 w-4.5" width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M21 8.25c0-2.485-2.099-4.5-4.688-4.5-1.935 0-3.597 1.126-4.312 2.733-.715-1.607-2.377-2.733-4.313-2.733C5.1 3.75 3 5.765 3 8.25c0 7.22 9 12 9 12s9-4.78 9-12Z"/></svg>
    </button>

    <div class="flex flex-1 flex-col p-4">
        <a href="{{ route('packages.show', $package->slug) }}" class="font-display text-base font-bold text-ink-900 hover:text-brand-700">{{ $package->name }}</a>
        <p class="mt-0.5 text-xs text-ink-500">{{ $package->duration_days }} Days {{ $package->duration_nights }} Nights</p>

        <div class="amenity-list mt-2">
            @foreach ($package->inclusions ?? [] as $inclusion)
                @continue(! in_array($inclusion, ['Hotel', 'Sightseeing', 'Cab', 'Activities', 'Meals', 'Breakfast', 'Transfer']))
                <span class="chip">
                    @if ($inclusion === 'Hotel')
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M4 3h16a1 1 0 0 1 1 1v16a1 1 0 0 1-1 1h-2v-5a2 2 0 0 0-2-2H8a2 2 0 0 0-2 2v5H4a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z"/></svg>
                    @elseif ($inclusion === 'Sightseeing')
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 3a15 15 0 0 1 0 18M12 3a15 15 0 0 0 0 18M3 12h18"/></svg>
                    @elseif ($inclusion === 'Cab')
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="currentColor"><path d="M5 11 6.5 6.5A2 2 0 0 1 8.4 5h7.2a2 2 0 0 1 1.9 1.5L19 11a2 2 0 0 1 2 2v4a1 1 0 0 1-1 1h-1a2 2 0 1 1-4 0H9a2 2 0 1 1-4 0H4a1 1 0 0 1-1-1v-4a2 2 0 0 1 2-2Z"/></svg>
                    @else
                        <svg class="h-3.5 w-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg>
                    @endif
                    {{ $inclusion }}
                </span>
            @endforeach
        </div>

        <div class="mt-auto flex items-center justify-between gap-2 pt-3">
            <div>
                <span class="font-display text-lg font-bold text-ink-900">{{ money($price) }}</span>
                @if ($package->discount_percent > 0)
                    <span class="ml-1 text-xs text-ink-300 line-through">{{ money($package->base_price) }}</span>
                @endif
                <span class="text-xs font-medium text-ink-500"> / person</span>
            </div>
            <a href="{{ route('packages.show', $package->slug) }}" class="btn-primary btn-sm">
                View Details
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5"/></svg>
            </a>
        </div>
    </div>
</div>
