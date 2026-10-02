@extends('layouts.admin')
@section('pageTitle', 'Offer Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.offers'),
        'viewKey' => 'offers',
        'entries' => $offers,
        'filterBar' => [
            'action' => route('admin.offers.index'),
            'searchPlaceholder' => 'Search title…',
            'filters' => [
                ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ],
        ],
    ])
@endsection
