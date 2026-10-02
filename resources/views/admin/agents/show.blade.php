@extends('layouts.admin')
@section('pageTitle', $agent->agency_name . ' · B2B Agent')

@section('content')
    <a href="{{ route('admin.agents.index') }}" class="text-xs text-brand-600 hover:underline">← Back to Agents</a>

    <div class="mt-2 flex flex-wrap items-center justify-between gap-4">
        <div>
            <h1 class="font-display text-xl font-bold">{{ $agent->agency_name }}</h1>
            <div class="text-xs text-ink-500">Code: <span class="font-mono font-bold">{{ $agent->agency_code }}</span> · Contact: {{ $agent->contact_person }} ({{ $agent->phone }} · {{ $agent->email }})</div>
        </div>
        <div class="flex items-center gap-2">
            <form action="{{ route('admin.agents.status', $agent) }}" method="POST" class="flex gap-2">
                @csrf
                <select name="status" class="input py-1 text-xs">
                    <option value="approved" {{ $agent->status === 'approved' ? 'selected' : '' }}>Approved</option>
                    <option value="pending" {{ $agent->status === 'pending' ? 'selected' : '' }}>Pending</option>
                    <option value="suspended" {{ $agent->status === 'suspended' ? 'selected' : '' }}>Suspended</option>
                    <option value="rejected" {{ $agent->status === 'rejected' ? 'selected' : '' }}>Rejected</option>
                </select>
                <button class="btn-primary btn-sm">Update Status</button>
            </form>
        </div>
    </div>

    @if ($agent->status === 'pending')
        <div class="mt-4 rounded-lg border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            <strong>Application pending review.</strong> Applied {{ $agent->applied_at?->format('d M Y, h:i A') ?? $agent->created_at->format('d M Y') }}. Approve above to let this agent sign in.
        </div>
    @endif
    @if ($agent->status === 'rejected' && $agent->rejection_reason)
        <div class="mt-4 rounded-lg border border-rose-200 bg-rose-50 p-4 text-sm text-rose-800">
            <strong>Rejected:</strong> {{ $agent->rejection_reason }}
        </div>
    @endif

    {{-- KYC documents --}}
    @if ($agent->pan_document || $agent->gst_document || $agent->logo_path)
        <div class="mt-4 admin-card p-4">
            <h3 class="font-bold text-sm mb-3">KYC Documents</h3>
            <div class="flex flex-wrap gap-3 text-xs">
                @if ($agent->logo_path)
                    <a href="{{ \Storage::disk('public')->url($agent->logo_path) }}" target="_blank" class="text-brand-600 hover:underline">View Logo</a>
                @endif
                @if ($agent->pan_document)
                    <a href="{{ \Storage::disk('public')->url($agent->pan_document) }}" target="_blank" class="text-brand-600 hover:underline">View PAN Document</a>
                @endif
                @if ($agent->gst_document)
                    <a href="{{ \Storage::disk('public')->url($agent->gst_document) }}" target="_blank" class="text-brand-600 hover:underline">View GST Document</a>
                @endif
                @if ($agent->business_license)
                    <a href="{{ \Storage::disk('public')->url($agent->business_license) }}" target="_blank" class="text-brand-600 hover:underline">View Business License</a>
                @endif
            </div>
            <div class="mt-2 text-xs text-ink-500">
                PAN: {{ $agent->pan_number ?? '—' }} · GST: {{ $agent->gst_number ?? '—' }}
            </div>
        </div>
    @endif

    <div class="mt-4 grid grid-cols-1 md:grid-cols-3 gap-4">
        <div class="admin-card p-4">
            <div class="text-xs text-ink-400">Wallet Balance</div>
            <div class="text-2xl font-bold text-emerald-600">₹{{ number_format($agent->wallet_balance, 2) }}</div>
        </div>
        <div class="admin-card p-4">
            <div class="text-xs text-ink-400">Credit Limit</div>
            <div class="text-2xl font-bold text-ink-800">₹{{ number_format($agent->credit_limit, 2) }}</div>
        </div>
        <div class="admin-card p-4">
            <div class="text-xs text-ink-400">Total Purchasing Power</div>
            <div class="text-2xl font-bold text-brand-600">₹{{ number_format($agent->totalPurchasingPower(), 2) }}</div>
        </div>
    </div>

    <div class="mt-6 grid grid-cols-1 md:grid-cols-3 gap-6">
        <div class="admin-card p-4 h-fit">
            <h3 class="font-bold text-sm mb-3">Adjust Wallet / Credit</h3>
            <form action="{{ route('admin.agents.balance', $agent) }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="label">Adjustment Type</label>
                    <select name="type" class="input">
                        <option value="deposit">Deposit to Wallet (+)</option>
                        <option value="credit_adjustment">Increase Credit Limit (+)</option>
                    </select>
                </div>
                <div>
                    <label class="label">Amount (₹)</label>
                    <input type="number" step="0.01" name="amount" class="input" required min="1" placeholder="e.g. 50000">
                </div>
                <div>
                    <label class="label">Reason / Reference Notes</label>
                    <input type="text" name="notes" class="input" required placeholder="Bank transfer Ref #, NEFT...">
                </div>
                <button class="btn-primary btn-md w-full">Apply Adjustment</button>
            </form>

            <hr class="my-4 border-ink-100">

            <h3 class="font-bold text-sm mb-3">Set / Reset Login Password</h3>
            <form action="{{ route('admin.agents.password', $agent) }}" method="POST" class="space-y-3">
                @csrf
                <div>
                    <label class="label">New Password</label>
                    <input type="password" name="password" class="input" required minlength="8" placeholder="Min. 8 characters">
                </div>
                <div>
                    <label class="label">Confirm Password</label>
                    <input type="password" name="password_confirmation" class="input" required minlength="8">
                </div>
                <button class="btn-ghost btn-md w-full">Update Password</button>
                <p class="text-[11px] text-ink-400">Use this to onboard an agent or help a locked-out agent regain access.</p>
            </form>
        </div>

        <div class="admin-card md:col-span-2 overflow-x-auto">
            <h3 class="font-bold text-sm p-4 border-b">Financial Transaction Ledger</h3>
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Amount</th>
                        <th>Balance After</th>
                        <th>Notes</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($transactions as $tx)
                        <tr>
                            <td class="text-xs text-ink-500">{{ $tx->created_at->format('d M Y, h:i A') }}</td>
                            <td><span class="status-pill bg-slate-100 text-slate-700">{{ label_case($tx->type) }}</span></td>
                            <td class="font-bold {{ in_array($tx->type, ['deposit', 'booking_credit', 'refund']) ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ in_array($tx->type, ['deposit', 'booking_credit', 'refund']) ? '+' : '-' }}₹{{ number_format($tx->amount, 2) }}
                            </td>
                            <td class="font-mono text-xs font-bold">₹{{ number_format($tx->balance_after, 2) }}</td>
                            <td class="text-xs text-ink-500">{{ $tx->notes ?? '—' }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="text-center text-ink-500 py-6">No ledger transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <div class="p-3">{{ $transactions->links() }}</div>
        </div>
    </div>
@endsection
