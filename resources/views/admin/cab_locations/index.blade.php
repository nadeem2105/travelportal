@extends('layouts.admin')
@section('pageTitle', 'Cab Location Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.cab_locations'),
        'viewKey' => 'cab-locations',
        'entries' => $cabLocations,
        'filterBar' => [
            'action' => route('admin.cab-locations.index'),
            'searchPlaceholder' => 'Search name or city…',
            'filters' => [
                ['name' => 'type', 'label' => 'Type', 'options' => ['airport' => 'Airport', 'local' => 'Local', 'outstation' => 'Outstation'], 'all' => 'All Types'],
                ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ],
            'sorts' => ['newest' => 'Newest', 'oldest' => 'Oldest'],
        ],
    ])
@endsection
