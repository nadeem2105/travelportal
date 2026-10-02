{{-- Popular packages section --}}
<section class="shell py-10">
    @include('home.sections._header', [
        'title' => $section->title ?? 'Popular Kashmir Tour Packages',
        'subtitle' => $section->subtitle ?? 'Handpicked experiences for every traveller',
        'ctaText' => $section->cta_text ?? 'View All Packages',
        'ctaUrl' => $section->cta_url ?? route('packages.index'),
        'cta' => $section->cta_text !== null,
    ])

    <div class="mt-6 grid gap-5 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($packages as $index => $package)
            @php
                $badges = [
                    ['label' => 'Best Seller', 'class' => 'badge-best'],
                    ['label' => 'Honeymoon Special', 'class' => 'badge-honeymoon'],
                    ['label' => 'Family Favourite', 'class' => 'badge-family'],
                    ['label' => 'Adventure', 'class' => 'badge-adventure'],
                ];
                $badge = $badges[$index % count($badges)];
            @endphp
            <x-package-card :package="$package" :badge="$badge" />
        @endforeach
    </div>
</section>
