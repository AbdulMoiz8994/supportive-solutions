@extends('layouts.app')


@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="List" />

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2 xl:gap-6">
        <x-common.component-card title="Unordered List">
            <x-ui.list.unordered-list />
        </x-common.component-card>
        <x-common.component-card title="Ordered List">
            <x-ui.list.order-list />
        </x-common.component-card>
        <x-common.component-card title="List With button">
            <x-ui.list.list-with-button />
        </x-common.component-card>
        <x-common.component-card title="List With Icon">
            <x-ui.list.list-with-icon />
        </x-common.component-card>
        <div class="col-span-2">
            <x-common.component-card title="Horizontal List">
                <x-ui.list.horizontal-list />
            </x-common.component-card>
        </div>
        <x-common.component-card title="List with checkbox">
            <x-ui.list.list-with-checkbox />
        </x-common.component-card>
        <x-common.component-card title="List with radio">
            <x-ui.list.list-with-radio />
        </x-common.component-card>
    </div>
@endsection
