@extends('layouts.site')

@php($heroImage = $sections->firstWhere('type', 'hero')->image ?? null)

@section('hero')
    {{-- HERO per approved design --}}
    <section class="hero">
        <div class="hero-media">
            <img src="{{ asset(img($heroImage, 'images/hero.svg')) }}" alt="{{ settings('hero_alt', 'Kashmir valley with snow-capped mountains and Dal Lake') }}" fetchpriority="high" loading="eager" decoding="async" width="1920" height="1080">
        </div>
        <div class="hero-scrim"></div>

        <div class="shell relative pb-8 pt-14 sm:pt-20">
            <div class="grid items-center gap-6 lg:grid-cols-[1fr_auto]">
                <div class="max-w-2xl">
                    <h1 class="font-display text-4xl font-extrabold leading-tight tracking-tight text-ink-900 sm:text-5xl">
                        {{ settings('hero_title_line1', 'More than a Trip') }}<br>
                        <span class="text-brand-600">{{ settings('hero_title_line2', 'A Beautiful Story') }}</span>
                    </h1>
                    <p class="mt-4 max-w-md text-[15px] leading-6 text-ink-700">
                        {{ settings('hero_subtitle', 'Discover the magic of Kashmir with flights, hotels, cabs and curated tour packages — all in one place.') }}
                    </p>
                </div>
                <div class="hidden lg:block">
                    <p class="hero-script -rotate-3 text-right">{{ settings('hero_script_line1', 'Kashmir') }}<br>{{ settings('hero_script_line2', 'Waits for You') }}</p>
                    <svg class="mx-auto mt-1 h-2 w-24 text-amber-400" viewBox="0 0 96 8" fill="currentColor"><path d="M2 6 Q 24 0 48 4 T 94 2" stroke="currentColor" stroke-width="3" fill="none" stroke-linecap="round"/></svg>
                </div>
            </div>

            {{-- Booking widget --}}
            <div class="mt-8">
                <x-search-widget :initialTab="$widgetTab ?? 'flights'" />
            </div>
        </div>
    </section>
@endsection

@section('page')
    @forelse ($sections as $section)
        @php($view = 'home.sections.' . $section->type)
        @if (view()->exists($view) && $section->type !== 'hero')
            @include($view, ['section' => $section])
        @endif
    @empty
        <div class="shell py-16 text-center text-ink-500">Homepage sections will appear here once configured in the Admin Panel.</div>
    @endforelse
@endsection
