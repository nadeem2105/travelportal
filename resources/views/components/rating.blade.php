@props(['rating', 'size' => 4])

<span class="stars">
    @for ($i = 1; $i <= 5; $i++)
        <svg class="h-{{ $size }} w-{{ $size }}" width="{{ $size * 4 }}" height="{{ $size * 4 }}" viewBox="0 0 24 24"
             fill="{{ $i <= round($rating) ? 'currentColor' : 'none' }}"
             stroke="currentColor" stroke-width="1.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="m12 2 2.9 6.3 6.9.8-5.1 4.7 1.4 6.8L12 17.2l-6.1 3.4 1.4-6.8L2.2 9.1l6.9-.8L12 2Z"/>
        </svg>
    @endfor
</span>
