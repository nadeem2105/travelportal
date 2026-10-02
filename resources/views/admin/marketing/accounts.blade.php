@extends('layouts.admin')
@section('pageTitle', 'Ad Accounts')

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">Ad Accounts</h1>
        <p class="text-xs text-ink-500">Connect Google Ads and Meta Ads via official OAuth. Credentials are encrypted; tokens are never exposed.</p>
    </div>
    <div class="flex items-center gap-2">
        <a href="{{ route('admin.marketing.settings') }}" class="btn-ghost btn-sm">⚙ Manage credentials</a>
        <a href="{{ route('admin.marketing.overview') }}" class="btn-ghost btn-sm">← Dashboard</a>
    </div>
</div>

<div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
    @foreach ($providers as $key => $p)
        <div class="admin-card p-5">
            <div class="mb-3 flex items-center justify-between">
                <h2 class="text-sm font-bold">{{ $p['label'] }}</h2>
                @if ($p['enabled'])
                    <span class="status-pill bg-emerald-100 text-emerald-700">Configured</span>
                @else
                    <span class="status-pill bg-amber-100 text-amber-700">Not configured</span>
                @endif
            </div>

            @unless ($p['enabled'])
                <p class="mb-3 text-xs text-ink-500">Not enabled yet. <a href="{{ route('admin.marketing.settings') }}" class="text-brand-600 underline">Add credentials</a> and turn it on — everything is stored (encrypted) in the database, no <code>.env</code> needed.</p>
            @endunless

            @if ($p['connections']->isEmpty())
                <a href="{{ $p['enabled'] ? route('admin.marketing.connect', $key) : '#' }}"
                   class="btn-primary btn-sm {{ $p['enabled'] ? '' : 'pointer-events-none opacity-50' }}">Connect {{ $p['label'] }}</a>
            @else
                <div class="space-y-3">
                    @foreach ($p['connections'] as $conn)
                        <div class="rounded-lg border p-3">
                            <div class="flex items-center justify-between">
                                <div>
                                    <div class="text-sm font-semibold">{{ $conn->name }}</div>
                                    <div class="text-[11px] text-ink-400">
                                        @if ($conn->status === 'connected' && ! $conn->isExpired())
                                            <span class="font-medium text-emerald-600">● Connected</span>
                                        @elseif ($conn->status === 'connected' && $conn->isExpired())
                                            <span class="font-medium text-amber-600">● Connected (token expired)</span>
                                        @else
                                            <span class="font-medium text-ink-500 capitalize">○ {{ $conn->status }}</span>
                                        @endif
                                        @if ($conn->token_expires_at) · expires {{ $conn->token_expires_at->diffForHumans() }} @endif
                                        @if ($conn->last_synced_at) · synced {{ $conn->last_synced_at->diffForHumans() }} @endif
                                    </div>
                                </div>
                                <div class="flex items-center gap-1">
                                    <form action="{{ route('admin.marketing.connections.check', $conn) }}" method="POST" class="inline">@csrf
                                        <button class="btn-ghost btn-xs">Check</button>
                                    </form>
                                    <form action="{{ route('admin.marketing.connections.sync', $conn) }}" method="POST" class="inline">@csrf
                                        <button class="btn-ghost btn-xs">Sync accounts</button>
                                    </form>
                                    <a href="{{ route('admin.marketing.connect', $key) }}" class="btn-ghost btn-xs">Reconnect</a>
                                    <form action="{{ route('admin.marketing.connections.disconnect', $conn) }}" method="POST" class="inline" onsubmit="return confirm('Disconnect this account?');">@csrf
                                        <button class="btn-ghost btn-xs text-rose-600">Disconnect</button>
                                    </form>
                                </div>
                            </div>

                            @if ($conn->accounts->isNotEmpty())
                                <table class="admin-table mt-2">
                                    <thead><tr><th>Account</th><th>External ID</th><th>Currency</th></tr></thead>
                                    <tbody>
                                        @foreach ($conn->accounts as $acc)
                                            <tr>
                                                <td class="text-xs">{{ $acc->account_name }}</td>
                                                <td class="font-mono text-xs">{{ $acc->external_account_id }}</td>
                                                <td class="text-xs">{{ $acc->currency ?? '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            @else
                                <p class="mt-2 text-[11px] text-ink-400">No accounts imported yet — click "Sync accounts".</p>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>
    @endforeach
</div>
@endsection
