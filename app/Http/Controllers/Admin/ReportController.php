<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Refund;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        [$from, $to, $range] = $this->range($request);

        $bookingsQuery = Booking::whereBetween('created_at', [$from, $to]);

        $summary = [
            'bookings' => (clone $bookingsQuery)->count(),
            'revenue' => (float) (clone $bookingsQuery)->whereIn('status', ['confirmed', 'completed'])->sum('total_amount'),
            'taxes' => (float) (clone $bookingsQuery)->whereIn('status', ['confirmed', 'completed'])->sum('tax_amount'),
            'discounts' => (float) (clone $bookingsQuery)->whereIn('status', ['confirmed', 'completed'])->sum('discount_amount'),
            'refunds' => (float) Refund::where('status', 'processed')->whereBetween('created_at', [$from, $to])->sum('amount'),
            'new_customers' => User::whereBetween('created_at', [$from, $to])->count(),
        ];

        $productWise = (clone $bookingsQuery)->selectRaw('product_type, COUNT(*) as count, SUM(total_amount) as revenue')
            ->groupBy('product_type')->get();

        $daily = (clone $bookingsQuery)->selectRaw("DATE(created_at) as date, COUNT(*) as bookings, SUM(CASE WHEN status IN ('confirmed','completed') THEN total_amount ELSE 0 END) as revenue")
            ->groupBy('date')->orderBy('date')->get();

        $topDestinations = \App\Models\Destination::withCount('packages')
            ->orderByDesc('packages_count')
            ->limit(6)->get();

        $supplierPerformance = \App\Models\Supplier::withCount(['bookings'])
            ->orderByDesc('bookings_count')->limit(8)->get();

        $confirmedBookings = (clone $bookingsQuery)->whereIn('status', ['confirmed', 'completed']);
        $grossTurnover = (float) (clone $confirmedBookings)->sum('total_amount');
        $supplierPayables = (float) (clone $confirmedBookings)->sum('supplier_cost');
        $taxesCollected = (float) (clone $confirmedBookings)->sum('tax_amount');
        $markupEarned = (float) (clone $confirmedBookings)->sum('markup_amount');
        $gatewayFees = round($grossTurnover * 0.02, 2);
        $refundsDeducted = (float) Refund::where('status', 'processed')->whereBetween('created_at', [$from, $to])->sum('amount');
        $netPlatformMargin = max(0, round($grossTurnover - $supplierPayables - $gatewayFees - $taxesCollected - $refundsDeducted, 2));
        $marginPercentage = $grossTurnover > 0 ? round(($netPlatformMargin / $grossTurnover) * 100, 1) : 0.0;

        $financials = [
            'gross_turnover' => $grossTurnover,
            'supplier_payables' => $supplierPayables,
            'taxes_collected' => $taxesCollected,
            'markup_earned' => $markupEarned,
            'gateway_fees' => $gatewayFees,
            'refunds_deducted' => $refundsDeducted,
            'net_margin' => $netPlatformMargin,
            'margin_percentage' => $marginPercentage,
        ];

        $funnel = app(\App\Services\AnalyticsService::class)->funnelSummary($from, $to);

        return view('admin.reports.index', compact('summary', 'financials', 'productWise', 'daily', 'range', 'from', 'to', 'topDestinations', 'supplierPerformance', 'funnel'));
    }

    public function export(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        ActivityLogger::log('export', 'reports', 'Exported sales report CSV');

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Bookings', 'Revenue']);

            Booking::selectRaw("DATE(created_at) as date, COUNT(*) as bookings, SUM(CASE WHEN status IN ('confirmed','completed') THEN total_amount ELSE 0 END) as revenue")
                ->whereBetween('created_at', [$from, $to])
                ->groupBy('date')->orderBy('date')
                ->chunk(500, function ($rows) use ($out) {
                    foreach ($rows as $row) {
                        fputcsv($out, [$row->date, $row->bookings, $row->revenue]);
                    }
                });

            fclose($out);
        }, 'sales-report.csv', ['Content-Type' => 'text/csv']);
    }

    public function exportFinancial(Request $request): StreamedResponse
    {
        [$from, $to] = $this->range($request);

        ActivityLogger::log('export', 'reports', 'Exported financial ledger CSV');

        return response()->streamDownload(function () use ($from, $to) {
            $out = fopen('php://output', 'w');
            fputcsv($out, [
                'Reference',
                'Date',
                'Product',
                'Customer',
                'Status',
                'Customer Paid (INR)',
                'Supplier Cost (INR)',
                'Tax Collected (INR)',
                'Est. Gateway Fee (INR)',
                'Net Platform Margin (INR)',
            ]);

            Booking::with('user')
                ->whereBetween('created_at', [$from, $to])
                ->whereIn('status', ['confirmed', 'completed'])
                ->orderBy('created_at', 'desc')
                ->chunk(500, function ($bookings) use ($out) {
                    foreach ($bookings as $b) {
                        $paid = (float) $b->total_amount;
                        $cost = (float) ($b->supplier_cost ?? 0);
                        $tax = (float) ($b->tax_amount ?? 0);
                        $gwFee = round($paid * 0.02, 2);
                        $margin = round($paid - $cost - $tax - $gwFee, 2);

                        fputcsv($out, [
                            $b->booking_reference,
                            $b->created_at->format('Y-m-d H:i'),
                            ucfirst($b->product_type),
                            $b->user?->name ?? ($b->contact['first_name'] ?? 'Guest'),
                            ucfirst($b->status),
                            number_format($paid, 2, '.', ''),
                            number_format($cost, 2, '.', ''),
                            number_format($tax, 2, '.', ''),
                            number_format($gwFee, 2, '.', ''),
                            number_format($margin, 2, '.', ''),
                        ]);
                    }
                });

            fclose($out);
        }, 'financial-ledger-' . now()->format('Ymd') . '.csv', ['Content-Type' => 'text/csv']);
    }

    protected function range(Request $request): array
    {
        $range = $request->query('range', '30_days');

        $from = match ($range) {
            'today' => today(),
            'yesterday' => today()->subDay(),
            '7_days' => today()->subDays(6),
            'this_month' => today()->startOfMonth(),
            'custom' => \Carbon\Carbon::parse($request->query('from', today())),
            default => today()->subDays(29),
        };

        $to = $range === 'custom' ? \Carbon\Carbon::parse($request->query('to', today())) : today();

        return [$from->copy()->startOfDay(), $to->copy()->endOfDay(), $range];
    }
}
