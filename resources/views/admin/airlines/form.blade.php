@extends('layouts.admin')
@section('pageTitle', 'Airline — ' . ($airline->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.airlines'), 'viewKey' => 'airlines', 'entry' => $airline])
@endsection
