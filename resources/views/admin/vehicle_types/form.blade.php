@extends('layouts.admin')
@section('pageTitle', 'Vehicle Type — ' . ($vehicleType->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.vehicle_types'), 'viewKey' => 'vehicle-types', 'entry' => $vehicleType])
@endsection
