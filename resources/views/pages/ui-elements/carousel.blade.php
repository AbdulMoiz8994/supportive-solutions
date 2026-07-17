@extends('layouts.app')

@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Carousel" />

    <div class="grid grid-cols-1 gap-5 xl:grid-cols-2 xl:gap-6">
        <x-common.component-card title="Slides Only">
            <x-ui.carousel.slide-only />
        </x-common.component-card>

        <x-common.component-card title="With Control">
            <x-ui.carousel.with-control />
        </x-common.component-card>

        <x-common.component-card title="With Control and Indicator">
            <x-ui.carousel.with-control-and-indicators />
        </x-common.component-card>

        <x-common.component-card title="With Indicator">
            <x-ui.carousel.with-indicator />
        </x-common.component-card>

    </div>
@endsection
