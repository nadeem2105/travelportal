@extends('emails.layout')

@section('content')
    <span class="badge badge-brand">Trip Itinerary</span>
    <h2 style="margin: 16px 0 8px 0; color: #0f172a; font-size: 22px;">Your itinerary is ready</h2>
    <p style="margin: 0 0 8px 0;">Dear {{ $trip->customerName() ?: 'Valued Guest' }},</p>
    <p style="margin: 0 0 16px 0;">
        We're delighted to share the day-by-day plan for your trip to
        <strong>{{ $trip->destination_label ?: 'your destination' }}</strong>. Your detailed itinerary
        is attached as a PDF.
    </p>

    <div class="details-box">
        <div class="details-row"><span class="details-label">Reference</span><span class="details-val">{{ $trip->trip_reference }}</span></div>
        <div class="details-row"><span class="details-label">Destination</span><span class="details-val">{{ $trip->destination_label ?: '—' }}</span></div>
        <div class="details-row"><span class="details-label">Arrival</span><span class="details-val">{{ optional($trip->arrival_date)->format('d M Y') ?? '—' }}</span></div>
        <div class="details-row"><span class="details-label">Duration</span><span class="details-val">{{ $trip->total_days }} day(s)</span></div>
    </div>

    <p style="margin: 0;">We look forward to hosting you. Safe travels!</p>
@endsection
