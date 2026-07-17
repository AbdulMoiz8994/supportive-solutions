@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-4 md:gap-6">
        <div class="col-span-12">
            <x-marketing.marketing-metrics />
        </div>

        <div class="col-span-12 space-y-6 xl:col-span-8">
            <x-marketing.impression-chart />
            <x-marketing.marketing-table />
        </div>

        <div class="col-span-12 space-y-6 xl:col-span-4">
            <x-marketing.traffic-stats />
            <x-marketing.traffic-source />
        </div>
    </div>
@endsection
