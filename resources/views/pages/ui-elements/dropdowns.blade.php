@extends('layouts.app')

@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Dropdowns" />

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2 xl:gap-6">

        <x-common.component-card title="Default Dropdown">
            <div class="pb-[300px]">
                <x-ui.dropdown.default-dropdown />
            </div>
        </x-common.component-card>

        <x-common.component-card title="Dropdown with Divider">
            <div class="pb-[300px]">
                <x-ui.dropdown.dropdown-with-divider />
            </div>
        </x-common.component-card>

        <x-common.component-card title="Dropdown with Icon">
            <div class="pb-[300px]">
                <x-ui.dropdown.dropdown-with-icon />
            </div>
        </x-common.component-card>

        <x-common.component-card title="Dropdown with Icon and Divider">
            <div class="pb-[300px]">
                <x-ui.dropdown.dropdown-icon-divider />
            </div>
        </x-common.component-card>

    </div>
@endsection
