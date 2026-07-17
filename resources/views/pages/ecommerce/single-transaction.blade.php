@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Transaction Details" />
    <div class="space-y-6">
        <x-ecommerce.transactions.transaction-header />
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-12">
            <div class="lg:col-span-8 2xl:col-span-9">
                <x-ecommerce.transactions.order-details />
            </div>
            <div class="space-y-6 lg:col-span-4 2xl:col-span-3">
                <x-ecommerce.transactions.customer-details />
                <x-ecommerce.transactions.order-history />
            </div>
        </div>
    </div>
@endsection
