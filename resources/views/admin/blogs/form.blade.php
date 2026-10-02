@extends('layouts.admin')
@section('pageTitle', 'Blog Post — ' . ($blog->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.blogs'), 'viewKey' => 'blogs', 'entry' => $blog])
@endsection
