@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Pricing Tables" />
    <div class="space-y-5 sm:space-y-6">
        <x-common.component-card title="Price Table 1">
            <x-pricing.pricing-table-one />
        </x-common.component-card>
        <x-common.component-card title="Price Table 2">
            <x-pricing.pricing-table-two />
        </x-common.component-card>
        <x-common.component-card title="Price Table 3">
            <x-pricing.pricing-table-three />
        </x-common.component-card>
    </div>
@endsection
