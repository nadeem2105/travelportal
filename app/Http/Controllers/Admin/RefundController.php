<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\Refund;
use App\Services\ActivityLogger;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RefundController extends Controller
{
    public function index(Request $request)
    {
        $perPage = in_array((int) $request->query('per_page'), [15, 25, 50, 100]) ? (int) $request->query('per_page') : 15;
        $q = trim((string) $request->query('q'));

        $sortable = ['amount', 'status', 'created_at'];
        $sortCol = $request->query('sort_col');
        $sortDir = $request->query('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $refunds = Refund::with(['booking.user', 'payment'])
            ->when($request->query('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($request->query('from'), fn ($query, $v) => $query->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($query, $v) => $query->whereDate('created_at', '<=', $v))
            ->when($q, fn ($query) => $query->whereHas('booking', fn ($b) => $b->where('booking_reference', 'like', "%{$q}%")))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->latest())
            ->paginate($perPage)->withQueryString();

        return view('admin.refunds.index', compact('refunds'));
    }

    public function export(Request $request): StreamedResponse
    {
        $q = trim((string) $request->query('q'));

        $sortable = ['amount', 'status', 'created_at'];
        $sortCol = $request->query('sort_col');
        $sortDir = $request->query('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'refunds-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'refunds', 'Exported refunds CSV');

        return response()->streamDownload(function () use ($request, $q, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Booking Reference', 'Amount', 'Penalty', 'Status', 'Created At']);

            Refund::with('booking')
                ->when($request->query('status'), fn ($query, $v) => $query->where('status', $v))
                ->when($request->query('from'), fn ($query, $v) => $query->whereDate('created_at', '>=', $v))
                ->when($request->query('to'), fn ($query, $v) => $query->whereDate('created_at', '<=', $v))
                ->when($q, fn ($query) => $query->whereHas('booking', fn ($b) => $b->where('booking_reference', 'like', "%{$q}%")))
                ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
                ->when(! $applySort, fn ($query) => $query->latest())
                ->chunk(500, function ($refunds) use ($out) {
                    foreach ($refunds as $refund) {
                        fputcsv($out, [
                            $refund->id,
                            $refund->booking?->booking_reference ?? '',
                            $refund->amount,
                            $refund->penalty_amount,
                            $refund->status,
                            $refund->created_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function payments(Request $request)
    {
        $perPage = in_array((int) $request->query('per_page'), [15, 25, 50, 100]) ? (int) $request->query('per_page') : 15;
        $q = trim((string) $request->query('q'));

        $sortable = ['amount', 'status', 'created_at'];
        $sortCol = $request->query('sort_col');
        $sortDir = $request->query('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $payments = Payment::with('booking')
            ->when($request->query('status'), fn ($query, $v) => $query->where('status', $v))
            ->when($request->query('gateway'), fn ($query, $v) => $query->where('gateway', $v))
            ->when($request->query('from'), fn ($query, $v) => $query->whereDate('created_at', '>=', $v))
            ->when($request->query('to'), fn ($query, $v) => $query->whereDate('created_at', '<=', $v))
            ->when($q, fn ($query) => $query->where(fn ($w) => $w
                ->where('gateway_order_id', 'like', "%{$q}%")
                ->orWhere('gateway_payment_id', 'like', "%{$q}%")
                ->orWhereHas('booking', fn ($b) => $b->where('booking_reference', 'like', "%{$q}%"))))
            ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
            ->when(! $applySort, fn ($query) => $query->latest())
            ->paginate($perPage)
            ->withQueryString();

        $gateways = \App\Models\PaymentGateway::orderBy('sort_order')->pluck('name', 'code')->all();

        return view('admin.payments.index', compact('payments', 'gateways'));
    }

    public function exportPayments(Request $request): StreamedResponse
    {
        $q = trim((string) $request->query('q'));

        $sortable = ['amount', 'status', 'created_at'];
        $sortCol = $request->query('sort_col');
        $sortDir = $request->query('sort_dir') === 'desc' ? 'desc' : 'asc';
        $applySort = $sortCol && in_array($sortCol, $sortable, true);

        $filename = 'payments-' . now()->format('Ymd-His') . '.csv';

        ActivityLogger::log('export', 'payments', 'Exported payments CSV');

        return response()->streamDownload(function () use ($request, $q, $applySort, $sortCol, $sortDir) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Booking Reference', 'Gateway', 'Order ID', 'Payment ID', 'Amount', 'Status', 'Paid At']);

            Payment::with('booking')
                ->when($request->query('status'), fn ($query, $v) => $query->where('status', $v))
                ->when($request->query('gateway'), fn ($query, $v) => $query->where('gateway', $v))
                ->when($request->query('from'), fn ($query, $v) => $query->whereDate('created_at', '>=', $v))
                ->when($request->query('to'), fn ($query, $v) => $query->whereDate('created_at', '<=', $v))
                ->when($q, fn ($query) => $query->where(fn ($w) => $w
                    ->where('gateway_order_id', 'like', "%{$q}%")
                    ->orWhere('gateway_payment_id', 'like', "%{$q}%")
                    ->orWhereHas('booking', fn ($b) => $b->where('booking_reference', 'like', "%{$q}%"))))
                ->when($applySort, fn ($query) => $query->orderBy($sortCol, $sortDir))
                ->when(! $applySort, fn ($query) => $query->latest())
                ->chunk(500, function ($payments) use ($out) {
                    foreach ($payments as $payment) {
                        fputcsv($out, [
                            $payment->id,
                            $payment->booking?->booking_reference ?? '',
                            $payment->gateway,
                            $payment->gateway_order_id ?? '',
                            $payment->gateway_payment_id ?? '',
                            $payment->amount,
                            $payment->status,
                            $payment->paid_at?->toDateTimeString(),
                        ]);
                    }
                });

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function process(Refund $refund)
    {
        if ($refund->status !== 'initiated') {
            return back()->with('error', 'Only initiated refunds can be processed. Approve the cancellation first.');
        }

        $result = app(BookingService::class)->processRefund($refund, auth('admin')->id());

        ActivityLogger::log('refund', 'refunds', "Refund {$refund->id} for {$refund->booking->booking_reference}: " . ($result['success'] ? 'processed' : 'failed'));

        return $result['success']
            ? back()->with('success', 'Refund processed successfully.')
            : back()->with('error', $result['error'] ?? 'Refund failed at gateway.');
    }

    public function reject(Refund $refund)
    {
        $refund->update(['status' => 'rejected', 'processed_by' => auth('admin')->id(), 'processed_at' => now()]);

        $refund->booking->update(['cancellation_status' => 'rejected', 'refund_status' => 'rejected']);

        ActivityLogger::log('refund_reject', 'refunds', "Refund {$refund->id} for {$refund->booking->booking_reference} rejected");

        return back()->with('success', 'Refund request rejected.');
    }

    /**
     * Reconciliation dashboard: payment succeeded but supplier booking failed.
     */
    public function reconciliation(Request $request)
    {
        $perPage = in_array((int) $request->query('per_page'), [15, 25, 50, 100]) ? (int) $request->query('per_page') : 15;

        $cases = Booking::with(['payments', 'supplier'])
            ->where('status', 'payment_success_booking_failed')
            ->when($request->query('type'), fn ($query, $v) => $query->where('product_type', $v))
            ->latest()
            ->paginate($perPage)->withQueryString();

        return view('admin.reconciliation', compact('cases'));
    }
}
