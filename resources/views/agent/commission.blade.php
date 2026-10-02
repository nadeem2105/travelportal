@extends('layouts.site')

@section('page')
<x-agent.shell agentNav="commission">
    <div class="mb-6">
        <h1 class="font-display text-2xl font-bold">Commission Report</h1>
        <p class="mt-1 text-sm text-ink-500">Commission earned on your confirmed bookings.</p>
    </div>

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Total Business</p>
            <p class="mt-2 text-2xl font-bold text-ink-900">{{ money($totalBusiness, true) }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Total Commission</p>
            <p class="mt-2 text-2xl font-bold text-emerald-600">{{ money($totalCommission, true) }}</p>
        </div>
        <div class="card p-5">
            <p class="text-xs font-semibold uppercase tracking-wide text-ink-500">Confirmed Bookings</p>
            <p class="mt-2 text-2xl font-bold text-ink-900">{{ $bookings->count() }}</p>
        </div>
    </div>

    <div class="card mt-6 overflow-hidden">
        <div class="border-b border-ink-100 p-5">
            <h2 class="font-display text-lg font-bold">Month-wise Summary</h2>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-ink-50 text-left text-xs font-semibold uppercase tracking-wide text-ink-500">
                    <tr>
                        <th class="px-5 py-3">Month</th>
                        <th class="px-5 py-3 text-right">Bookings</th>
                        <th class="px-5 py-3 text-right">Business</th>
                        <th class="px-5 py-3 text-right">Commission</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-ink-100">
                    @forelse ($byMonth as $row)
                        <tr>
                            <td class="px-5 py-3 font-semibold text-ink-900">{{ $row['month'] }}</td>
                            <td class="px-5 py-3 text-right text-ink-600">{{ $row['count'] }}</td>
                            <td class="px-5 py-3 text-right text-ink-700">{{ money($row['business'], true) }}</td>
                            <td class="px-5 py-3 text-right font-semibold text-emerald-600">{{ money($row['commission'], true) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="4" class="px-5 py-12 text-center text-ink-400">No confirmed bookings yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-agent.shell>
@endsection
