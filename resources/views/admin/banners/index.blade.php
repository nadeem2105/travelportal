@extends('layouts.admin')
@section('pageTitle', 'Banner Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.banners'),
        'viewKey' => 'banners',
        'entries' => $banners,
        'filterBar' => [
            'action' => route('admin.banners.index'),
            'searchPlaceholder' => 'Search title or subtitle…',
            'filters' => [
                ['name' => 'position', 'label' => 'Position', 'options' => ['home' => 'Home', 'flights' => 'Flights', 'hotels' => 'Hotels', 'cabs' => 'Cabs', 'packages' => 'Packages', 'offers' => 'Offers'], 'all' => 'All Positions'],
                ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ],
            'sorts' => ['newest' => 'Newest', 'oldest' => 'Oldest'],
        ],
    ])
@endsection
