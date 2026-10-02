@extends('layouts.site')
@section('activeNav', 'offers')

@section('page')
<section class="shell pt-28">
    <h1 class="font-display text-3xl font-bold">Offers &amp; Coupons</h1>
    <p class="section-sub mt-1">Save more on your next Kashmir trip</p>

    <div class="mt-6 grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
        @forelse ($offers as $offer)
            <div class="card card-hover flex flex-col p-6">
                <span class="flex h-14 w-14 items-center justify-center rounded-2xl bg-brand-50 font-display text-lg font-extrabold text-brand-600">{{ $offer->discount_text ?? '%' }}</span>
                <h2 class="font-display mt-4 text-lg font-bold text-ink-900">{{ $offer->title }}</h2>
                <p class="mt-1 flex-1 text-sm text-ink-500">{{ $offer->description }}</p>
                @if ($offer->ends_at)
                    <p class="mt-2 text-xs font-semibold text-rose-500">Valid till {{ $offer->ends_at->format('d M Y') }}</p>
                @endif
                <a href="{{ $offer->link_url ?? route('packages.index') }}" class="btn-primary btn-md mt-4 w-full">{{ $offer->button_text ?? 'Book Now' }}</a>
            </div>
        @empty
            <div class="card col-span-full p-12 text-center">
                <p class="font-display font-bold">No active offers right now</p>
                <p class="mt-1 text-sm text-ink-500">Subscribe to our newsletter to never miss a deal.</p>
            </div>
        @endforelse
    </div>

    <div class="mt-8">{{ $offers->links() }}</div>
</section>
@endsection
