@extends('layouts.app')

@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Spinners" />

    <div class="space-y-5 sm:space-y-6">

        <x-common.component-card title="Spinner 1">
            <x-ui.spinner.spinner-one />
        </x-common.component-card>

        <x-common.component-card title="Spinner 2">
            <x-ui.spinner.spinner-two />
        </x-common.component-card>

        <x-common.component-card title="Spinner 3">
            <x-ui.spinner.spinner-three />
        </x-common.component-card>

        <x-common.component-card title="Spinner 4">
            <x-ui.spinner.spinner-four />
        </x-common.component-card>

    </div>
@endsection
