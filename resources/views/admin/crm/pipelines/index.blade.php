@extends('layouts.admin')
@section('pageTitle', 'CRM Pipelines')

@section('content')
    <div>
        <h1 class="font-display text-xl font-bold">Pipelines</h1>
        <p class="text-xs text-ink-500">Sales stages leads move through. Read-only overview.</p>
    </div>

    <div class="mt-6 space-y-6">
        @forelse ($pipelines as $pipeline)
            <div class="admin-card p-5">
                <div class="mb-4 flex flex-wrap items-center gap-2">
                    <h2 class="font-display text-base font-bold">{{ $pipeline->name }}</h2>
                    @if ($pipeline->is_default)<span class="status-pill bg-brand-100 text-brand-700">Default</span>@endif
                    <span class="status-pill {{ $pipeline->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-500' }}">{{ $pipeline->is_active ? 'Active' : 'Inactive' }}</span>
                    <span class="text-xs text-ink-400">{{ $pipeline->stages->count() }} stage(s)</span>
                </div>

                <div class="overflow-x-auto">
                    <table class="admin-table">
                        <thead>
                            <tr>
                                <th class="w-10">#</th>
                                <th>Stage</th>
                                <th class="text-center">Probability</th>
                                <th class="text-center">Won / Lost</th>
                                <th class="text-center">Open leads</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse ($pipeline->stages as $stage)
                                <tr>
                                    <td class="text-ink-400">{{ $stage->sort_order }}</td>
                                    <td>
                                        <span class="inline-flex items-center gap-2">
                                            @if ($stage->color)<span class="inline-block h-2.5 w-2.5 rounded-full" style="background-color: {{ $stage->color }};"></span>@endif
                                            <span class="font-semibold text-ink-800">{{ $stage->name }}</span>
                                        </span>
                                    </td>
                                    <td class="text-center">{{ $stage->probability !== null ? $stage->probability . '%' : '—' }}</td>
                                    <td class="text-center">
                                        @if ($stage->is_won)<span class="status-pill bg-emerald-100 text-emerald-700">Won</span>
                                        @elseif ($stage->is_lost)<span class="status-pill bg-rose-100 text-rose-700">Lost</span>
                                        @else <span class="text-ink-400">—</span>@endif
                                    </td>
                                    <td class="text-center font-semibold">{{ $stage->leads_count ?? 0 }}</td>
                                </tr>
                            @empty
                                <tr><td colspan="5" class="py-4 text-center text-ink-500">No stages configured.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @empty
            <div class="admin-card p-8 text-center text-ink-500">No pipelines configured yet.</div>
        @endforelse
    </div>
@endsection
