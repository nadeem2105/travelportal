@extends('layouts.site')

@section('page')
<section class="shell py-10">
    {{-- Gallery + info --}}
    <div class="grid gap-2 lg:grid-cols-[2fr_1fr]">
        <div class="h-72 overflow-hidden rounded-2xl">
            <img src="{{ asset(img($hotel->cover_image, 'images/destinations/srinagar.svg')) }}" class="h-full w-full object-cover" alt="{{ $hotel->name }}">
        </div>
        <div class="grid grid-cols-2 gap-2 lg:grid-cols-1">
            @foreach (array_slice(array_merge($hotel->photos ?? [], ['images/destinations/gulmarg.svg', 'images/destinations/pahalgam.svg']), 0, 2) as $i => $photo)
                <div class="h-36 overflow-hidden rounded-2xl lg:h-[8.4rem]">
                    <img src="{{ asset(img($photo, 'images/destinations/gulmarg.svg')) }}" class="h-full w-full object-cover" alt="{{ $hotel->name }} photo {{ $i + 2 }}">
                </div>
            @endforeach
        </div>
    </div>

    <div class="mt-6 grid gap-8 lg:grid-cols-[1fr_360px]">
        <div>
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h1 class="font-display text-3xl font-bold">{{ $hotel->name }}</h1>
                    <p class="mt-1 text-sm text-amber-500">{{ str_repeat('★', $hotel->star_rating) }}
                        <span class="ml-1 text-ink-500">{{ $hotel->address }}</span></p>
                </div>
                @if ($rating > 0)
                    <span class="rounded-2xl bg-brand-600 px-4 py-2 text-center text-white">
                        <span class="block font-display text-xl font-bold">{{ $rating }}</span>
                        <span class="block text-[10px]">{{ $hotel->reviews()->approved()->count() }} reviews</span>
                    </span>
                @endif
            </div>

            <div class="prose-page mt-5">
                {!! nl2br(e($hotel->description ?? $hotel->short_description)) !!}
            </div>

            <h2 class="mt-8 font-display text-xl font-bold">Amenities</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($hotel->amenities ?? [] as $amenity)
                    <span class="badge-soft">{{ $amenity }}</span>
                @endforeach
            </div>

            @if ($hotel->policies)
                <h2 class="mt-8 font-display text-xl font-bold">Hotel Policies</h2>
                <ul class="mt-3 list-disc space-y-1 pl-5 text-sm text-ink-700">
                    @foreach ($hotel->policies as $policy)
                        <li>{{ $policy }}</li>
                    @endforeach
                </ul>
            @endif

            {{-- Reviews --}}
            @if ($reviews->count())
                <h2 class="mt-8 font-display text-xl font-bold">Guest Reviews</h2>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    @foreach ($reviews as $review)
                        <div class="card p-4">
                            <x-rating :rating="$review->rating" :size="3" />
                            <p class="mt-2 text-sm text-ink-700">&ldquo;{{ $review->content }}&rdquo;</p>
                            <p class="mt-2 text-xs font-bold text-ink-900">{{ $review->user?->name ?? 'Guest' }}
                                @if ($review->is_verified_booking) <span class="badge-soft ml-1 !text-[10px]">✓ Verified Stay</span> @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Room selection --}}
        <aside class="h-fit lg:sticky lg:top-24"
               x-data="{
                   open: {{ $errors->any() ? 'true' : 'false' }},
                   room: { id: {{ old('room_id', 'null') }}, type: '', price: '' },
                   reserve(r) { this.room = r; this.open = true; }
               }">
            <div class="card p-5">
                <h2 class="font-display text-lg font-bold">Select a Room</h2>
                @php
                    $occ = [($params['adults'] ?? 2).' '.\Illuminate\Support\Str::plural('adult', $params['adults'] ?? 2)];
                    if (!empty($params['children'])) $occ[] = $params['children'].' '.\Illuminate\Support\Str::plural('child', $params['children']);
                    if (!empty($params['infants'])) $occ[] = $params['infants'].' '.\Illuminate\Support\Str::plural('infant', $params['infants']);
                @endphp
                <p class="mt-0.5 text-xs text-ink-500">{{ \Carbon\Carbon::parse($params['check_in'])->format('d M') }} → {{ \Carbon\Carbon::parse($params['check_out'])->format('d M Y') }} · {{ implode(', ', $occ) }} · {{ $params['rooms'] ?? 1 }} {{ \Illuminate\Support\Str::plural('room', $params['rooms'] ?? 1) }}</p>

                @forelse ($rooms as $room)
                    <div class="mt-4 rounded-2xl border border-slate-200 p-4 transition hover:border-brand-300">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h3 class="text-sm font-bold text-ink-900">{{ $room['room_type'] }}</h3>
                                <p class="text-xs text-ink-500 font-medium">
                                    <span class="inline-flex items-center rounded-md bg-brand-50 px-1.5 py-0.5 text-xs text-brand-700 ring-1 ring-inset ring-brand-600/10">
                                        {{ $room['meal_plan_label'] ?? ucfirst(str_replace('_', ' ', $room['meal_plan'])) }}
                                    </span>
                                    · Max {{ $room['max_adults'] }} adults
                                    @if (!empty($room['max_children'])) + {{ $room['max_children'] }} child @endif
                                </p>
                                @if (!empty($room['extra_bed_price']))
                                    <p class="mt-1 text-[11px] text-ink-400">+ {{ money($room['extra_bed_price']) }} for extra bed/adult</p>
                                @endif
                                @if (!empty($room['child_price']))
                                    <p class="mt-0.5 text-[11px] text-ink-400">+ {{ money($room['child_price']) }} per child (2-12 yrs)</p>
                                @endif
                                @if (!empty($room['child_cost']))
                                    <p class="mt-0.5 text-[11px] font-semibold text-emerald-600">Includes {{ $room['chargeable_children'] }} child @ {{ money($room['child_price']) }}/night</p>
                                @endif
                            </div>
                            <p class="font-display text-lg font-extrabold text-ink-900">{{ money($room['display_price']) }}</p>
                        </div>
                        @if ($room['available_rooms'] < 3 && $room['available_rooms'] >= 1)
                            <p class="mt-2 text-xs font-semibold text-rose-500">Only {{ $room['available_rooms'] }} left!</p>
                        @endif
                        <button type="button"
                                class="btn-primary btn-md mt-3 w-full"
                                @disabled($room['available_rooms'] < 1)
                                @if ($room['available_rooms'] >= 1)
                                    @click="reserve({ id: {{ $room['room_id'] }}, type: @js($room['room_type']), price: @js(money($room['display_price'])) })"
                                @endif>
                            {{ $room['available_rooms'] < 1 ? 'Sold Out' : 'Reserve' }}
                        </button>
                    </div>
                @empty
                    <p class="mt-4 text-sm text-ink-500">We're unable to retrieve availability right now. Please try again.</p>
                @endforelse
            </div>

            {{-- Guest details modal --}}
            <div x-cloak x-show="open"
                 class="fixed inset-0 z-50 flex items-center justify-center bg-ink-900/50 p-4"
                 @keydown.escape.window="open = false">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl"
                     @click.outside="open = false"
                     x-transition>
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <h3 class="font-display text-lg font-bold">Guest Details</h3>
                            <p class="mt-0.5 text-xs text-ink-500">
                                <span x-text="room.type"></span> · <span class="font-semibold text-ink-700" x-text="room.price"></span>
                            </p>
                        </div>
                        <button type="button" class="text-ink-400 hover:text-ink-700" @click="open = false" aria-label="Close">&times;</button>
                    </div>

                    <form action="{{ route('hotels.book') }}" method="POST" class="mt-4 space-y-4">
                        @csrf
                        <input type="hidden" name="hotel_id" value="{{ $hotel->id }}">
                        <input type="hidden" name="room_id" :value="room.id">
                        <input type="hidden" name="check_in" value="{{ $params['check_in'] }}">
                        <input type="hidden" name="check_out" value="{{ $params['check_out'] }}">
                        <input type="hidden" name="rooms" value="{{ $params['rooms'] ?? 1 }}">

                        <div>
                            <label class="label">Full name <span class="text-red-500">*</span></label>
                            <input type="text" name="guest_name" class="input" value="{{ old('guest_name', auth()->user()?->name) }}" required>
                        </div>
                        <div>
                            <label class="label">Email <span class="text-red-500">*</span></label>
                            <input type="email" name="guest_email" class="input" value="{{ old('guest_email', auth()->user()?->email) }}" required>
                        </div>
                        <div>
                            <label class="label">Phone <span class="text-red-500">*</span></label>
                            <input type="text" name="guest_phone" class="input" value="{{ old('guest_phone', auth()->user()?->phone) }}" required>
                        </div>

                        <div class="flex gap-2">
                            <button type="button" class="btn-ghost btn-md flex-1" @click="open = false">Cancel</button>
                            <button type="submit" class="btn-primary btn-md flex-1">Continue to Payment</button>
                        </div>
                    </form>
                </div>
            </div>
        </aside>
    </div>
</section>

@endsection
