@props([
    'action' => null,
    'search' => true,
    'searchName' => 'q',
    'searchPlaceholder' => 'Search…',
    'filters' => [],   // [['name'=>'status','label'=>'Status','options'=>[val=>label],'all'=>'All']]
    'sorts' => [],     // [val => label]
    'sortName' => 'sort',
    'perPage' => true,
    'count' => null,
])

@php
    $action = $action ?? url()->current();
    // Which query keys this bar controls (so "Clear" knows what to drop / active-state works).
    $controlled = collect($filters)->pluck('name')->push($searchName)->push($sortName)->push('per_page')->all();
    $hasActive = collect($controlled)->contains(fn ($k) => filled(request($k)));
@endphp

<div class="admin-card mt-4 !p-3">
    <form method="GET" action="{{ $action }}"
          class="flex flex-wrap items-center gap-2"
          x-data
          @change.debounce.10ms="$el.requestSubmit()">

        @if ($search)
            <div class="relative min-w-[220px] flex-1">
                <svg class="pointer-events-none absolute left-3 top-1/2 h-4 w-4 -translate-y-1/2 text-ink-400" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-4.34-4.34M17 10a7 7 0 1 1-14 0 7 7 0 0 1 14 0Z"/></svg>
                <input type="search" name="{{ $searchName }}" value="{{ request($searchName) }}"
                       placeholder="{{ $searchPlaceholder }}"
                       class="input !pl-9"
                       @keydown.enter.prevent="$el.form.requestSubmit()">
            </div>
        @endif

        @foreach ($filters as $filter)
            <select name="{{ $filter['name'] }}" class="input !w-auto min-w-[130px]">
                <option value="">{{ $filter['all'] ?? ('All ' . ($filter['label'] ?? ucfirst($filter['name'])) . 's') }}</option>
                @foreach ($filter['options'] as $val => $label)
                    <option value="{{ $val }}" @selected((string) request($filter['name']) === (string) $val)>{{ $label }}</option>
                @endforeach
            </select>
        @endforeach

        @if (! empty($sorts))
            <select name="{{ $sortName }}" class="input !w-auto min-w-[140px]">
                @foreach ($sorts as $val => $label)
                    <option value="{{ $val }}" @selected((string) request($sortName) === (string) $val)>{{ $label }}</option>
                @endforeach
            </select>
        @endif

        {{-- extra custom controls (e.g. date range) --}}
        {{ $slot }}

        @if ($perPage)
            <select name="per_page" class="input !w-auto" title="Rows per page">
                @foreach ([15, 25, 50, 100] as $n)
                    <option value="{{ $n }}" @selected((int) request('per_page', 15) === $n)>{{ $n }} / page</option>
                @endforeach
            </select>
        @endif

        <noscript><button class="btn-primary btn-sm">Apply</button></noscript>

        @if ($hasActive)
            <a href="{{ $action }}" class="btn-ghost btn-sm !text-rose-600" title="Clear all filters">Clear</a>
        @endif

        @if (! is_null($count))
            <span class="ml-auto text-xs font-semibold text-ink-500">{{ number_format($count) }} result{{ $count === 1 ? '' : 's' }}</span>
        @endif
    </form>
</div>
