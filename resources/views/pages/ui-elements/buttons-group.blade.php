@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Buttons Group" />

    <div class="space-y-5 sm:space-y-6">

        <x-common.component-card title="Primary Button Group">
            <x-example.buttons-group.primary-button-group />
        </x-common.component-card>

        <x-common.component-card title="Primary Button Group with Left Icon">
            <x-example.buttons-group.button-group-with-left-icon />
        </x-common.component-card>

        <x-common.component-card title="Primary Button Group with Right Icon">
            <x-example.buttons-group.button-group-with-right-icon />
        </x-common.component-card>

        <x-common.component-card title="Secondary Button Group">
            <x-example.buttons-group.secondary-button-group />
        </x-common.component-card>

        <x-common.component-card title="Secondary Button Group with Left Icon">
            <x-example.buttons-group.secondary-button-group-with-left-icon />
        </x-common.component-card>

        <x-common.component-card title="Secondary Button Group with Right Icon">
            <x-example.buttons-group.secondary-button-group-with-right-icon />
        </x-common.component-card>

    </div>
@endsection
