@extends('layouts.site')

@section('page')
<section class="shell max-w-4xl py-10">
    <h1 class="font-display text-2xl font-bold">Traveller Details</h1>

    {{-- Flight summary --}}
    <div class="card mt-4 flex flex-wrap items-center justify-between gap-4 p-5">
        <div class="flex items-center gap-3">
            <span class="flex h-11 w-11 items-center justify-center rounded-full bg-brand-50 text-sm font-extrabold text-brand-700">{{ $flight['airline']['code'] }}</span>
            <div>
                <p class="font-bold text-ink-900">{{ $flight['airline']['name'] }} · {{ $flight['flight_number'] }}</p>
                <p class="text-sm text-ink-500">
                    {{ $flight['segments'][0]['from']['city'] }} ({{ $flight['segments'][0]['from']['code'] }}) → {{ $flight['segments'][0]['to']['city'] }} ({{ $flight['segments'][0]['to']['code'] }})
                    · {{ \Carbon\Carbon::parse($flight['segments'][0]['from']['date'])->format('d M Y') }}
                </p>
            </div>
        </div>
        <div class="text-right">
            <p class="font-display text-xl font-extrabold text-ink-900">{{ money($flight['fare']['total_for_all']) }}</p>
            <p class="text-xs text-ink-500">{{ $flight['refundable'] ? 'Refundable' : 'Non-refundable' }}</p>
        </div>
    </div>

    <form action="{{ route('flights.book') }}" method="POST" class="mt-6 space-y-6">
        @csrf

        <div class="card p-6">
            <h2 class="mb-4 font-display text-lg font-bold">Contact Information</h2>
            <div class="grid gap-4 sm:grid-cols-2">
                <div>
                    <label class="label">Email</label>
                    <input type="email" name="contact_email" class="input" value="{{ old('contact_email', auth('web')->user()?->email) }}" required>
                </div>
                <div>
                    <label class="label">Mobile Number</label>
                    <input type="tel" name="contact_phone" class="input" value="{{ old('contact_phone', auth('web')->user()?->phone) }}" placeholder="+91 XXXXX XXXXX" required>
                </div>
            </div>
        </div>

        @php($typeCounts = ['adult' => $adults, 'child' => $children, 'infant' => $infants])
        @foreach (['adult' => 'Adult', 'child' => 'Child', 'infant' => 'Infant'] as $type => $label)
            @for ($i = 0; $i < $typeCounts[$type]; $i++)
                <div class="card p-6">
                    <h2 class="mb-4 font-display text-lg font-bold">{{ $label }} {{ $i + 1 }}</h2>
                    <div class="grid gap-4 sm:grid-cols-4">
                        <div>
                            <label class="label">Title</label>
                            <select name="{{ $type }}_{{ $i }}[title]" class="input" @if($type !== 'infant') required @endif>
                                @foreach ($type === 'child' ? ['Master', 'Miss'] : ['Mr', 'Mrs', 'Ms'] as $t)
                                    <option value="{{ $t }}">{{ $t }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="label">First Name</label>
                            <input type="text" name="{{ $type }}_{{ $i }}[first_name]" class="input" value="{{ old($type . '.' . $i . '.first_name') }}" required>
                        </div>
                        <div>
                            <label class="label">Last Name</label>
                            <input type="text" name="{{ $type }}_{{ $i }}[last_name]" class="input" value="{{ old($type . '.' . $i . '.last_name') }}" required>
                        </div>
                        @if ($type !== 'infant')
                            <div>
                                <label class="label">Date of Birth</label>
                                <input type="date" name="{{ $type }}_{{ $i }}[dob]" class="input" value="{{ old($type . '.' . $i . '.dob') }}">
                            </div>
                        @endif
                    </div>
                </div>
            @endfor
        @endforeach

        <div class="card flex flex-wrap items-center justify-between gap-4 p-6">
            <p class="text-sm text-ink-500">Fare rules: cancellation permitted per airline policy. Taxes calculated at checkout.</p>
            <button class="btn-primary btn-lg">Continue to Payment
                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
            </button>
        </div>
    </form>
</section>
@endsection
