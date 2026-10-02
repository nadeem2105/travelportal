@extends('layouts.admin')
@section('pageTitle', 'Packages')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-xl font-bold">Tour Packages</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.packages.export', request()->query()) }}" class="btn-ghost btn-md">Export CSV</a>
            <a href="{{ route('admin.packages.create') }}" class="btn-primary btn-md">+ Add Package</a>
        </div>
    </div>

    <x-admin.filters
        :action="route('admin.packages.index')"
        search-placeholder="Search packages…"
        :filters="[
            ['name'=>'destination_id','label'=>'Destination','options'=>$destinations->pluck('name','id')->all(),'all'=>'All Destinations'],
            ['name'=>'status','label'=>'Status','options'=>['active'=>'Active','inactive'=>'Inactive','draft'=>'Draft']],
        ]"
        :sorts="['newest'=>'Newest','price_low'=>'Price low→high','price_high'=>'Price high→low']"
        :count="$packages->total()" />

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr>
                <x-admin.sort-header column="name" label="Package" />
                <th>Destination</th>
                <th>Duration</th>
                <x-admin.sort-header column="base_price" label="Base Price" />
                <th>Featured</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
            </tr></thead>
            <tbody>
                @forelse ($packages as $package)
                    <tr>
                        <td class="font-bold">{{ $package->name }}</td>
                        <td>{{ $package->destination?->name ?? '—' }}</td>
                        <td>{{ $package->duration_days }}D / {{ $package->duration_nights }}N</td>
                        <td>{{ money($package->base_price) }}</td>
                        <td>{{ $package->is_featured ? '✓' : '—' }}</td>
                        <td><span class="status-pill {{ status_pill_class($package->status) }}">{{ ucfirst($package->status) }}</span></td>
                        <td class="text-right">
                            <a href="{{ route('admin.packages.edit', $package) }}" class="font-bold text-brand-600">Edit</a>
                            <form action="{{ route('admin.packages.destroy', $package) }}" method="POST" class="inline" onclick="return confirm('Delete package?')">
                                @csrf @method('DELETE')
                                <button class="ml-3 font-bold text-rose-500">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">No packages yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $packages->links() }}</div>
@endsection
