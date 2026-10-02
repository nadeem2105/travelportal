@extends('layouts.admin')
@section('pageTitle', 'Vehicle Type Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.vehicle_types'),
        'viewKey' => 'vehicle-types',
        'entries' => $vehicleTypes,
        'filterBar' => [
            'action' => route('admin.vehicle-types.index'),
            'searchPlaceholder' => 'Search name…',
            'filters' => [
                ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ],
            'sorts' => ['newest' => 'Newest', 'oldest' => 'Oldest'],
        ],
    ])
@endsection
