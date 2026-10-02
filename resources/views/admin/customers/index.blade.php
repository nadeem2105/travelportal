@extends('layouts.admin')
@section('pageTitle', 'Customers')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-xl font-bold">Customers</h1>
        <a href="{{ route('admin.customers.export', request()->query()) }}" class="btn-ghost btn-md">Export CSV</a>
    </div>

    <x-admin.filters
        :action="route('admin.customers.index')"
        search-placeholder="Search name, email, phone…"
        :filters="[['name' => 'is_active', 'label' => 'Status', 'options' => ['1' => 'Active', '0' => 'Inactive']]]"
        :sorts="['newest' => 'Newest', 'most-bookings' => 'Most Bookings']"
        :count="$customers->total()" />

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr>
                <x-admin.sort-header column="name" label="Name" />
                <x-admin.sort-header column="email" label="Email" />
                <th>Phone</th>
                <x-admin.sort-header column="bookings_count" label="Bookings" />
                <th>Status</th>
                <x-admin.sort-header column="created_at" label="Joined" />
                <th class="text-right">Actions</th>
            </tr></thead>
            <tbody>
                @forelse ($customers as $customer)
                    <tr>
                        <td><a href="{{ route('admin.customers.show', $customer) }}" class="font-bold text-brand-600">{{ $customer->name }}</a></td>
                        <td>{{ $customer->email }}</td>
                        <td>{{ $customer->phone ?? '—' }}</td>
                        <td>{{ $customer->bookings_count }}</td>
                        <td>
                            <span class="status-pill {{ $customer->is_active ? 'bg-emerald-100 text-emerald-700' : 'bg-rose-100 text-rose-700' }}">
                                {{ $customer->is_active ? 'Active' : 'Blocked' }}
                            </span>
                        </td>
                        <td class="text-xs text-ink-500">{{ $customer->created_at->format('d M Y') }}</td>
                        <td class="text-right">
                            <form action="{{ route('admin.customers.toggle', $customer) }}" method="POST">
                                @csrf
                                <button class="btn-ghost btn-sm">{{ $customer->is_active ? 'Deactivate' : 'Activate' }}</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">No customers yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $customers->links() }}</div>
@endsection
