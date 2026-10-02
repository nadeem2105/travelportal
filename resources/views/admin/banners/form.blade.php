@extends('layouts.admin')
@section('pageTitle', 'Banner — ' . ($banner->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.banners'), 'viewKey' => 'banners', 'entry' => $banner])
@endsection
