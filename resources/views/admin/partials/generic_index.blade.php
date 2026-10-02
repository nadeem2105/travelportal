{{-- Generic admin index for simple entities: $entries, $spec, $viewKey --}}
@extends('layouts.admin')
@section('pageTitle', $spec['label'] . 's')

@section('content')
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="font-display text-xl font-bold">{{ $spec['label'] }}s</h1>
        <div class="flex items-center gap-2">
            @if (Route::has('admin.' . $viewKey . '.export'))
                <a href="{{ route('admin.' . $viewKey . '.export', request()->query()) }}" class="btn-ghost btn-md">Export CSV</a>
            @endif
            <a href="{{ route('admin.' . $viewKey . '.create') }}" class="btn-primary btn-md">+ Add {{ $spec['label'] }}</a>
        </div>
    </div>

    @isset($filterBar)
        <x-admin.filters
            :action="$filterBar['action']"
            :search="$filterBar['search'] ?? true"
            :search-placeholder="$filterBar['searchPlaceholder'] ?? 'Search…'"
            :filters="$filterBar['filters'] ?? []"
            :sorts="$filterBar['sorts'] ?? []"
            :count="$entries->total()" />
    @endisset

    <div class="admin-card mt-4 overflow-x-auto">
        <table class="admin-table">
            <thead>
                <tr>
                    <th>#</th>
                    @foreach ($spec['columns'] as $column)
                        <x-admin.sort-header :column="$column" />
                    @endforeach
                    <th class="text-right">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse ($entries as $entry)
                    <tr>
                        <td class="text-ink-500">{{ $entry->id }}</td>
                        @foreach ($spec['columns'] as $column)
                            @php($value = $entry->{$column})
                            <td>
                                @if (in_array($column, ['status', 'type']))
                                    <span class="status-pill {{ status_pill_class((string) $value) }}">{{ label_case((string) $value) }}</span>
                                @elseif (is_bool($value))
                                    {{ $value ? '✓' : '—' }}
                                @elseif (is_array($value))
                                    {{ implode(', ', $value) }}
                                @elseif ($value instanceof \Carbon\CarbonInterface)
                                    {{ $value->format('d M Y') }}
                                @elseif ($column === 'vehicle_type_id')
                                    {{ $entry->type?->name }}
                                @elseif (in_array($column, ['base_price', 'discount_value', 'min_booking_amount']))
                                    {{ money((float) $value) }}
                                @elseif (str_ends_with($column, '_id'))
                                    {{ $value }}
                                @else
                                    {{ \Illuminate\Support\Str::limit((string) $value, 60) }}
                                @endif
                            </td>
                        @endforeach
                        <td class="text-right">
                            <a href="{{ route('admin.' . $viewKey . '.edit', $entry) }}" class="font-bold text-brand-600 hover:text-brand-800">Edit</a>
                            <form action="{{ route('admin.' . $viewKey . '.destroy', $entry) }}" method="POST" class="inline"
                                  onclick="return confirm('Delete this {{ strtolower($spec['label']) }}?')">
                                @csrf
                                @method('DELETE')
                                <button class="ml-3 font-bold text-rose-500 hover:text-rose-700">Delete</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ count($spec['columns']) + 2 }}" class="text-center text-ink-500">No records yet</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $entries->links() }}</div>
@endsection
