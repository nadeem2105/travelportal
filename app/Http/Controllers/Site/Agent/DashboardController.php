<?php

namespace App\Http\Controllers\Site\Agent;

use App\Http\Controllers\Controller;

class DashboardController extends Controller
{
    public function index()
    {
        $agent = auth('agent')->user();

        $bookings = $agent->bookings();

        $stats = [
            'wallet_balance' => (float) $agent->wallet_balance,
            'available_credit' => $agent->availableCredit(),
            'purchasing_power' => $agent->totalPurchasingPower(),
            'total_bookings' => (clone $bookings)->count(),
            'confirmed_bookings' => (clone $bookings)->where('status', 'confirmed')->count(),
            'total_commission' => (float) (clone $bookings)->sum('agent_commission'),
        ];

        $recentBookings = $agent->bookings()->latest()->limit(5)->get();
        $recentTransactions = $agent->transactions()->latest()->limit(5)->get();

        return view('agent.dashboard', [
            'seo' => ['title' => 'Agent Dashboard'],
            'agent' => $agent,
            'stats' => $stats,
            'recentBookings' => $recentBookings,
            'recentTransactions' => $recentTransactions,
        ]);
    }
}
