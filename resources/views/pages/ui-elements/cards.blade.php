@extends('layouts.app')


@section('content')
    {{-- Page Breadcrumb --}}
    <x-common.page-breadcrumb pageTitle="Cards" />

    <div class="space-y-5 sm:space-y-6">
        <x-common.component-card title="Card with Image">
            <x-ui.cards.card-with-image />
        </x-common.component-card>

        {{-- horizontal card --}}
        <x-common.component-card title="Horizontal Card with Image">
            <x-ui.cards.horizontal-card-with-image />
        </x-common.component-card>

        <x-common.component-card title="Card with Link">
            <x-ui.cards.card-with-link />
        </x-common.component-card>


        {{-- card with icon --}}
        <x-common.component-card title="Card with Icon">
            <x-ui.cards.card-with-icon />
        </x-common.component-card>
    </div>
@endsection
