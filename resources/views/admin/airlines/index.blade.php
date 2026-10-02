@extends('layouts.admin')
@section('pageTitle', 'Airline Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.airlines'),
        'viewKey' => 'airlines',
        'entries' => $airlines,
        'filterBar' => [
            'action' => route('admin.airlines.index'),
            'searchPlaceholder' => 'Search airlines by name or code…',
            'filters' => [
                ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ],
        ],
    ])
@endsection
