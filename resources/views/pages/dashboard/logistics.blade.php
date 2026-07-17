@extends('layouts.app')

@section('content')
    <div class="space-y-6">
        <x-logistics.logistics-metrics />
        <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-logistics.delivery-statistics-chart />
                <div class="grid gap-6 sm:grid-cols-2">
                    <x-logistics.revenue-earned-chart/>
                    <x-logistics.delivery-vehicle/>
                </div>
            </div>
            <div class="lg:col-span-1">
                <div class="space-y-2 rounded-xl border bg-gray-100 p-2 dark:border-gray-800 dark:bg-white/3">
                    <x-logistics.tracking-delivery-map/>
                    <x-logistics.tracking-timeline/>
                    <x-logistics.delivery-man/>
                </div>
            </div>
        </div>
        <x-logistics.delivery-activity-table/>
    </div>
@endsection
