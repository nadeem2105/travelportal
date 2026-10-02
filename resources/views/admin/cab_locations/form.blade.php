@extends('layouts.admin')
@section('pageTitle', 'Cab Location — ' . ($cabLocation->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.cab_locations'), 'viewKey' => 'cab-locations', 'entry' => $cabLocation])
@endsection
