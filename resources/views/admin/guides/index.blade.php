@extends('layouts.admin')
@section('pageTitle', 'Travel Guide Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.guides'),
        'viewKey' => 'guides',
        'entries' => $guides,
        'filterBar' => [
            'action' => route('admin.guides.index'),
            'searchPlaceholder' => 'Search title…',
            'filters' => [
                ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ],
        ],
    ])
@endsection
