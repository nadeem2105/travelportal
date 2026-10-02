<?php

/**
 * Generates thin admin index/form views for entities driven by the
 * generic partials. Run: php scripts/generate-crud-views.php
 */

$entities = [
    // viewKey => [pluralVar, singularVar]
    'vehicles' => ['vehicles', 'vehicle'],
    'vehicle_types' => ['vehicleTypes', 'vehicleType'],
    'vendors' => ['vendors', 'vendor'],
    'cab_locations' => ['cabLocations', 'cabLocation'],
    'airports' => ['airports', 'airport'],
    'airlines' => ['airlines', 'airline'],
    'coupons' => ['coupons', 'coupon'],
    'offers' => ['offers', 'offer'],
    'banners' => ['banners', 'banner'],
    'faqs' => ['faqs', 'faq'],
    'testimonials' => ['testimonials', 'testimonial'],
    'blogs' => ['blogs', 'blog'],
    'guides' => ['guides', 'guide'],
    'pages' => ['pages', 'page'],
];

foreach ($entities as $view => [$plural, $single]) {
    @mkdir(__DIR__ . "/../resources/views/admin/{$view}", 0777, true);

    $index = <<<BLADE
@extends('layouts.admin')
@section('pageTitle', \$spec['label'] . 's')

@section('content')
    @include('admin.partials.generic_index', ['spec' => config('crud_fields.{$view}'), 'viewKey' => '{$view}', 'entries' => \${$plural}])
@endsection

BLADE;

    $form = <<<BLADE
@extends('layouts.admin')
@section('pageTitle', (\${$single}->exists ? 'Edit ' : 'New ') . \$spec['label'])

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.{$view}'), 'viewKey' => '{$view}', 'entry' => \${$single}])
@endsection

BLADE;

    file_put_contents(__DIR__ . "/../resources/views/admin/{$view}/index.blade.php", $index);
    file_put_contents(__DIR__ . "/../resources/views/admin/{$view}/form.blade.php", $form);
    echo "views: {$view}\n";
}

echo "Done.\n";
