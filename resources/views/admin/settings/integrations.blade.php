@extends('layouts.admin')
@section('pageTitle', 'Integrations')

@section('content')
<div class="flex items-center justify-between">
    <div>
        <h1 class="font-display text-xl font-bold">Integrations</h1>
        <p class="text-xs text-ink-500">Third-party providers at a glance. Credentials are stored encrypted in the database and managed from each provider's settings page — <code>.env</code> is only a fallback.</p>
    </div>
</div>

<div class="mt-5 grid grid-cols-1 gap-4 md:grid-cols-2">
    @foreach ($integrations as $i)
        @php
            if (! ($i['configured'] ?? false)) {
                $badge = ['Not configured', 'bg-slate-100 text-slate-500'];
            } elseif ($i['enabled'] ?? false) {
                $badge = ['Live', 'bg-emerald-50 text-emerald-700'];
            } else {
                $badge = ['Configured · Off', 'bg-amber-50 text-amber-700'];
            }
        @endphp
        <a href="{{ route($i['route']) }}" class="admin-card block p-5 transition hover:shadow-md">
            <div class="flex items-start justify-between gap-3">
                <div>
                    <h2 class="text-sm font-bold text-ink-900">{{ $i['name'] }}</h2>
                    <p class="mt-0.5 text-xs text-ink-500">{{ $i['desc'] }}</p>
                </div>
                <span class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold {{ $badge[1] }}">{{ $badge[0] }}</span>
            </div>

            <div class="mt-3 flex flex-wrap items-center gap-2 text-[11px]">
                <span class="rounded px-2 py-0.5 font-medium {{ ($i['in_db'] ?? false) ? 'bg-brand-50 text-brand-700' : 'bg-amber-50 text-amber-700' }}">
                    {{ ($i['in_db'] ?? false) ? 'Stored in database' : 'Using .env fallback' }}
                </span>
                @if (! empty($i['detail']))
                    <span class="text-ink-400">{{ $i['detail'] }}</span>
                @endif
            </div>

            @if (! empty($i['warn']))
                <p class="mt-2 rounded-lg bg-amber-50 px-3 py-1.5 text-[11px] text-amber-700">{{ $i['warn'] }}</p>
            @endif

            <div class="mt-3 text-xs font-semibold text-brand-700">Manage &rarr;</div>
        </a>
    @endforeach
</div>

<p class="mt-5 text-[11px] text-ink-400">
    "Stored in database" means the provider's credentials have been saved from the admin panel (encrypted at rest).
    "Using .env fallback" means it is still reading from <code>.env</code> — save it once from the settings page to move it into the database.
    To migrate existing <code>.env</code> credentials in bulk, run <code>php artisan integration:import-env</code>.
</p>
@endsection
