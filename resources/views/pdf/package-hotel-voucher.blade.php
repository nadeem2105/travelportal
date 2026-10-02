@extends('pdf.layout')

@section('pdf_title', 'Hotel Voucher ' . $booking->booking_reference)

@section('foot_path')/booking/{{ $booking->booking_reference }}/voucher
@endsection

@section('pdf_content')
    @section('doc_badge')
        <span class="doc-label blue">HOTEL VOUCHER</span>
    @endsection
    @section('doc_right')
        <div class="doc-big">Booking #{{ $booking->booking_reference }}</div>
        <div class="doc-line">Issue Date: <strong style="color:#0f172a">{{ now()->format('d M Y') }}</strong></div>
        <div class="doc-line">Status: <span class="status-green">CONFIRMED</span></div>
    @endsection

    @include('pdf.partials.brand-header')

    <p class="muted" style="margin-bottom:6px">
        Package: <strong style="color:#0f172a">{{ $booking->packageBooking?->package_name ?? 'Tour Package' }}</strong>
        · Lead Guest: <strong style="color:#0f172a">{{ $booking->travellers->first()?->full_name ?? ($booking->contact['full_name'] ?? 'Guest') }}</strong>
    </p>

    @forelse ($booking->bookingHotels as $hotel)
        <h2 class="sec with-icon">{{ $hotel->segment_label ?: 'Hotel Stay' }}</h2>
        <div class="card">
            <table class="w">
                <tr>
                    <td class="vtop" width="50%">
                        <p class="lbl">Hotel</p>
                        <p class="val big blue">{{ $hotel->hotel_name_snapshot }}
                            @if ($hotel->star_rating_snapshot) <span style="color:#f59e0b; font-size:12px">{{ str_repeat('★', (int) $hotel->star_rating_snapshot) }}</span>@endif
                        </p>
                        <p class="muted">{{ $hotel->address_snapshot ?: '' }}</p>
                    </td>
                    <td class="vtop" width="50%">
                        <p class="lbl">Room &amp; Meal Plan</p>
                        <p class="val">{{ $hotel->room_name_snapshot ?: '—' }}</p>
                        <p class="muted">{{ $hotel->mealPlanLabel() }}</p>
                    </td>
                </tr>
                <tr><td colspan="2" style="height:12px"></td></tr>
                <tr>
                    <td class="vtop">
                        <p class="lbl">Check In</p>
                        <p class="val">{{ optional($hotel->check_in)->format('D, d M Y') ?? '—' }}</p>
                        <p class="muted">From 2:00 PM</p>
                    </td>
                    <td class="vtop">
                        <p class="lbl">Check Out</p>
                        <p class="val">{{ optional($hotel->check_out)->format('D, d M Y') ?? '—' }}</p>
                        <p class="muted">By 12:00 PM · {{ $hotel->nights }} Night(s)</p>
                    </td>
                </tr>
                <tr><td colspan="2" style="height:12px"></td></tr>
                <tr>
                    <td class="vtop">
                        <p class="lbl">Occupancy</p>
                        <p class="val">{{ $hotel->guestSummary() }}</p>
                        <p class="muted">{{ $hotel->rooms }} Room(s)</p>
                    </td>
                    <td class="vtop">
                        <p class="lbl">Supplier Reference</p>
                        <p class="val">{{ $hotel->supplier_booking_id ?: 'Direct booking' }}</p>
                        <p class="muted">{{ $hotel->refundable ? 'Refundable' : 'Non-refundable' }}</p>
                    </td>
                </tr>
            </table>
            @if ($hotel->cancellation_policy_snapshot)
                <div style="margin-top:10px; border-top:1px solid #e2e8f0; padding-top:8px">
                    <p class="lbl">Cancellation Policy</p>
                    <p class="muted">{{ $hotel->cancellation_policy_snapshot }}</p>
                </div>
            @endif
        </div>
    @empty
        <div class="card"><p class="muted">No hotel was included with this booking.</p></div>
    @endforelse

    <div class="note" style="border:1px solid #e2e8f0; background:#f8fafc; padding:10px 12px; font-size:10.5px; color:#475569; border-radius:6px; margin-top:16px">
        <strong>Check-in instructions:</strong> Present this voucher along with a valid government photo ID at each hotel reception.
        Hotel details reflect your booking at the time of purchase and remain valid regardless of later catalogue changes.
    </div>
@endsection
