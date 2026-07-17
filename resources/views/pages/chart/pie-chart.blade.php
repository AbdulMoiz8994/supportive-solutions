@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Pie chart" />
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <x-common.component-card title="Pie chart 1">
            <!-- ====== Pie Chart One Start -->
            <div class="flex justify-center">
                <div id="chartSeven" class="chartDarkStyle"></div>
            </div>
            <!-- ====== Pie Chart One End -->
        </x-common.component-card>

        <x-common.component-card title="Pie chart 2">
            <!-- ====== Pie Chart Two Start -->
            <div class="flex justify-center">
                <div id="chartTwentyTwo" class="chartDarkStyle"></div>
            </div>
            <!-- ====== Pie Chart Two End -->
        </x-common.component-card>
    </div>
@endsection
