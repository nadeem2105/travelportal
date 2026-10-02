<?php

/**
 * Regenerates the thin admin index/form views with the entity label
 * inlined (no $spec reference outside the @include).
 */

$entities = [
    'vehicles' => ['vehicles', 'vehicle', 'Vehicle'],
    'vehicle_types' => ['vehicleTypes', 'vehicleType', 'Vehicle Type'],
    'vendors' => ['vendors', 'vendor', 'Cab Vendor'],
    'cab_locations' => ['cabLocations', 'cabLocation', 'Cab Location'],
    'airports' => ['airports', 'airport', 'Airport'],
    'airlines' => ['airlines', 'airline', 'Airline'],
    'coupons' => ['coupons', 'coupon', 'Coupon'],
    'offers' => ['offers', 'offer', 'Offer'],
    'banners' => ['banners', 'banner', 'Banner'],
    'faqs' => ['faqs', 'faq', 'FAQ'],
    'testimonials' => ['testimonials', 'testimonial', 'Testimonial'],
    'blogs' => ['blogs', 'blog', 'Blog Post'],
    'guides' => ['guides', 'guide', 'Travel Guide'],
    'pages' => ['pages', 'page', 'Page'],
];

foreach ($entities as $view => [$plural, $single, $label]) {
    $index = <<<BLADE
@extends('layouts.admin')
@section('pageTitle', '{$label} Management')

@section('content')
    @include('admin.partials.generic_index', ['spec' => config('crud_fields.{$view}'), 'viewKey' => '{$view}', 'entries' => \${$plural}])
@endsection

BLADE;

    $form = <<<BLADE
@extends('layouts.admin')
@section('pageTitle', '{$label} — ' . (\${$single}->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.{$view}'), 'viewKey' => '{$view}', 'entry' => \${$single}])
@endsection

BLADE;

    file_put_contents(__DIR__ . "/../resources/views/admin/{$view}/index.blade.php", $index);
    file_put_contents(__DIR__ . "/../resources/views/admin/{$view}/form.blade.php", $form);
    echo "regenerated {$view}\n";
}
