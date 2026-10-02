@extends('layouts.admin')
@section('pageTitle', 'FAQ Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.faqs'),
        'viewKey' => 'faqs',
        'entries' => $faqs,
        'filterBar' => [
            'action' => route('admin.faqs.index'),
            'searchPlaceholder' => 'Search question, answer or category…',
            'filters' => [
                ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
            ],
            'sorts' => ['newest' => 'Newest', 'oldest' => 'Oldest'],
        ],
    ])
@endsection
