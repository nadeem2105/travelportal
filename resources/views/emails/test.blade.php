@extends('emails.layout')

@section('content')
    <div style="text-align: center; margin-bottom: 24px;">
        <span class="badge badge-success">✓ SMTP Connectivity Verified</span>
        <h2 style="font-size: 22px; color: #0f172a; margin: 12px 0 4px 0;">SMTP Test Email Successful!</h2>
        <p style="color: #64748b; font-size: 14px; margin: 0;">Dispatched at: <strong>{{ $diagnostics['timestamp'] }}</strong></p>
    </div>

    <p style="font-size: 14px;">This diagnostic email confirms that your outgoing mail server configuration in the <strong>Leemroz Travels Admin Panel</strong> is active and communicating properly.</p>

    <div class="details-box">
        <h3 style="margin-top: 0; margin-bottom: 14px; font-size: 16px; color: #0f766e; border-bottom: 2px solid #ccfbf1; padding-bottom: 6px;">
            Connection Parameters Tested
        </h3>

        <table width="100%" cellpadding="6" cellspacing="0">
            <tr>
                <td class="details-label">Mail Driver:</td>
                <td class="details-val" style="font-family: monospace;">{{ $diagnostics['mailer'] }}</td>
            </tr>
            <tr>
                <td class="details-label">SMTP Host:</td>
                <td class="details-val" style="font-family: monospace;">{{ $diagnostics['host'] }}</td>
            </tr>
            <tr>
                <td class="details-label">SMTP Port:</td>
                <td class="details-val" style="font-family: monospace;">{{ $diagnostics['port'] }}</td>
            </tr>
            <tr>
                <td class="details-label">Encryption:</td>
                <td class="details-val" style="font-family: monospace; text-transform: uppercase;">{{ $diagnostics['encryption'] }}</td>
            </tr>
            <tr>
                <td class="details-label">From Address:</td>
                <td class="details-val">{{ $diagnostics['from_address'] }}</td>
            </tr>
            <tr>
                <td class="details-label">From Name:</td>
                <td class="details-val">{{ $diagnostics['from_name'] }}</td>
            </tr>
        </table>
    </div>

    <p style="font-size: 13px; color: #166534; background-color: #dcfce7; padding: 12px; border-radius: 6px; margin: 0;">
        ✓ Automated notifications for package, hotel, flight, and cab confirmations and CRM quotations are ready for live delivery.
    </p>
@endsection
