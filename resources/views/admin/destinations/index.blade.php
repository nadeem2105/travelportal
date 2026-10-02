@extends('layouts.admin')
@section('pageTitle', 'Destinations')

@section('content')
    <div class="flex items-center justify-between">
        <h1 class="font-display text-xl font-bold">Destinations</h1>
        <a href="{{ route('admin.destinations.create') }}" class="btn-primary btn-md">+ Add Destination</a>
    </div>

    <x-admin.filters
        :action="route('admin.destinations.index')"
        search-placeholder="Search name…"
        :filters="[['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']]]"
        :count="$destinations->total()" />

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr><th>Name</th><th>Region</th><th>Packages</th><th>Featured</th><th>Order</th><th>Status</th><th class="text-right">Actions</th></tr></thead>
            <tbody>
                @forelse ($destinations as $destination)
                    <tr>
                        <td class="font-bold">{{ $destination->name }}</td>
                        <td>{{ $destination->region ?? '—' }}</td>
                        <td>{{ $destination->packages_count }}</td>
                        <td>{{ $destination->is_featured ? '✓' : '—' }}</td>
                        <td>{{ $destination->sort_order }}</td>
                        <td><span class="status-pill {{ status_pill_class($destination->status) }}">{{ ucfirst($destination->status) }}</span></td>
                        <td class="text-right">
                            <a href="{{ route('admin.destinations.edit', $destination) }}" class="font-bold text-brand-600">Edit</a>
                            <form action="{{ route('admin.destinations.destroy', $destination) }}" method="POST" class="inline" onclick="return confirm('Delete destination?')">
                                @csrf @method('DELETE')
                                <button class="ml-3 font-bold text-rose-500">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">No destinations yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $destinations->links() }}</div>
@endsection
