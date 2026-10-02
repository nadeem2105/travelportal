@extends('layouts.site')

@section('page')
<section class="shell max-w-3xl py-10">
    <h1 class="font-display text-2xl font-bold">Traveller Details</h1>

    <div class="card mt-4 flex items-center justify-between gap-4 p-5">
        <div>
            <p class="font-bold text-ink-900">{{ $vehicle->name }}</p>
            <p class="text-sm text-ink-500">{{ $params['pickup'] }} → {{ $params['drop'] }} · {{ $distance }} km</p>
        </div>
        <p class="text-xs text-ink-500">{{ \Carbon\Carbon::parse($params['pickup_datetime'])->format('d M Y, h:i A') }}</p>
    </div>

    <form action="{{ route('cabs.book') }}" method="POST" class="card mt-6 space-y-4 p-6">
        @csrf
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label class="label">Full Name</label>
                <input type="text" name="name" class="input" value="{{ old('name', auth('web')->user()?->name) }}" required>
            </div>
            <div>
                <label class="label">Mobile Number</label>
                <input type="tel" name="phone" class="input" value="{{ old('phone', auth('web')->user()?->phone) }}" required>
            </div>
            <div class="sm:col-span-2">
                <label class="label">Email</label>
                <input type="email" name="email" class="input" value="{{ old('email', auth('web')->user()?->email) }}" required>
            </div>
        </div>
        <div class="flex items-center justify-between pt-2">
            <a href="{{ route('cabs.search', $params) }}" class="text-sm text-ink-500 hover:text-brand-700">← Change vehicle</a>
            <button class="btn-primary btn-lg">Continue to Payment</button>
        </div>
    </form>
</section>
@endsection
