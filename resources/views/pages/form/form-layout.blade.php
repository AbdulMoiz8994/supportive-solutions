@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="From Elements" />
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="space-y-5 sm:space-y-6">
            <x-form.example-form.basic-form />
            <x-form.example-form.example-form-one />
        </div>
        <div class="space-y-6">
            <x-form.example-form.example-form-with-icon />
            <x-form.example-form.example-form-two />
        </div>
    </div>
@endsection
