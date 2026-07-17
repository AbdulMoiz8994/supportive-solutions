@extends('layouts.app')


@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Pagination" />

    <div class="space-y-5 sm:space-y-6">
        <x-common.component-card title="Pagination With Text">
            <x-ui.pagination.pagination-with-text totalPages="10" currentPage="1" />
        </x-common.component-card>
        <x-common.component-card title="Pagination With Icon">
            <x-ui.pagination.pagination-with-icon totalPages="10" currentPage="1" />
        </x-common.component-card>
        <x-common.component-card title="Pagination With Text And Icon">
            <x-ui.pagination.pagination-with-text-and-icon totalPages="10" currentPage="1" />
        </x-common.component-card>
    </div>
@endsection
