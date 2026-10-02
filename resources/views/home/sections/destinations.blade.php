{{-- Popular destinations section --}}
<section class="shell py-10">
    @include('home.sections._header', [
        'title' => $section->title ?? 'Explore Kashmir',
        'subtitle' => $section->subtitle ?? 'Popular destinations for your next trip',
        'ctaText' => $section->cta_text ?? 'View All',
        'ctaUrl' => $section->cta_url ?? route('destinations.index'),
        'cta' => $section->cta_text !== null,
    ])
    <div class="scroll-row mt-6 sm:grid sm:grid-cols-3 sm:overflow-visible lg:grid-cols-6">
        @foreach ($destinations as $destination)
            <div class="w-[240px] sm:w-auto">
                <x-destination-card :destination="$destination" />
            </div>
        @endforeach
    </div>
</section>
