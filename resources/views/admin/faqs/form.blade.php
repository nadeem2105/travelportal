@extends('layouts.admin')
@section('pageTitle', 'FAQ — ' . ($faq->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.faqs'), 'viewKey' => 'faqs', 'entry' => $faq])
@endsection
