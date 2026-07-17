@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Faq" />
    <div class="space-y-5 sm:space-y-6">
        <x-common.component-card title="Faq 1">
            <x-faq.faq-one />
        </x-common.component-card>
        <x-common.component-card title="Faq 2">
            <x-faq.faq-two />
        </x-common.component-card>
        <x-common.component-card title="Faq 3">
            <x-faq.faq-three />
        </x-common.component-card>
    </div>
@endsection
