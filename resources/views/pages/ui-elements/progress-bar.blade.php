@extends('layouts.app')


@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Progress Bar" />

    <div class="space-y-5 sm:space-y-6">

        <x-common.component-card title="Default Progress Bar">
            <div class="space-y-5 sm:max-w-[320px] w-full">
                <x-ui.progressbar :progress="55" />
                <x-ui.progressbar :progress="85" />
                <x-ui.progressbar :progress="35" />
            </div>
        </x-common.component-card>

        <x-common.component-card title="Default Progress Bar">
            <div class="space-y-5 sm:max-w-[320px] w-full">
                <x-ui.progressbar :progress="70" size="sm" />
                <x-ui.progressbar :progress="70" size="md" />
                <x-ui.progressbar :progress="70" size="lg" />
                <x-ui.progressbar :progress="70" size="xl" />
            </div>
        </x-common.component-card>

        <x-common.component-card title="Progress Bar with Inside Label">
            <div class="space-y-5 sm:max-w-[320px] w-full">
                <x-ui.progressbar :progress="40" label="outside" />
                <x-ui.progressbar :progress="70" label="outside" />
                <x-ui.progressbar :progress="30" label="outside" />
            </div>
        </x-common.component-card>

        <x-common.component-card title="Progress Bar with Inside Label">
            <div class="space-y-5 sm:max-w-[320px] w-full">
                <x-ui.progressbar :progress="40" label="inside" size="lg" />
                <x-ui.progressbar :progress="70" label="inside" size="lg" />
                <x-ui.progressbar :progress="30" label="inside" size="lg" />
            </div>
        </x-common.component-card>

    </div>
@endsection
