@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Create Invoice" />
    <x-ecommerce.invoice.create-invoice-form />
@endsection
