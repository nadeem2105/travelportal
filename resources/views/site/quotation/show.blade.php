@extends('layouts.site')

@section('page')
@php
    $cur = $quotation->currency ?: 'INR';
    $sym = $cur === 'INR' ? '₹' : ($cur . ' ');
    $fmt = fn ($n) => $sym . number_format((float) $n, 2);
    $accepted = in_array($quotation->status, ['accepted', 'converted'], true);
    $rejected = $quotation->status === 'rejected';
@endphp

<div class="quote-wrap">
    <div class="quote-card">

        {{-- Flash messages --}}
        @foreach (['success' => 'ok', 'info' => 'info', 'error' => 'err'] as $key => $cls)
            @if (session($key))
                <div class="quote-alert quote-alert--{{ $cls }}">{{ session($key) }}</div>
            @endif
        @endforeach

        {{-- Header --}}
        <div class="quote-head">
            <div>
                <div class="quote-brand">{{ settings('company_name', config('app.name')) }}</div>
                <h1 class="quote-title">{{ $quotation->title }}</h1>
                <div class="quote-meta">
                    Quotation <strong>{{ $quotation->quotation_number }}</strong>
                    @if ($quotation->valid_until)
                        &middot; Valid until {{ $quotation->valid_until->format('d M Y') }}
                    @endif
                    @if ($quotation->paxSummary())
                        &middot; {{ $quotation->paxSummary() }}
                    @endif
                </div>
            </div>
            <div class="quote-status quote-status--{{ $quotation->status }}">
                {{ ucfirst($quotation->status) }}
            </div>
        </div>

        @if ($expired && ! $accepted)
            <div class="quote-alert quote-alert--warn">
                This quotation expired on {{ $quotation->valid_until?->format('d M Y') }}. Please contact us for an updated quote.
            </div>
        @endif

        {{-- Package summary --}}
        @if ($package)
            <div class="quote-section">
                <h2 class="quote-h2">Package</h2>
                <div class="quote-pkg">
                    <div class="quote-pkg__name">{{ $package->title ?? $package->name }}</div>
                    @if ($package->destination)
                        <div class="quote-pkg__dest">{{ $package->destination->name }}</div>
                    @endif
                    @if (! empty($package->duration_days))
                        <div class="quote-pkg__dur">{{ $package->duration_days }} Days
                            @if (! empty($package->duration_nights)) / {{ $package->duration_nights }} Nights @endif
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Stay & Transfer (optional) --}}
        @php($qHotels = $quotation->resolvedHotels())
        @php($qVehicle = $quotation->vehicle)
        @if (! empty($qHotels) || $qVehicle || $quotation->pickup_location || $quotation->dropoff_location)
            <div class="quote-section">
                <h2 class="quote-h2">Stay &amp; Transfer</h2>

                @if (! empty($qHotels))
                    <table class="quote-items" style="margin-bottom:14px;">
                        <tbody>
                            @foreach ($qHotels as $i => $stay)
                                <tr>
                                    <td>
                                        <strong>{{ $stay['hotel_name'] ?? 'Hotel' }}</strong>
                                        @if (! empty($stay['city'])) <span style="color:#70708a;">· {{ $stay['city'] }}</span>@endif
                                        @if (! empty($stay['location']))<div class="quote-stay__sub">{{ $stay['location'] }}</div>@endif
                                    </td>
                                    <td class="quote-items__amt">
                                        @if (! empty($stay['nights'])){{ $stay['nights'] }} {{ \Illuminate\Support\Str::plural('Night', $stay['nights']) }}@endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                @endif

                <div class="quote-stay">
                    @if ($qVehicle)
                        <div class="quote-stay__item">
                            <span class="quote-stay__label">Cab / Vehicle</span>
                            <span class="quote-stay__val">{{ $qVehicle->name }}@if ($qVehicle->type) ({{ $qVehicle->type->name }})@endif</span>
                        </div>
                    @endif
                    @if ($quotation->pickup_location)
                        <div class="quote-stay__item">
                            <span class="quote-stay__label">Pick-up</span>
                            <span class="quote-stay__val">{{ $quotation->pickup_location }}</span>
                        </div>
                    @endif
                    @if ($quotation->dropoff_location)
                        <div class="quote-stay__item">
                            <span class="quote-stay__label">Drop-off</span>
                            <span class="quote-stay__val">{{ $quotation->dropoff_location }}</span>
                        </div>
                    @endif
                </div>
            </div>
        @endif

        {{-- Day-by-day itinerary with overnight stays --}}
        @php($qItinerary = $quotation->resolvedItinerary())
        @if (! empty($qItinerary))
            <div class="quote-section">
                <h2 class="quote-h2">Day-by-Day Itinerary</h2>
                <div class="quote-itin">
                    @foreach ($qItinerary as $i => $day)
                        <div class="quote-itin__day">
                            <div class="quote-itin__badge">Day {{ $day['day'] ?? ($i + 1) }}</div>
                            <div class="quote-itin__body">
                                @if (! empty($day['title']))<div class="quote-itin__title">{{ $day['title'] }}</div>@endif
                                @if (! empty($day['description']))<div class="quote-itin__desc">{{ $day['description'] }}</div>@endif
                                @if (! empty($day['stay']))<div class="quote-itin__stay"><span>Overnight:</span> {{ $day['stay'] }}</div>@endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        @endif

        {{-- Line items --}}
        @if (! empty($quotation->items))
            <div class="quote-section">
                <h2 class="quote-h2">Details</h2>
                <table class="quote-items">
                    <tbody>
                        @foreach ($quotation->items as $item)
                            <tr>
                                <td>{{ $item['label'] ?? '' }}</td>
                                <td class="quote-items__amt">{{ $fmt($item['amount'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Price breakdown --}}
        <div class="quote-section">
            <h2 class="quote-h2">Price Summary</h2>
            <div class="quote-price">
                <div class="quote-price__row"><span>Subtotal</span><span>{{ $fmt($quotation->subtotal) }}</span></div>
                @if ((float) $quotation->tax_amount > 0)
                    <div class="quote-price__row"><span>Taxes</span><span>{{ $fmt($quotation->tax_amount) }}</span></div>
                @endif
                @if ((float) $quotation->discount_amount > 0)
                    <div class="quote-price__row quote-price__row--disc"><span>Discount</span><span>&minus;{{ $fmt($quotation->discount_amount) }}</span></div>
                @endif
                <div class="quote-price__row quote-price__row--total"><span>Total</span><span>{{ $fmt($quotation->total_amount) }}</span></div>
            </div>
        </div>

        {{-- Payment schedule --}}
        @if (! empty($quotation->payment_schedule))
            <div class="quote-section">
                <h2 class="quote-h2">Payment Schedule</h2>
                <table class="quote-items">
                    <tbody>
                        @foreach ($quotation->payment_schedule as $p)
                            <tr>
                                <td>{{ $p['label'] ?? ($p['due'] ?? '') }}</td>
                                <td class="quote-items__amt">{{ $fmt($p['amount'] ?? 0) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif

        {{-- Inclusions / Exclusions --}}
        @php($incl = $quotation->resolvedInclusions())
        @php($excl = $quotation->resolvedExclusions())
        @if ($incl)
            <div class="quote-section">
                <h2 class="quote-h2">Inclusions</h2>
                <ul class="quote-list quote-list--incl">
                    @foreach ($incl as $item)<li>{{ is_array($item) ? ($item['title'] ?? reset($item)) : $item }}</li>@endforeach
                </ul>
            </div>
        @endif
        @if ($excl)
            <div class="quote-section">
                <h2 class="quote-h2">Exclusions</h2>
                <ul class="quote-list quote-list--excl">
                    @foreach ($excl as $item)<li>{{ is_array($item) ? ($item['title'] ?? reset($item)) : $item }}</li>@endforeach
                </ul>
            </div>
        @endif

        {{-- Terms / cancellation --}}
        @if ($quotation->terms)
            <div class="quote-section">
                <h2 class="quote-h2">Terms &amp; Conditions</h2>
                <div class="quote-prose">{!! nl2br(e($quotation->terms)) !!}</div>
            </div>
        @endif
        @if ($quotation->cancellation_policy)
            <div class="quote-section">
                <h2 class="quote-h2">Cancellation Policy</h2>
                <div class="quote-prose">{!! nl2br(e($quotation->cancellation_policy)) !!}</div>
            </div>
        @endif

        {{-- Actions --}}
        <div class="quote-actions">
            <a class="quote-btn quote-btn--ghost" href="{{ route('quote.pdf', $quotation->public_token) }}" target="_blank" rel="noopener">Download PDF</a>

            @if ($accepted)
                <span class="quote-done quote-done--ok">✓ You accepted this quotation</span>
            @elseif ($rejected)
                <span class="quote-done quote-done--no">You declined this quotation</span>
            @elseif (! $expired)
                <form method="POST" action="{{ route('quote.accept', $quotation->public_token) }}" class="quote-inline">
                    @csrf
                    <button type="submit" class="quote-btn quote-btn--primary">Accept Quotation</button>
                </form>
                <button type="button" class="quote-btn quote-btn--danger" onclick="document.getElementById('quote-decline').style.display='block';this.style.display='none';">Decline</button>
            @endif
        </div>

        @if (! $accepted && ! $rejected && ! $expired)
            <form method="POST" action="{{ route('quote.decline', $quotation->public_token) }}" id="quote-decline" class="quote-decline" style="display:none;">
                @csrf
                <label for="reason" class="quote-label">Let us know why (optional)</label>
                <textarea name="reason" id="reason" rows="3" class="quote-textarea" placeholder="e.g. Budget, dates changed, chose another option..."></textarea>
                <button type="submit" class="quote-btn quote-btn--danger">Confirm Decline</button>
            </form>
        @endif

        @if ($quotation->lead?->assignee)
            <div class="quote-agent">
                Questions? Contact <strong>{{ $quotation->lead->assignee->name }}</strong>
                @if ($quotation->lead->assignee->email) &middot; {{ $quotation->lead->assignee->email }} @endif
            </div>
        @endif
    </div>
</div>

@push('styles')
<style>
    .quote-wrap{max-width:760px;margin:0 auto;padding:32px 16px 64px;}
    .quote-card{background:#fff;border:1px solid #ececf1;border-radius:16px;padding:32px;box-shadow:0 8px 32px rgba(20,20,40,.06);}
    .quote-head{display:flex;justify-content:space-between;align-items:flex-start;gap:16px;flex-wrap:wrap;border-bottom:1px solid #f0f0f4;padding-bottom:20px;margin-bottom:24px;}
    .quote-brand{font-size:12px;letter-spacing:.08em;text-transform:uppercase;color:#8a8aa0;font-weight:600;}
    .quote-title{font-size:24px;line-height:1.25;margin:6px 0 8px;color:#1a1a2e;font-weight:700;}
    .quote-meta{font-size:13px;color:#70708a;}
    .quote-status{font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.05em;padding:6px 12px;border-radius:999px;background:#eef;color:#4b4bd6;white-space:nowrap;}
    .quote-status--accepted,.quote-status--converted{background:#e6f8ee;color:#128a4a;}
    .quote-status--rejected{background:#fdeaea;color:#c0362c;}
    .quote-status--expired{background:#f3f3f5;color:#7a7a88;}
    .quote-section{margin:24px 0;}
    .quote-h2{font-size:13px;text-transform:uppercase;letter-spacing:.06em;color:#8a8aa0;font-weight:700;margin:0 0 12px;}
    .quote-pkg__name{font-size:18px;font-weight:700;color:#1a1a2e;}
    .quote-pkg__dest,.quote-pkg__dur{font-size:14px;color:#5a5a72;margin-top:2px;}
    .quote-stay{display:grid;grid-template-columns:repeat(2,1fr);gap:14px;background:#fafafc;border-radius:12px;padding:16px 18px;}
    .quote-stay__item{display:flex;flex-direction:column;}
    .quote-stay__label{font-size:11px;text-transform:uppercase;letter-spacing:.05em;color:#8a8aa0;font-weight:700;margin-bottom:3px;}
    .quote-stay__val{font-size:15px;color:#1a1a2e;font-weight:600;}
    .quote-stay__sub{display:block;font-size:13px;color:#70708a;font-weight:400;}
    .quote-itin{display:flex;flex-direction:column;gap:12px;}
    .quote-itin__day{display:flex;gap:12px;align-items:flex-start;}
    .quote-itin__badge{flex:0 0 auto;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;background:#eef;color:#4b4bd6;padding:5px 10px;border-radius:999px;white-space:nowrap;}
    .quote-itin__body{flex:1;padding-bottom:12px;border-bottom:1px solid #f2f2f6;}
    .quote-itin__day:last-child .quote-itin__body{border-bottom:none;padding-bottom:0;}
    .quote-itin__title{font-size:15px;font-weight:700;color:#1a1a2e;}
    .quote-itin__desc{font-size:14px;line-height:1.6;color:#4a4a5e;margin-top:3px;}
    .quote-itin__stay{font-size:13px;color:#3a3a4e;margin-top:5px;}
    .quote-itin__stay span{font-weight:700;color:#4b4bd6;}
    .quote-items{width:100%;border-collapse:collapse;}
    .quote-items td{padding:10px 0;border-bottom:1px solid #f2f2f6;font-size:15px;color:#333;}
    .quote-items__amt{text-align:right;font-variant-numeric:tabular-nums;white-space:nowrap;}
    .quote-price{background:#fafafc;border-radius:12px;padding:16px 18px;}
    .quote-price__row{display:flex;justify-content:space-between;padding:7px 0;font-size:15px;color:#444;}
    .quote-price__row--disc span:last-child{color:#128a4a;}
    .quote-price__row--total{border-top:1px solid #e6e6ee;margin-top:6px;padding-top:12px;font-size:19px;font-weight:800;color:#1a1a2e;}
    .quote-prose{font-size:14px;line-height:1.6;color:#4a4a5e;}
    .quote-list{margin:0;padding-left:18px;font-size:14px;line-height:1.7;color:#3a3a4e;}
    .quote-list li{margin-bottom:4px;}
    .quote-list--incl li::marker{color:#128a4a;}
    .quote-list--excl li::marker{color:#c0362c;}
    .quote-actions{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:28px;padding-top:20px;border-top:1px solid #f0f0f4;}
    .quote-inline{display:inline;}
    .quote-btn{display:inline-block;font-size:15px;font-weight:600;padding:12px 22px;border-radius:10px;border:1px solid transparent;cursor:pointer;text-decoration:none;transition:.15s;}
    .quote-btn--primary{background:#4b4bd6;color:#fff;}
    .quote-btn--primary:hover{background:#3a3ac0;}
    .quote-btn--danger{background:#fff;color:#c0362c;border-color:#e7c3c0;}
    .quote-btn--danger:hover{background:#fdeaea;}
    .quote-btn--ghost{background:#fff;color:#4b4bd6;border-color:#d8d8ea;}
    .quote-btn--ghost:hover{background:#f4f4fb;}
    .quote-decline{margin-top:16px;}
    .quote-label{display:block;font-size:13px;color:#70708a;margin-bottom:6px;}
    .quote-textarea{width:100%;border:1px solid #dcdce6;border-radius:10px;padding:10px 12px;font:inherit;font-size:14px;resize:vertical;margin-bottom:12px;box-sizing:border-box;}
    .quote-done{font-weight:700;font-size:15px;}
    .quote-done--ok{color:#128a4a;}
    .quote-done--no{color:#c0362c;}
    .quote-agent{margin-top:24px;font-size:14px;color:#5a5a72;text-align:center;}
    .quote-alert{padding:12px 16px;border-radius:10px;margin-bottom:16px;font-size:14px;}
    .quote-alert--ok{background:#e6f8ee;color:#128a4a;}
    .quote-alert--info{background:#eef;color:#4b4bd6;}
    .quote-alert--err{background:#fdeaea;color:#c0362c;}
    .quote-alert--warn{background:#fff6e6;color:#9a6a00;}
    @media (max-width:480px){
        .quote-card{padding:20px;border-radius:12px;}
        .quote-title{font-size:20px;}
        .quote-actions{flex-direction:column;align-items:stretch;}
        .quote-btn{text-align:center;width:100%;box-sizing:border-box;}
        .quote-stay{grid-template-columns:1fr;}
    }
</style>
@endpush
@endsection
