@extends('layouts.app')

@section('title', 'Breadcrumbs')

@section('content')
    <x-common.page-breadcrumb pageTitle="Breadcrumbs" />

    @php
        $breadcrumbItems = [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'UI Kits']
        ];

        $breadcrumbItemsTwo = [
            ['label' => 'Home', 'href' => '/'],
            ['label' => 'UI Kits', 'href' => '/ui-kits'],
            ['label' => 'Avatar']
        ];
    @endphp

    <div class="space-y-5 sm:space-y-6">
        {{-- Default Breadcrumb --}}
        <x-common.component-card title="Default Breadcrumb">
            <div class="space-y-4">
                <x-ui.breadcrumb :items="$breadcrumbItems" />
                <x-ui.breadcrumb :items="$breadcrumbItemsTwo" />
            </div>
        </x-common.component-card>

        {{-- Breadcrumb With Icon --}}
        <x-common.component-card title="Breadcrumb With Icon">
            <div class="space-y-4">
                <x-ui.breadcrumb :items="$breadcrumbItems" variant="withIcon" />
                <x-ui.breadcrumb :items="$breadcrumbItemsTwo" variant="withIcon" />
            </div>
        </x-common.component-card>

        {{-- Chevron Breadcrumb --}}
        <x-common.component-card title="Chevron Breadcrumb">
            <div class="space-y-4">
                <x-ui.breadcrumb :items="$breadcrumbItems" variant="chevron" />
                <x-ui.breadcrumb :items="$breadcrumbItemsTwo" variant="chevron" />
            </div>
        </x-common.component-card>

        {{-- Dotted Breadcrumb --}}
        <x-common.component-card title="Dotted Breadcrumb">
            <div class="space-y-4">
                <x-ui.breadcrumb :items="$breadcrumbItems" variant="dotted" />
                <x-ui.breadcrumb :items="$breadcrumbItemsTwo" variant="dotted" />
            </div>
        </x-common.component-card>
    </div>
@endsection
