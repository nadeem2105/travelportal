@props(['destination'])

<a href="{{ route('destinations.show', $destination->slug) }}" class="dest-card">
    <img src="{{ asset(img($destination->cover_image, 'images/destinations/' . $destination->slug . '.svg')) }}" alt="{{ $destination->name }}" loading="lazy" decoding="async" width="300" height="200">
    <span class="overlay"></span>
    <span class="absolute bottom-3 left-4 right-14 text-white">
        <span class="font-display block text-lg font-bold">{{ $destination->name }}</span>
        <span class="block text-xs text-slate-200">{{ $destination->famous_for ?? $destination->region }}</span>
    </span>
    <span class="dest-arrow">
        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5"/></svg>
    </span>
</a>
