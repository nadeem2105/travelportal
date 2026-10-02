@php
    $rows = collect($data ?? []);
    $max = max(1, $rows->max() ?? 1);
    $palette = $colors ?? ['#4b4bd6'];
@endphp

@if ($rows->isEmpty())
    <p class="py-2 text-xs text-ink-400">No data for this period.</p>
@else
    <div class="space-y-2">
        @foreach ($rows as $label => $value)
            <div class="flex items-center gap-3">
                <div class="w-28 shrink-0 truncate text-xs capitalize text-ink-600" title="{{ label_case((string) $label) }}">{{ label_case((string) $label) }}</div>
                <div class="h-5 flex-1 rounded bg-ink-100">
                    <div class="h-5 rounded" style="width: {{ max(4, round($value / $max * 100)) }}%; background: {{ $palette[$loop->index % count($palette)] }};"></div>
                </div>
                <div class="w-10 shrink-0 text-right text-xs font-semibold text-ink-700">{{ $value }}</div>
            </div>
        @endforeach
    </div>
@endif
