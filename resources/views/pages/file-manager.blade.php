@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="File manager" />
    <div class="grid grid-cols-12 gap-6">
        <div class="col-span-12">
            <x-file-manager.media-card />
        </div>

        <div class="col-span-12 xl:col-span-8">
            <x-file-manager.all-folder-card />
        </div>

        <div class="col-span-12 xl:col-span-4">
            <x-file-manager.storage-chart />
        </div>

        <div class="col-span-12">
            <x-file-manager.recent-files />
        </div>
    </div>
@endsection
