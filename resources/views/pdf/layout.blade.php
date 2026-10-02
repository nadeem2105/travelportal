{{-- Shared PDF layout matching the approved Tax Invoice / Tour Itinerary samples --}}
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>@yield('pdf_title', 'Document')</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: DejaVu Sans, Helvetica, sans-serif; color: #0f172a; font-size: 12px; line-height: 1.45; }

        .brand-name { font-size: 24px; font-weight: bold; color: #0f172a; letter-spacing: 0.2px; }
        .brand-tagline { font-size: 10px; font-weight: bold; color: #2563eb; letter-spacing: 2px; margin-top: 2px; }
        .brand-line { font-size: 11px; color: #334155; margin-top: 6px; }
        .brand-line.muted { color: #64748b; margin-top: 2px; }

        .doc-right { text-align: right; }
        .doc-label { font-size: 11px; font-weight: bold; color: #0f766e; letter-spacing: 1.5px; }
        .doc-label.blue { color: #2563eb; }
        .doc-big { font-size: 18px; font-weight: bold; color: #0f172a; margin-top: 4px; }
        .doc-line { font-size: 11px; color: #475569; margin-top: 3px; }
        .status-green { color: #16a34a; font-weight: bold; }
        .status-amber { color: #d97706; font-weight: bold; }

        .head-rule { border-bottom: 2px solid #e2e8f0; margin: 14px 0 18px 0; }

        .card { border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 14px; margin-bottom: 14px; }

        h2.sec { font-size: 14px; font-weight: bold; color: #1d4ed8; margin: 18px 0 8px 0; }
        h2.sec.with-icon { color: #2563eb; }

        table.w { width: 100%; border-collapse: collapse; }
        td.vtop { vertical-align: top; }

        .lbl { font-size: 9px; font-weight: bold; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.8px; }
        .val { font-size: 12px; font-weight: bold; color: #0f172a; margin-top: 3px; }
        .val.big { font-size: 15px; }
        .muted { color: #64748b; font-size: 11px; }
        .green { color: #16a34a; }
        .blue { color: #2563eb; }

        table.data { width: 100%; border-collapse: collapse; }
        table.data th { text-align: left; font-size: 9px; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; border-bottom: 1px solid #cbd5e1; padding: 8px 6px; }
        table.data td { padding: 10px 6px; border-bottom: 1px solid #e2e8f0; font-size: 12px; vertical-align: top; word-wrap: break-word; overflow-wrap: break-word; }
        table.data td .blue { word-break: break-all; }

        .pill { display: inline-block; border: 1px solid #bfdbfe; background: #eff6ff; color: #1d4ed8; border-radius: 20px; padding: 3px 10px; font-size: 10px; font-weight: bold; }
        .pill.green { border-color: #bbf7d0; background: #f0fdf4; color: #16a34a; }
        .pill.gray { border-color: #e2e8f0; background: #f8fafc; color: #64748b; }

        .totals { margin-top: 12px; width: 62%; margin-left: auto; }
        .totals .row { padding: 5px 0; font-size: 12px; color: #334155; }
        .totals .grand { padding-top: 8px; font-size: 16px; font-weight: bold; color: #0f172a; }

        .terms { margin-top: 18px; font-size: 10.5px; color: #475569; }
        .doc-footer { margin-top: 22px; border-top: 1px solid #e2e8f0; padding-top: 8px; font-size: 9px; color: #94a3b8; overflow: hidden; }
        .doc-footer .left { float: left; }
        .doc-footer .right { float: right; }
    </style>
</head>
<body>
    <div style="padding: 30px 36px;">
        @yield('pdf_content')

        <div class="doc-footer">
            <div class="left">{{ url('/') }}@yield('foot_path')</div>
            <div class="right">Page {PAGE_NUM} of {PAGE_COUNT}</div>
        </div>
    </div>
</body>
</html>
