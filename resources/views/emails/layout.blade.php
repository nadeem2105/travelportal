<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject ?? 'Leemroz Travels Notification' }}</title>
    <style>
        body { margin: 0; padding: 0; background-color: #f1f5f9; font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; -webkit-font-smoothing: antialiased; }
        table { border-collapse: collapse; }
        .wrapper { width: 100%; table-layout: fixed; background-color: #f1f5f9; padding: 30px 10px; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03); }
        .header { background: linear-gradient(135deg, #0f766e 0%, #115e59 100%); padding: 32px 30px; text-align: center; }
        .logo-text { color: #ffffff; font-size: 26px; font-weight: 800; letter-spacing: -0.5px; margin: 0; text-transform: uppercase; }
        .tagline { color: #99f6e4; font-size: 13px; font-weight: 500; margin-top: 6px; letter-spacing: 0.5px; }
        .content { padding: 36px 30px; color: #334155; line-height: 1.6; }
        .footer { background-color: #0f172a; padding: 26px 30px; text-align: center; color: #94a3b8; font-size: 12px; }
        .footer a { color: #2dd4bf; text-decoration: none; }
        .badge { display: inline-block; padding: 5px 12px; border-radius: 9999px; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; }
        .badge-success { background-color: #dcfce7; color: #15803d; }
        .badge-brand { background-color: #ccfbf1; color: #0f766e; }
        .badge-amber { background-color: #fef3c7; color: #b45309; }
        .btn { display: inline-block; padding: 13px 28px; background-color: #0f766e; color: #ffffff !important; text-decoration: none; font-weight: 700; font-size: 14px; border-radius: 8px; margin-top: 20px; text-align: center; }
        .btn-secondary { background-color: #334155; }
        .details-box { background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 20px; margin: 24px 0; }
        .details-row { display: flex; justify-content: space-between; padding: 8px 0; border-bottom: 1px dashed #cbd5e1; font-size: 14px; }
        .details-row:last-child { border-bottom: none; }
        .details-label { color: #64748b; font-weight: 500; }
        .details-val { color: #0f172a; font-weight: 700; text-align: right; }
        .support-card { background-color: #f0fdfa; border-left: 4px solid #0f766e; padding: 16px 20px; margin-top: 30px; border-radius: 4px; font-size: 13px; color: #134e4a; }
    </style>
</head>
<body>
    <table role="presentation" class="wrapper" width="100%">
        <tr>
            <td align="center">
                <div class="container">
                    <!-- Brand Header -->
                    <div class="header">
                        <h1 class="logo-text">{{ settings('company_name', 'Leemroz Travels') }}</h1>
                        <div class="tagline">{{ settings('company_tagline', 'Curated Kashmir Holidays · Flights · Hotels · Cabs') }}</div>
                    </div>

                    <!-- Email Body -->
                    <div class="content">
                        @yield('content')

                        <!-- Support Card -->
                        <div class="support-card">
                            <strong>Need Assistance with Your Journey?</strong><br>
                            Our Srinagar local concierge team is available 24/7.<br>
                            📞 <strong>{{ settings('company_phone', '+91 70069 76447') }}</strong> &nbsp;|&nbsp; ✉️ <strong>{{ settings('company_email', 'hello@leemroztravels.com') }}</strong>
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="footer">
                        <p style="margin: 0 0 8px 0;"><strong>{{ settings('company_legal_name', settings('company_name', 'Leemroz Travels')) }}</strong> · {{ settings('company_address', 'Ishber Nishat Gupt Ganga, Srinagar J&K -190025') }}@if (settings('company_registration')) · Reg. No: {{ settings('company_registration') }}@endif</p>
                        <p style="margin: 0;">This is an automated operational notification regarding your booking or quotation.<br>
                        <a href="{{ config('app.url') }}" target="_blank">Visit Portal</a> &nbsp;•&nbsp; <a href="{{ config('app.url') }}/account/bookings" target="_blank">Manage My Bookings</a></p>
                    </div>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>
