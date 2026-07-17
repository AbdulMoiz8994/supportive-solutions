@extends('layouts.app')


@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Modals" />

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2 xl:gap-6">
        <x-example.modals-example.regular-modal />
        <x-example.modals-example.vertically-centered />
        <x-example.modals-example.form-in-modal />
        <x-example.modals-example.full-screen-modal />
        <x-example.modals-example.modal-based-alerts />
    </div>
@endsection
