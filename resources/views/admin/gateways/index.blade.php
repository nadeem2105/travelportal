@extends('layouts.admin')
@section('pageTitle', 'Payment Gateways')

@section('content')
    <h1 class="font-display text-xl font-bold">Payment Gateways</h1>
    <p class="mt-1 text-sm text-ink-500">Enable a gateway for checkout. Secrets are stored encrypted and never exposed to the frontend. For production, add Razorpay keys and enable it.</p>

    <div class="mt-4 space-y-4">
        @foreach ($gateways as $gateway)
            <div class="admin-card">
                <form action="{{ route('admin.gateways.update', $gateway) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <span class="flex h-10 w-10 items-center justify-center rounded-xl bg-brand-600 font-display text-lg font-bold text-white">{{ strtoupper(substr($gateway->code, 0, 1)) }}</span>
                            <div>
                                <p class="font-display font-bold">{{ $gateway->name }}</p>
                                <p class="text-xs text-ink-500">code: {{ $gateway->code }} · mode: {{ $gateway->mode }}</p>
                            </div>
                        </div>
                        <label class="flex cursor-pointer items-center gap-2 text-sm font-semibold">
                            <input type="checkbox" name="is_enabled" value="1" class="accent-brand-600" @checked($gateway->is_enabled)> Enabled
                        </label>
                    </div>

                    <div class="mt-4 grid gap-3 sm:grid-cols-3">
                        <div><label class="label">Display Name</label><input type="text" name="name" class="input" value="{{ $gateway->name }}"></div>
                        <div>
                            <label class="label">Mode</label>
                            <select name="mode" class="input">
                                <option value="test" @selected($gateway->mode === 'test')>Test / Sandbox</option>
                                <option value="live" @selected($gateway->mode === 'live')>Live</option>
                            </select>
                        </div>
                        <div><label class="label">Currency</label><input type="text" name="currency" class="input" value="{{ $gateway->currency }}" maxlength="3"></div>
                    </div>

                    <div class="mt-3 grid gap-3 sm:grid-cols-3">
                        @foreach ($secretFields[$gateway->code] ?? [] as $field)
                            <div>
                                <label class="label">{{ label_case($field) }}</label>
                                <input type="password" name="config[{{ $field }}]" class="input"
                                       placeholder="{{ filled($gateway->config[$field] ?? null) ? '•••••••• (saved)' : 'Enter ' . $field }}">
                            </div>
                        @endforeach
                    </div>

                    <button class="btn-primary btn-sm mt-4">Save Gateway</button>
                </form>
            </div>
        @endforeach
    </div>
@endsection
