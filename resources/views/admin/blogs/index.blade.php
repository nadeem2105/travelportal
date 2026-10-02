@extends('layouts.admin')
@section('pageTitle', 'Blog Post Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.blogs'),
        'viewKey' => 'blogs',
        'entries' => $blogs,
        'filterBar' => [
            'action' => route('admin.blogs.index'),
            'searchPlaceholder' => 'Search title…',
            'filters' => [
                ['name' => 'status', 'label' => 'Status', 'options' => ['draft' => 'Draft', 'published' => 'Published', 'scheduled' => 'Scheduled', 'archived' => 'Archived']],
            ],
        ],
    ])
@endsection
