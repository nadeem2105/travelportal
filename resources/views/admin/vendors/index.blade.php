@extends('layouts.admin')
@section('pageTitle', 'Cab Vendor Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.vendors'),
        'viewKey' => 'vendors',
        'entries' => $vendors,
        'filterBar' => [
            'action' => route('admin.vendors.index'),
            'searchPlaceholder' => 'Search vendors…',
            'filters' => [
                ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ],
        ],
    ])
@endsection
