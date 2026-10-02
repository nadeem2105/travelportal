@extends('layouts.admin')
@section('pageTitle', 'Travel Guide — ' . ($guide->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.guides'), 'viewKey' => 'guides', 'entry' => $guide])
@endsection
