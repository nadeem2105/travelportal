@extends('layouts.admin')
@section('pageTitle', 'Airport — ' . ($airport->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.airports'), 'viewKey' => 'airports', 'entry' => $airport])
@endsection
