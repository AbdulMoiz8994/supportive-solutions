@extends('layouts.app')

@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Tooltips" />

    <div class="space-y-5 sm:space-y-6">

        <x-common.component-card title="Default Tooltips">
            <div class="flex flex-wrap gap-4">
                <button data-tooltip="This is a tooltip" data-tooltip-placement="top" class="bg-brand-500 shadow-theme-xs inline-flex rounded-lg px-4 py-3 text-sm font-medium text-white">
                    Default Tooltip
                </button>
            </div>
        </x-common.component-card>

        <x-common.component-card title="White and Dark Tooltip">
            <div class="flex items-center gap-10">
                <!-- White -->
                <button data-tooltip="This is a tooltip" data-tooltip-placement="top" class="bg-brand-500 shadow-theme-xs inline-flex rounded-lg px-4 py-3 text-sm font-medium text-white">
                    White Tooltip
                </button>
                <!-- Dark -->
                <button data-tooltip="This is a tooltip" data-tooltip-placement="top" data-tooltip-variant="dark"
                    class="bg-brand-500 shadow-theme-xs inline-flex rounded-lg px-4 py-3 text-sm font-medium text-white">
                    Dark Tooltip Top
                </button>
            </div>
        </x-common.component-card>

        <x-common.component-card title="Tooltip Placement">
            <div class="flex flex-col items-center gap-10 sm:flex-row">
                <!-- Default Tooltip - Top -->
                <button data-tooltip="This is a tooltip" data-tooltip-placement="top" class="bg-brand-500 shadow-theme-xs inline-flex rounded-lg px-4 py-3 text-sm font-medium text-white">
                    Tooltip Top
                </button>

                <!-- Dark Variant - Right -->
                <button data-tooltip="Dark variant tooltip" data-tooltip-placement="right" class="bg-brand-500 shadow-theme-xs inline-flex rounded-lg px-4 py-3 text-sm font-medium text-white">
                    Tooltip Right
                </button>

                <!-- Bottom Placement -->
                <button data-tooltip="Tooltip on bottom" data-tooltip-placement="bottom" class="bg-brand-500 shadow-theme-xs inline-flex rounded-lg px-4 py-3 text-sm font-medium text-white">
                    Tooltip Bottom
                </button>

                <!-- Left Placement -->
                <button data-tooltip="Tooltip on left side" data-tooltip-placement="left" class="bg-brand-500 shadow-theme-xs inline-flex rounded-lg px-4 py-3 text-sm font-medium text-white">
                    Tooltip Left
                </button>
            </div>
        </x-common.component-card>

    </div>
@endsection
