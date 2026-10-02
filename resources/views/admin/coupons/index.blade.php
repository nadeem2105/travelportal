@extends('layouts.admin')
@section('pageTitle', 'Coupon Management')

@section('content')
    @include('admin.partials.generic_index', [
        'spec' => config('crud_fields.coupons'),
        'viewKey' => 'coupons',
        'entries' => $coupons,
        'filterBar' => [
            'action' => route('admin.coupons.index'),
            'searchPlaceholder' => 'Search code or description…',
            'filters' => [
                ['name' => 'status', 'label' => 'Status', 'options' => ['active' => 'Active', 'inactive' => 'Inactive']],
                ['name' => 'discount_type', 'label' => 'Type', 'options' => ['percentage' => 'Percentage', 'fixed' => 'Fixed'], 'all' => 'All Types'],
            ],
            'sorts' => ['newest' => 'Newest', 'oldest' => 'Oldest'],
        ],
    ])
@endsection
