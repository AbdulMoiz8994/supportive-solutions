@extends('layouts.app')

@section('content')
    <div class="space-y-5 sm:space-y-6">
        {{-- sass metrics --}}
        <x-sass.sass-metrics/>
        {{-- end sass metrics --}}
        <div class="gap-6 space-y-5 sm:space-y-6 xl:grid xl:grid-cols-12 xl:space-y-0">
            <div class="xl:col-span-7 2xl:col-span-8">
                <div class="space-y-5  sm:space-y-6">
                    <div class="grid sm:gap-6 lg:grid-cols-2">
                        <x-sass.churn-rate/>
                        <x-sass.growth-rate/>
                    </div>

                    <!-- Funnel -->
                    <x-sass.funnel-chart/>

                    <!-- Table -->
                    <x-sass.invoice-table/>
                </div>
            </div>
            <div class="space-y-5 sm:space-y-6 xl:col-span-5 2xl:col-span-4">
                <x-sass.performance-tab/>

                <!-- Activities -->
                <x-sass.activity-card/>
            </div>
        </div>
    </div>
@endsection
