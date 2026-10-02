@extends('layouts.admin')
@section('pageTitle', 'Suppliers & APIs')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-display text-xl font-bold">Suppliers & API Integrations</h1>
    </div>
    <p class="mt-1 text-sm text-ink-500">Credentials are encrypted at rest. Add an adapter (e.g. Amadeus, TBO) to connect a live API — the platform routes searches by priority automatically.</p>

    <div class="mt-4 space-y-4">
        @forelse ($suppliers as $supplier)
            <details class="admin-card" @if($loop->first) open @endif>
                <summary class="flex cursor-pointer list-none flex-wrap items-center justify-between gap-2">
                    <span class="font-display font-bold">{{ $supplier->name }}
                        <span class="badge-soft ml-1 !text-[10px] uppercase">{{ $supplier->type }}</span>
                        <span class="ml-1 text-xs font-medium text-ink-500">adapter: {{ $supplier->adapter }}</span>
                    </span>
                    <span class="flex items-center gap-2">
                        <span class="status-pill {{ status_pill_class($supplier->status) }}">{{ ucfirst($supplier->status) }}</span>
                        <span class="text-xs text-ink-500">priority {{ $supplier->priority }}</span>
                    </span>
                </summary>

                <div class="mt-4 grid gap-4 lg:grid-cols-2">
                    {{-- Settings --}}
                    <form action="{{ route('admin.suppliers.update', $supplier) }}" method="POST" class="space-y-3">
                        @csrf @method('PUT')
                        <div class="grid gap-3 sm:grid-cols-2">
                            <div><label class="label">Name</label><input type="text" name="name" class="input" value="{{ $supplier->name }}" required></div>
                            <div>
                                <label class="label">Environment</label>
                                <select name="environment" class="input">
                                    <option value="test" @selected($supplier->environment === 'test')>Test / Sandbox</option>
                                    <option value="production" @selected($supplier->environment === 'production')>Production</option>
                                </select>
                            </div>
                            <div><label class="label">Priority (lower = first)</label><input type="number" name="priority" class="input" value="{{ $supplier->priority }}"></div>
                            <div><label class="label">Timeout (sec)</label><input type="number" name="timeout_seconds" class="input" value="{{ $supplier->timeout_seconds }}"></div>
                            <div><label class="label">Retry Attempts</label><input type="number" name="retry_attempts" class="input" value="{{ $supplier->retry_attempts }}"></div>
                            <div><label class="label">Status</label>
                                <select name="status" class="input">
                                    <option value="active" @selected($supplier->status === 'active')>Active</option>
                                    <option value="inactive" @selected($supplier->status === 'inactive')>Inactive</option>
                                </select>
                            </div>
                            <div><label class="label">Default Markup %</label><input type="number" step="any" name="default_markup_percent" class="input" value="{{ $supplier->default_markup_percent }}"></div>
                            <div><label class="label">Commission %</label><input type="number" step="any" name="default_commission_percent" class="input" value="{{ $supplier->default_commission_percent }}"></div>
                            <div><label class="label">Service Fee (₹)</label><input type="number" step="any" name="default_service_fee" class="input" value="{{ $supplier->default_service_fee }}"></div>
                            <div><label class="label">Extra Settings (JSON)</label><input type="text" name="settings" class="input font-mono text-xs" value='{{ json_encode($supplier->settings) }}'></div>
                        </div>
                        <button class="btn-primary btn-sm">Save Supplier</button>
                    </form>

                    {{-- Credentials --}}
                    <div>
                        <h3 class="text-sm font-bold">API Credentials ({{ $supplier->environment }})</h3>
                        @if ($supplier->credentialFor($supplier->environment))
                            <p class="mt-1 text-xs text-emerald-600">✓ Credentials stored (encrypted). Submit new values to replace.</p>
                        @else
                            <p class="mt-1 text-xs text-ink-500">No credentials yet for this environment.</p>
                        @endif
                        <form action="{{ route('admin.suppliers.credentials', $supplier) }}" method="POST" class="mt-2 space-y-2">
                            @csrf
                            <select name="environment" class="input">
                                <option value="test" @selected($supplier->environment === 'test')>Test / Sandbox</option>
                                <option value="production" @selected($supplier->environment === 'production')>Production</option>
                            </select>
                            <textarea name="credentials" rows="4" class="input font-mono text-xs"
                                      placeholder='{"client_id": "...", "client_secret": "..."}'></textarea>
                            <button class="btn-ghost btn-sm">Save Credentials (Encrypted)</button>
                        </form>

                        <form action="{{ route('admin.suppliers.destroy', $supplier) }}" method="POST" class="mt-4 border-t border-slate-100 pt-3"
                              onclick="return confirm('Delete supplier {{ $supplier->name }}?')">
                            @csrf @method('DELETE')
                            <button class="text-xs font-bold text-rose-500">Delete Supplier</button>
                        </form>
                    </div>
                </div>
            </details>
        @empty
            <div class="admin-card text-center text-ink-500">No suppliers configured yet</div>
        @endforelse
    </div>

    {{-- Add supplier --}}
    <div class="admin-card mt-4">
        <h2 class="font-display text-base font-bold">Add Supplier</h2>
        <form action="{{ route('admin.suppliers.store') }}" method="POST" class="mt-3 grid gap-3 sm:grid-cols-4">
            @csrf
            <div><label class="label">Name</label><input type="text" name="name" class="input" required></div>
            <div>
                <label class="label">Type</label>
                <select name="type" class="input">
                    @foreach (['flight', 'hotel', 'cab', 'package', 'bus', 'generic'] as $type)
                        <option value="{{ $type }}">{{ ucfirst($type) }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Adapter</label>
                <select name="adapter" class="input">
                    @foreach ($adapters as $adapter)
                        <option value="{{ $adapter }}">{{ $adapter }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="label">Environment</label>
                <select name="environment" class="input">
                    <option value="test">Test</option>
                    <option value="production">Production</option>
                </select>
            </div>
            <div><label class="label">Priority</label><input type="number" name="priority" class="input" value="10"></div>
            <div><label class="label">Timeout (sec)</label><input type="number" name="timeout_seconds" class="input" value="30"></div>
            <div><label class="label">Retries</label><input type="number" name="retry_attempts" class="input" value="2"></div>
            <div class="flex items-end"><button class="btn-primary btn-md w-full">Add Supplier</button></div>
        </form>
    </div>
@endsection
