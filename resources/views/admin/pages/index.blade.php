@extends('layouts.admin')
@section('pageTitle', 'Page Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.pages'),
        'viewKey' => 'pages',
        'entries' => $pages,
        'filterBar' => [
            'action' => route('admin.pages.index'),
            'searchPlaceholder' => 'Search title…',
            'filters' => [
                ['name' => 'template', 'label' => 'Template', 'options' => ['default' => 'Default', 'full_width' => 'Full Width', 'sidebar' => 'With Sidebar'], 'all' => 'All Templates'],
                ['name' => 'status', 'label' => 'Status', 'options' => ['draft' => 'Draft', 'published' => 'Published']],
            ],
            'sorts' => ['newest' => 'Newest', 'oldest' => 'Oldest'],
        ],
    ])
@endsection
