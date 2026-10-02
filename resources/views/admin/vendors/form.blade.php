@extends('layouts.admin')
@section('pageTitle', 'Cab Vendor — ' . ($vendor->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.vendors'), 'viewKey' => 'vendors', 'entry' => $vendor])
@endsection
