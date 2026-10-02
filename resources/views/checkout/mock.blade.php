@extends('layouts.site')

@section('page')
<section class="shell max-w-lg py-20">
    <div class="card p-8">
        <span class="badge-soft">Sandbox Payment Gateway</span>
        <h1 class="font-display mt-3 text-xl font-bold">Confirm Mock Payment</h1>
        <p class="mt-2 text-sm text-ink-500">
            This is the sandbox gateway. Confirming simulates a successful payment of
            <strong>{{ money($booking->total_amount) }}</strong> for {{ $booking->booking_reference }}.
        </p>

        <form action="{{ route('checkout.mock_pay', $booking) }}" method="POST" class="mt-6"
              x-data="{ submitting: false }" @submit="submitting = true">
            @csrf
            <button class="btn-primary btn-lg w-full" :disabled="submitting" :class="submitting ? 'opacity-60 cursor-not-allowed' : ''">
                <span x-show="!submitting">Pay {{ money($booking->total_amount) }} (Sandbox)</span>
                <span x-show="submitting" x-cloak>Processing… please don't close this page</span>
            </button>
        </form>
        <a href="{{ route('checkout.show', $booking) }}" class="mt-3 block text-center text-sm text-ink-500 hover:text-brand-700">Cancel and go back</a>
    </div>
</section>
@endsection
