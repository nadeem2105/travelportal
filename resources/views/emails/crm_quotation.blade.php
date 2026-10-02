@extends('emails.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 24px;">
        <span class="badge badge-brand">Personalized Travel Quotation</span>
        <h2 style="font-size: 22px; color: #0f172a; margin: 12px 0 4px 0;">{{ $quotation->title }}</h2>
        <p style="color: #64748b; font-size: 14px; margin: 0;">Quotation Reference: <strong style="font-family: monospace; color: #0f766e; font-size: 16px;">{{ $quotation->quotation_number }}</strong></p>
    </div>

    <p style="font-size: 15px;">Dear <strong>{{ $lead?->name ?? 'Traveller' }}</strong>,</p>
    <p style="font-size: 14px;">Thank you for your interest in travelling with Leemroz Travels. As requested, we have curated an exclusive travel proposal customized for your upcoming holiday.</p>

    <div class="details-box">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: #0f766e; border-bottom: 2px solid #ccfbf1; padding-bottom: 6px;">
            Quotation Summary & Pricing Breakdown
        </h3>

        <table width="100%" cellpadding="6" cellspacing="0">
            <tr>
                <td class="details-label">Proposal Title:</td>
                <td class="details-val">{{ $quotation->title }}</td>
            </tr>
            @if ($package)
            <tr>
                <td class="details-label">Package Option:</td>
                <td class="details-val">{{ $package->name }} ({{ $package->duration_days }}D / {{ $package->duration_nights }}N)</td>
            </tr>
            @endif
            @if ($lead?->destination)
            <tr>
                <td class="details-label">Destination:</td>
                <td class="details-val">{{ $lead->destination }}</td>
            </tr>
            @endif
            @if ($lead?->travellers_count)
            <tr>
                <td class="details-label">Number of Travellers:</td>
                <td class="details-val">{{ $lead->travellers_count }} Person(s)</td>
            </tr>
            @endif
            @if ($lead?->travel_date)
            <tr>
                <td class="details-label">Tentative Travel Date:</td>
                <td class="details-val">{{ $lead->travel_date->format('d M Y') }}</td>
            </tr>
            @endif
            <tr>
                <td class="details-label">Subtotal:</td>
                <td class="details-val">₹{{ number_format((float)$quotation->subtotal, 2) }}</td>
            </tr>
            @if ((float)$quotation->tax_amount > 0)
            <tr>
                <td class="details-label">Goods & Services Tax (GST):</td>
                <td class="details-val">₹{{ number_format((float)$quotation->tax_amount, 2) }}</td>
            </tr>
            @endif
            <tr style="border-top: 2px solid #0f766e;">
                <td class="details-label" style="font-size: 16px; color: #0f172a; font-weight: 700;">Grand Total:</td>
                <td class="details-val" style="font-size: 18px; color: #0f766e; font-weight: 800;">₹{{ number_format((float)$quotation->total_amount, 2) }}</td>
            </tr>
            @if ($quotation->valid_until)
            <tr>
                <td class="details-label">Offer Valid Until:</td>
                <td class="details-val" style="color: #b45309;">{{ $quotation->valid_until->format('d M Y') }}</td>
            </tr>
            @endif
        </table>
    </div>

    @php
        $consultantName = settings('company_legal_name', 'Leemroz Travels Private Limited');
        $consultantEmail = settings('company_email', 'info@leemroztravels.com');
        $consultantPhone = settings('company_phone', '+91 70069 76447');
    @endphp
    <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 14px 18px; margin: 20px 0; font-size: 14px;">
        <strong style="color: #0f766e;">Your Dedicated Travel Consultant:</strong><br>
        <span style="font-weight: 600; color: #0f172a;">{{ $consultantName }}</span><br>
        <span style="color: #64748b;">Email: <a href="mailto:{{ $consultantEmail }}" style="color: #0f766e;">{{ $consultantEmail }}</a></span>
        @if ($consultantPhone)
            &nbsp;•&nbsp; <span style="color: #64748b;">Phone: <a href="tel:{{ preg_replace('/\s+/', '', $consultantPhone) }}" style="color: #0f766e;">{{ $consultantPhone }}</a></span>
        @endif
    </div>

    <div style="text-align: center; margin: 30px 0 10px 0;">
        <a href="{{ $quotation->publicUrl() }}" class="btn" style="margin-right: 8px;">
            ✓ View &amp; Accept Online
        </a>
        <a href="tel:+917006976447" class="btn btn-secondary">
            📞 Speak with Consultant
        </a>
    </div>
    <p style="text-align:center;color:#94a3b8;font-size:12px;margin:8px 0 0;">
        Or open your quotation directly: <a href="{{ $quotation->publicUrl() }}" style="color:#0f766e;">{{ $quotation->publicUrl() }}</a>
    </p>
@endsection
