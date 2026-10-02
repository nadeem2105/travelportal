@extends('layouts.site')

@section('page')
<section class="shell max-w-4xl pt-28">
    <h1 class="font-display text-3xl font-bold">What Our Travellers Say</h1>
    <p class="section-sub mt-1">Real reviews from verified bookings</p>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($reviews as $review)
            <div class="testi-card">
                <x-rating :rating="$review->rating" />
                @if ($review->title) <p class="text-sm font-bold text-ink-900">{{ $review->title }}</p> @endif
                <p class="text-sm leading-6 text-ink-700">&ldquo;{{ $review->content }}&rdquo;</p>
                <div class="mt-auto flex items-center justify-between pt-2">
                    <p class="text-xs font-bold text-ink-900">{{ $review->user?->name ?? 'Traveller' }}</p>
                    @if ($review->is_verified_booking)
                        <span class="badge-soft !text-[10px]">✓ Verified Booking</span>
                    @endif
                </div>
            </div>
        @empty
            <p class="text-ink-500">No reviews yet — be the first after your trip!</p>
        @endforelse
    </div>

    <div class="mt-8">{{ $reviews->links() }}</div>
</section>
@endsection
