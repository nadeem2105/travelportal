@extends('layouts.admin')
@section('pageTitle', 'Airport Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.airports'),
        'viewKey' => 'airports',
        'entries' => $airports,
        'filterBar' => [
            'action' => route('admin.airports.index'),
            'searchPlaceholder' => 'Search airports by name, code or city…',
            'filters' => [
                ['name' => 'country', 'label' => 'Country', 'options' => $countries->mapWithKeys(fn ($c) => [$c => $c])->all(), 'all' => 'All Countries'],
            ],
        ],
    ])
@endsection
