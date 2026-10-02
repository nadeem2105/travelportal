@extends('layouts.admin')
@section('pageTitle', 'Taxes')

@section('content')
    <h1 class="font-display text-xl font-bold">Taxes & Charges</h1>
    <p class="mt-1 text-sm text-ink-500">GST, service tax, convenience fee — all configurable. Nothing is hardcoded.</p>

    <div class="mt-4 grid gap-4 lg:grid-cols-[1fr_360px]">
        <div class="admin-card overflow-x-auto">
            <table class="admin-table">
                <thead><tr><th>Name</th><th>Product</th><th>Calculation</th><th>Value</th><th>Inclusive</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
                <tbody>
                    @forelse ($taxes as $tax)
                        <tr>
                            <td class="font-bold">{{ $tax->name }}</td>
                            <td class="capitalize">{{ $tax->product_type }}</td>
                            <td class="capitalize">{{ $tax->calculation }}</td>
                            <td class="font-bold">{{ $tax->calculation === 'percentage' ? $tax->value . '%' : money($tax->value) }}</td>
                            <td>{{ $tax->is_inclusive ? '✓' : '—' }}</td>
                            <td><span class="status-pill {{ status_pill_class($tax->status) }}">{{ ucfirst($tax->status) }}</span></td>
                            <td class="text-right">
                                <form action="{{ route('admin.taxes.update', $tax) }}" method="POST" class="inline flex items-center gap-1">
                                    @csrf @method('PUT')
                                    <input type="number" step="any" name="value" class="input !w-24 !px-2 !py-1" value="{{ $tax->value }}">
                                    <input type="hidden" name="name" value="{{ $tax->name }}">
                                    <select name="status" class="input !w-24 !px-2 !py-1">
                                        <option value="active" @selected($tax->status === 'active')>Active</option>
                                        <option value="inactive" @selected($tax->status === 'inactive')>Off</option>
                                    </select>
                                    <button class="btn-ghost btn-sm">Save</button>
                                </form>
                                <form action="{{ route('admin.taxes.destroy', $tax) }}" method="POST" class="inline" onclick="return confirm('Delete tax?')">
                                    @csrf @method('DELETE')
                                    <button class="ml-2 font-bold text-rose-500">✕</button>
                                </form>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7" class="text-center text-ink-500">No taxes configured</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="admin-card h-fit">
            <h2 class="font-display text-base font-bold">Add Tax</h2>
            <form action="{{ route('admin.taxes.store') }}" method="POST" class="mt-3 space-y-3">
                @csrf
                <div><label class="label">Name</label><input type="text" name="name" class="input" placeholder="e.g. GST 5%" required></div>
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
                        <label class="label">Calculation</label>
                        <select name="calculation" class="input">
                            <option value="percentage">Percentage %</option>
                            <option value="fixed">Fixed ₹</option>
                        </select>
                    </div>
                    <div><label class="label">Value</label><input type="number" step="any" name="value" class="input" required></div>
                    <div class="flex items-end pb-2">
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <input type="checkbox" name="is_inclusive" value="1" class="accent-brand-600"> Inclusive
                        </label>
                    </div>
                </div>
                <div><label class="label">Description</label><input type="text" name="description" class="input"></div>
                <button class="btn-primary btn-md w-full">Create Tax</button>
            </form>
        </div>
    </div>
@endsection
