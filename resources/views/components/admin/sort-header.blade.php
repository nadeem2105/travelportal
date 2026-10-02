@props([
    'column',        // DB column to sort by
    'label' => null, // header text (defaults to prettified column)
    'align' => 'left',
])

@php
    $label = $label ?? str_replace('_', ' ', ucwords($column));
    $active = request('sort_col') === $column;
    $dir = strtolower(request('sort_dir', 'asc')) === 'desc' ? 'desc' : 'asc';
    $nextDir = ($active && $dir === 'asc') ? 'desc' : 'asc';
    $url = request()->fullUrlWithQuery(['sort_col' => $column, 'sort_dir' => $nextDir, 'page' => 1]);
@endphp

<th class="text-{{ $align }}">
    <a href="{{ $url }}" class="group inline-flex items-center gap-1 whitespace-nowrap hover:text-brand-700 {{ $active ? 'text-brand-700' : '' }}">
        <span>{{ $label }}</span>
        @if ($active)
            <span class="text-[10px]">{{ $dir === 'asc' ? '▲' : '▼' }}</span>
        @else
            <span class="text-[10px] opacity-25 group-hover:opacity-60">⇅</span>
        @endif
    </a>
</th>
