@extends('layouts.admin')
@section('pageTitle', 'Testimonial Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.testimonials'),
        'viewKey' => 'testimonials',
        'entries' => $testimonials,
        'filterBar' => [
            'action' => route('admin.testimonials.index'),
            'searchPlaceholder' => 'Search name, city or destination…',
            'filters' => [
                ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
                ['name' => 'rating', 'label' => 'Rating', 'options' => [5 => '5 stars', 4 => '4 stars', 3 => '3 stars', 2 => '2 stars', 1 => '1 star'], 'all' => 'All Ratings'],
            ],
            'sorts' => ['newest' => 'Newest', 'oldest' => 'Oldest'],
        ],
    ])
@endsection
