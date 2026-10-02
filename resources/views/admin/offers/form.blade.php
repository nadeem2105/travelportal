@extends('layouts.admin')
@section('pageTitle', 'Offer — ' . ($offer->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.offers'), 'viewKey' => 'offers', 'entry' => $offer])
@endsection
