@extends('layouts.admin')
@section('pageTitle', 'B2B Travel Agents')

@section('content')
    <div class="flex items-center justify-between">
        <div>
            <h1 class="font-display text-xl font-bold">B2B Travel Agents</h1>
            <p class="text-xs text-ink-500">Manage B2B travel partners, agent wallets, credit limits and commissions</p>
        </div>
        <a href="{{ route('admin.agents.create') }}" class="btn-primary btn-md">+ Register New Agent</a>
    </div>

    <div class="mt-4 flex flex-wrap gap-2">
        <a href="{{ route('admin.agents.index') }}" class="btn-sm {{ !$status ? 'btn-primary' : 'btn-ghost' }}">All</a>
        <a href="{{ route('admin.agents.index', ['status' => 'approved']) }}" class="btn-sm {{ $status === 'approved' ? 'btn-primary' : 'btn-ghost' }}">Approved</a>
        <a href="{{ route('admin.agents.index', ['status' => 'pending']) }}" class="btn-sm {{ $status === 'pending' ? 'btn-primary' : 'btn-ghost' }}">Pending Approval</a>
        <a href="{{ route('admin.agents.index', ['status' => 'suspended']) }}" class="btn-sm {{ $status === 'suspended' ? 'btn-primary' : 'btn-ghost' }}">Suspended</a>
    </div>

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>Agency / Code</th>
                    <th>Contact Person</th>
                    <th>Phone / Email</th>
                    <th>Wallet Balance</th>
                    <th>Credit Limit</th>
                    <th>Status</th>
                    <th class="text-right">Action</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($agents as $agent)
                    <tr>
                        <td>
                            <a href="{{ route('admin.agents.show', $agent) }}" class="font-bold text-brand-600 hover:underline">
                                {{ $agent->agency_name }}
                            </a>
                            <div class="text-[11px] font-mono text-ink-400">{{ $agent->agency_code }}</div>
                        </td>
                        <td>{{ $agent->contact_person }}</td>
                        <td>
                            <div>{{ $agent->phone }}</div>
                            <div class="text-[11px] text-ink-400">{{ $agent->email }}</div>
                        </td>
                        <td class="font-bold text-emerald-600">₹{{ number_format($agent->wallet_balance, 2) }}</td>
                        <td>₹{{ number_format($agent->credit_limit, 2) }}</td>
                        <td>
                            <span class="status-pill {{ $agent->status === 'approved' ? 'bg-emerald-100 text-emerald-700' : ($agent->status === 'pending' ? 'bg-amber-100 text-amber-700' : 'bg-rose-100 text-rose-700') }}">
                                {{ ucfirst($agent->status) }}
                            </span>
                        </td>
                        <td class="text-right">
                            <a href="{{ route('admin.agents.show', $agent) }}" class="btn-ghost btn-sm">Manage</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500 py-6">No agents registered yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $agents->links() }}</div>
@endsection
