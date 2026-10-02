@extends('layouts.admin')
@section('pageTitle', 'Pricing Rules')

@section('content')
    <h1 class="font-display text-xl font-bold">Pricing Rules — Markup, Commission & Fees</h1>
    <p class="mt-1 text-sm text-ink-500">Rules apply server-side on every booking. Example: supplier price ₹20,000 + 5% markup + ₹299 fee → customer price auto-calculated.</p>

    <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_380px]">
        <div class="admin-card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Rule</th><th>Product</th><th>Supplier</th><th>Type</th><th>Calculation</th><th>Value</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($rules as $rule)
                        <tr>
                            <td class="font-bold">{{ $rule->name }}</td>
                            <td class="capitalize">{{ $rule->product_type }}</td>
                            <td>{{ $rule->supplier?->name ?? 'All' }}</td>
                            <td class="capitalize">{{ str_replace('_', ' ', $rule->rule_type) }}</td>
                            <td class="capitalize">{{ $rule->calculation }}</td>
                            <td class="font-bold">{{ $rule->calculation === 'percentage' ? $rule->value . '%' : money($rule->value) }}</td>
                            <td><span class="status-pill {{ status_pill_class($rule->status) }}">{{ ucfirst($rule->status) }}</span></td>
                            <td class="text-right">
                                <form action="{{ route('admin.pricing-rules.update', $rule) }}" method="POST" class="inline flex items-center gap-1">
                                    @csrf @method('PUT')
                                    <input type="number" step="any" name="value" class="input !w-24 !px-2 !py-1" value="{{ $rule->value }}">
                                    <input type="hidden" name="name" value="{{ $rule->name }}">
                                    <select name="status" class="input !w-24 !px-2 !py-1">
                                        <option value="active" @selected($rule->status === 'active')>Active</option>
                                        <option value="inactive" @selected($rule->status === 'inactive')>Off</option>
                                    </select>
                                    <button class="btn-ghost btn-sm">Save</button>
                                </form>
                                <form action="{{ route('admin.pricing-rules.destroy', $rule) }}" method="POST" class="inline" onclick="return confirm('Delete rule?')">
                                    @csrf @method('DELETE')
                                    <button class="ml-2 font-bold text-rose-500">✕</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="text-center text-ink-500">No pricing rules yet</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-card h-fit">
            <h2 class="font-display text-base font-bold">Add Pricing Rule</h2>
            <form action="{{ route('admin.pricing-rules.store') }}" method="POST" class="mt-3 space-y-3">
                @csrf
                <div><label class="label">Rule Name</label><input type="text" name="name" class="input" placeholder="e.g. Flight markup 5%" required></div>
                <div class="grid grid-cols-2 gap-2">
                    <div>
                        <label class="label">Product</label>
                        <select name="product_type" class="input">
                            @foreach (['all', 'flight', 'hotel', 'cab', 'package'] as $t)
                                <option value="{{ $t }}">{{ ucfirst($t) }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Rule Type</label>
                        <select name="rule_type" class="input">
                            @foreach (['markup' => 'Markup', 'commission' => 'Commission', 'service_fee' => 'Service Fee', 'convenience_fee' => 'Convenience Fee', 'discount' => 'Discount'] as $k => $label)
                                <option value="{{ $k }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="label">Calculation</label>
                        <select name="calculation" class="input">
                            <option value="percentage">Percentage %</option>
                            <option value="fixed">Fixed ₹</option>
                        </select>
                    </div>
                    <div><label class="label">Value</label><input type="number" step="any" name="value" class="input" required></div>
                </div>
                <div>
                    <label class="label">Supplier (optional)</label>
                    <select name="supplier_id" class="input">
                        <option value="">All suppliers</option>
                        @foreach ($suppliers as $supplier)
                            <option value="{{ $supplier->id }}">{{ $supplier->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="grid grid-cols-2 gap-2">
                    <div><label class="label">Min Amount (₹)</label><input type="number" step="any" name="min_amount" class="input"></div>
                    <div><label class="label">Max Amount (₹)</label><input type="number" step="any" name="max_amount" class="input"></div>
                </div>
                <button class="btn-primary btn-md w-full">Create Rule</button>
            </form>
        </div>
    </div>
@endsection
