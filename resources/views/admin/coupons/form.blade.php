@extends('layouts.admin')
@section('pageTitle', 'Coupon — ' . ($coupon->exists ? 'Edit' : 'New'))

@section('content')
    @include('admin.partials.generic_form', ['spec' => config('crud_fields.coupons'), 'viewKey' => 'coupons', 'entry' => $coupon])
@endsection
