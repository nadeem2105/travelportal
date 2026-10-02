@extends('layouts.admin')
@section('pageTitle', 'Vehicle — ' . ($vehicle->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.vehicles'), 'viewKey' => 'vehicles', 'entry' => $vehicle])
@endsection
