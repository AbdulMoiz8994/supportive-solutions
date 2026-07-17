@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Invoices" />

    <x-ecommerce.invoice.invoice-overview />
    <x-ecommerce.invoice.invoice-list />
@endsection
