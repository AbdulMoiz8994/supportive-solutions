@extends('layouts.app')

@section('content')
    <div class="grid grid-cols-12 gap-4 md:gap-6">
        <div class="col-span-12">
            <x-stocks.stock-metrics />
        </div>

        <div class="col-span-12 space-y-6 xl:col-span-8">
            <x-stocks.portfolio-performance-chart />
            <x-stocks.trending-stocks />
        </div>

        <div class="col-span-12 space-y-6 xl:col-span-4">
            <x-stocks.dividend-chart />
            <x-stocks.my-watchlists />
        </div>

        <div class="col-span-12">
            <x-stocks.stocks-table />
        </div>
    </div>
@endsection
