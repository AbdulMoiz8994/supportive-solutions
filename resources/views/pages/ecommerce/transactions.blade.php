@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Transactions" />
    <x-ecommerce.transactions.transaction-table />
@endsection
