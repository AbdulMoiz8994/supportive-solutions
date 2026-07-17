@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Billing" />
    <div class="mb-6 flex flex-col gap-6 xl:flex-row">
      <x-ecommerce.billing.plan-details />
      <x-ecommerce.billing.billing-info />
    </div>
    <x-ecommerce.billing.payment-method />
    <x-ecommerce.billing.bill-invoice-table />
@endsection
