<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Payment;
use App\Models\Refund;
use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $bookingsToday = Booking::whereDate('created_at', today());

        $stats = [
            'total_bookings' => Booking::count(),
            'bookings_today' => $bookingsToday->count(),
            'revenue' => (float) Booking::whereIn('status', ['confirmed', 'completed'])->sum('total_amount'),
            'revenue_month' => (float) Booking::whereIn('status', ['confirmed', 'completed'])->whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->sum('total_amount'),
            'pending' => Booking::whereIn('status', ['pending', 'payment_pending'])->count(),
            'confirmed' => Booking::where('status', 'confirmed')->count(),
            'cancelled' => Booking::where('status', 'cancelled')->count(),
            'refunds_pending' => Refund::whereIn('status', ['requested', 'initiated'])->count(),
            'reconciliation' => Booking::where('status', 'payment_success_booking_failed')->count(),
            'customers' => User::count(),
            'open_tickets' => SupportTicket::whereIn('status', ['open', 'in_progress'])->count(),
            'unread_messages' => ContactMessage::where('is_read', false)->count(),
        ];

        $productWise = [
            'flight' => Booking::where('product_type', 'flight')->count(),
            'hotel' => Booking::where('product_type', 'hotel')->count(),
            'cab' => Booking::where('product_type', 'cab')->count(),
            'package' => Booking::where('product_type', 'package')->count(),
        ];

        $monthly = Booking::select(
            DB::raw("DATE_FORMAT(created_at, '%b %Y') as month"),
            DB::raw('COUNT(*) as bookings'),
            DB::raw('SUM(total_amount) as revenue')
        )
            ->where('created_at', '>=', now()->subMonths(11)->startOfMonth())
            ->groupBy('month')
            ->orderByRaw('MIN(created_at)')
            ->get();

        $recentBookings = Booking::with('user')->latest()->limit(8)->get();
        $recentTickets = SupportTicket::latest()->limit(5)->get();

        return view('admin.dashboard', compact('stats', 'productWise', 'monthly', 'recentBookings', 'recentTickets'));
    }
}
