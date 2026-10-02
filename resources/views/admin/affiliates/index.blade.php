@extends('layouts.admin')
@section('pageTitle', 'Affiliate & Referral Marketing')

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-display text-xl font-bold">Affiliate & Referral Marketing</h1>
            <p class="text-xs text-ink-500">Track partner referral links, click conversions and commission payouts</p>
        </div>
    </div>

    <div class="admin-card mt-4 overflow-x-auto">
        <h3 class="font-bold text-sm p-4 border-b">Affiliate Partners</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Affiliate User</th>
                    <th>Code / Link</th>
                    <th>Clicks</th>
                    <th>Bookings</th>
                    <th>Commission Rate</th>
                    <th>Total Earned</th>
                    <th>Pending Payout</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($affiliates as $aff)
                    <tr>
                        <td>
                            <div class="font-bold">{{ $aff->user?->name ?? 'User #' . $aff->user_id }}</div>
                            <div class="text-[11px] text-ink-400">{{ $aff->user?->email }}</div>
                        </td>
                        <td>
                            <span class="font-mono font-bold bg-slate-100 px-2 py-0.5 rounded text-xs">{{ $aff->affiliate_code }}</span>
                        </td>
                        <td>{{ $aff->clicks_count }}</td>
                        <td>{{ $aff->commissions_count }}</td>
                        <td>{{ $aff->commission_percent }}%</td>
                        <td class="font-bold text-ink-800">₹{{ number_format($aff->total_earnings, 2) }}</td>
                        <td class="font-bold text-amber-600">₹{{ number_format($aff->pendingEarnings(), 2) }}</td>
                        <td>
                            <span class="status-pill {{ $aff->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ ucfirst($aff->status) }}
                            </span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-ink-500 py-6">No affiliate partners registered yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="admin-card mt-6 overflow-x-auto">
        <h3 class="font-bold text-sm p-4 border-b">Referral Commissions</h3>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Date</th>
                    <th>Affiliate</th>
                    <th>Booking Reference</th>
                    <th>Booking Amount</th>
                    <th>Commission</th>
                    <th>Status</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($commissions as $comm)
                    <tr>
                        <td class="text-xs text-ink-500">{{ $comm->created_at->format('d M Y') }}</td>
                        <td>{{ $comm->affiliate?->user?->name ?? $comm->affiliate?->affiliate_code }}</td>
                        <td class="font-mono text-xs font-bold">{{ $comm->booking?->booking_reference }}</td>
                        <td>₹{{ number_format($comm->booking_amount, 2) }}</td>
                        <td class="font-bold text-emerald-600">₹{{ number_format($comm->commission_amount, 2) }}</td>
                        <td>
                            <span class="status-pill {{ $comm->status === 'paid' ? 'bg-emerald-100 text-emerald-700' : 'bg-amber-100 text-amber-700' }}">
                                {{ ucfirst($comm->status) }}
                            </span>
                        </td>
                        <td class="text-right">
                            @if ($comm->status !== 'paid')
                                <form action="{{ route('admin.affiliates.pay', $comm) }}" method="POST" class="inline">
                                    @csrf
                                    <button class="btn-primary btn-sm">Mark Paid</button>
                                </form>
                            @else
                                <span class="text-xs text-ink-400">Paid {{ $comm->paid_at?->format('d M') }}</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500 py-6">No referral commissions recorded yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
