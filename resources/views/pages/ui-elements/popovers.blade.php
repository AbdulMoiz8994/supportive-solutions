@extends('layouts.app')


@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Popovers" />

    <div class="space-y-5 sm:space-y-6">
        <x-common.component-card title="Default Popovers">
            <x-ui.popover.default-popovers />
        </x-common.component-card>

        <x-common.component-card title="Popover With Button">
            <x-ui.popover.popover-with-button />
        </x-common.component-card>

        <x-common.component-card title="Popover With Link">
            <x-ui.popover.popover-with-link />
        </x-common.component-card>
    </div>
@endsection
