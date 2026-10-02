@extends('layouts.admin')
@section('pageTitle', 'Page — ' . ($page->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.pages'), 'viewKey' => 'pages', 'entry' => $page])
@endsection
