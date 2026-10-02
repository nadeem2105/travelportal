@extends('layouts.admin')
@section('pageTitle', 'Testimonial — ' . ($testimonial->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.testimonials'), 'viewKey' => 'testimonials', 'entry' => $testimonial])
@endsection
