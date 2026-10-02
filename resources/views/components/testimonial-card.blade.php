@props(['testimonial'])

<div class="testi-card">
    <x-rating :rating="$testimonial->rating" />
    <p class="text-sm leading-6 text-ink-700">&ldquo;{{ $testimonial->content }}&rdquo;</p>
    <div class="mt-auto flex items-center gap-3 pt-2">
        <img src="{{ asset(img($testimonial->customer_photo, 'images/avatars/a1.svg')) }}" class="h-10 w-10 rounded-full object-cover" alt="{{ $testimonial->customer_name }}" loading="lazy">
        <div>
            <p class="text-sm font-bold text-ink-900">{{ $testimonial->customer_name }}</p>
            <p class="text-xs text-ink-500">{{ $testimonial->city }}@if($testimonial->destination) · Trip to {{ $testimonial->destination }}@endif</p>
        </div>
    </div>
</div>
