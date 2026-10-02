@extends('layouts.admin')
@section('pageTitle', 'Hotels')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-xl font-bold">Hotels</h1>
        <div class="flex items-center gap-2">
            <a href="{{ route('admin.hotels.export', request()->query()) }}" class="btn-ghost btn-md">Export CSV</a>
            <a href="{{ route('admin.hotels.create') }}" class="btn-primary btn-md">+ Add Hotel</a>
        </div>
    </div>

    <x-admin.filters
        :action="route('admin.hotels.index')"
        search-placeholder="Search hotels…"
        :filters="[
            ['name'=>'city','label'=>'City','options'=>$cities->mapWithKeys(fn($c)=>[$c=>$c])->all(),'all'=>'All Cities'],
            ['name'=>'star_rating','label'=>'Stars','options'=>[5=>'5 Star',4=>'4 Star',3=>'3 Star',2=>'2 Star',1=>'1 Star'],'all'=>'All Ratings'],
            ['name'=>'status','label'=>'Status','options'=>['active'=>'Active','inactive'=>'Inactive']],
        ]"
        :sorts="['newest'=>'Newest','price_low'=>'Price low→high','price_high'=>'Price high→low']"
        :count="$hotels->total()" />

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead><tr>
                <x-admin.sort-header column="name" label="Hotel" />
                <th>City</th>
                <x-admin.sort-header column="star_rating" label="Stars" />
                <th>Rooms</th>
                <th>Source</th>
                <th>Status</th>
                <th class="text-right">Actions</th>
            </tr></thead>
            <tbody>
                @forelse ($hotels as $hotel)
                    <tr>
                        <td class="font-bold">{{ $hotel->name }}</td>
                        <td>{{ $hotel->city ?? '—' }}</td>
                        <td class="text-amber-500">{{ str_repeat('★', $hotel->star_rating) }}</td>
                        <td>{{ $hotel->rooms->count() }}</td>
                        <td>{{ $hotel->supplier?->name ?? 'Manual' }}</td>
                        <td><span class="status-pill {{ status_pill_class($hotel->status) }}">{{ ucfirst($hotel->status) }}</span></td>
                        <td class="text-right">
                            <a href="{{ route('admin.hotels.rooms.index', $hotel) }}" class="font-bold text-teal-600">Rooms</a>
                            <a href="{{ route('admin.hotels.edit', $hotel) }}" class="ml-3 font-bold text-brand-600">Edit</a>
                            <form action="{{ route('admin.hotels.destroy', $hotel) }}" method="POST" class="inline" onclick="return confirm('Delete hotel?')">
                                @csrf @method('DELETE')
                                <button class="ml-3 font-bold text-rose-500">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="text-center text-ink-500">No hotels yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $hotels->links() }}</div>
@endsection
