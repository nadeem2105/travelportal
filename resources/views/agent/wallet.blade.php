@extends('layouts.site')

@section('page')
<x-agent.shell agentNav="wallet">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold">Wallet &amp; Credit Ledger</h1>
        <p class="mt-1 text-sm text-ink-500">Your account balances and transaction history.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Wallet Balance</p>
            <p class="mt-2 text-2xl font-bold text-ink-900">{{ money($agent->wallet_balance, true) }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Credit Limit</p>
            <p class="mt-2 text-2xl font-bold text-ink-900">{{ money($agent->credit_limit, true) }}</p>
            <p class="mt-1 text-xs text-ink-400">Used {{ money($agent->credit_balance, true) }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Available Credit</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600">{{ money($agent->availableCredit(), true) }}</p>
        </div>
    </div>

    <div class="mt-4 rounded-xl border border-ink-100 bg-ink-50/50 p-4 text-xs text-ink-500">
        To top up your wallet or increase your credit limit, please contact our accounts team.
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="border-b border-ink-100 p-5">
            <h2 class="font-display text-lg font-bold">Transaction History</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-ink-50 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3">Date</th>
                        <th class="px-5 py-3">Type</th>
                        <th class="px-5 py-3">Reference</th>
                        <th class="px-5 py-3 text-right">Amount</th>
                        <th class="px-5 py-3 text-right">Balance After</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($transactions as $txn)
                        <tr>
                            <td class="whitespace-nowrap px-5 py-3 text-ink-600">{{ $txn->created_at->format('d M Y, H:i') }}</td>
                            <td class="px-5 py-3 font-semibold text-ink-900">{{ label_case($txn->type) }}</td>
                            <td class="px-5 py-3 text-ink-500">{{ $txn->reference ?: ($txn->booking_id ? '#'.$txn->booking_id : '—') }}</td>
                            <td class="px-5 py-3 text-right font-bold {{ in_array($txn->type, ['deposit', 'booking_credit', 'refund', 'commission']) ? 'text-emerald-600' : 'text-rose-600' }}">
                                {{ in_array($txn->type, ['deposit', 'booking_credit', 'refund', 'commission']) ? '+' : '−' }}{{ money($txn->amount, true) }}
                            </td>
                            <td class="px-5 py-3 text-right text-ink-700">{{ money($txn->balance_after, true) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-10 text-center text-ink-400">No transactions yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($transactions->hasPages())
            <div class="border-t border-ink-100 p-4">{{ $transactions->links() }}</div>
        @endif
    </div>
</x-agent.shell>
@endsection
