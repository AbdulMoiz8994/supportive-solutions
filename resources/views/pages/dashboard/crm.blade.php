@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-4 md:gap-6">
        <div class="col-span-12">
            <x-crm.crm-metrics :metrics="$metrics" />
        </div>

        <div class="col-span-12 xl:col-span-8">
            <x-crm.crm-statistics-chart :monthlyBilling="$monthlyBilling" />
        </div>

        <div class="col-span-12 xl:col-span-4">
            <x-crm.estimated-revenue-chart :monthlyBilling="$monthlyBilling" :revenueGoal="$revenueGoal" :clientCount="$metrics[2]['value'] ?? 0" />
        </div>

        <div class="col-span-12 xl:col-span-6">
            <x-crm.sale-category-chart :sources="$referralSources" />
        </div>

        <div class="col-span-12 xl:col-span-6">
            <x-crm.upcoming-schedule :items="$upcomingVisits" />
        </div>

        <div class="col-span-12">
            <x-crm.crm-table :orders="$recentIntakes" />
        </div>
    </div>
@endsection
