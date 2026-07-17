@extends('layouts.app')

@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Tabs" />

    <div class="space-y-5 sm:space-y-6">

        <x-common.component-card title="Default Tabs">
            <x-ui.tab.default-tab />
        </x-common.component-card>

        <x-common.component-card title="Tab With Underline">
            <x-ui.tab.tab-with-underline />
        </x-common.component-card>

        <x-common.component-card title="Tab With Icon">
            <x-ui.tab.tab-with-icon />
        </x-common.component-card>

        <x-common.component-card title="Tab With Badge">
            <x-ui.tab.tab-with-badge />
        </x-common.component-card>

        <x-common.component-card title="Vertical Tabs">
            <x-ui.tab.vertical-tab />
        </x-common.component-card>

    </div>
@endsection
