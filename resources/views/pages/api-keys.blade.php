@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="API Keys" />
    <x-api-keys-table :apiKeys="$apiKeys" />
@endsection
