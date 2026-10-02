@extends('layouts.admin')
@section('pageTitle', 'Vehicle Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.vehicles'),
        'viewKey' => 'vehicles',
        'entries' => $vehicles,
        'filterBar' => [
            'action' => route('admin.vehicles.index'),
            'searchPlaceholder' => 'Search vehicles…',
            'filters' => [
                ['name' => 'vehicle_type_id', 'label' => 'Type', 'options' => $vehicleTypes->pluck('name', 'id')->all(), 'all' => 'All Types'],
                ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ],
        ],
    ])
@endsection
