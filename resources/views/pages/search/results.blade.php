@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Global Search Results" />

    <div class="mb-8 p-6 bg-brand-500/5 rounded-2xl border border-brand-500/10">
        <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">Results for: <span class="text-brand-500">"{{ $query }}"</span></h3>
        <p class="text-xs text-gray-500 mt-1 uppercase">Searching across Clients, Employees, and Intakes</p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
        <!-- Clients Results -->
        <div class="space-y-4">
            <h4 class="text-sm font-bold text-gray-400 uppercase tracking-widest pl-2">Clients ({{ $clients->count() }})</h4>
            @forelse($clients as $client)
                <a href="{{ route('clients.show', $client->id) }}" class="block p-5 bg-white rounded-2xl shadow-theme-xs border border-gray-100 hover:border-brand-500 transition-all dark:bg-white/[0.03] dark:border-white/[0.05]">
                    <h5 class="font-bold text-gray-800 dark:text-white/90">{{ $client->first_name }} {{ $client->last_name }}</h5>
                    <p class="text-xs text-gray-500 mt-1">ID: {{ $client->member_id ?? 'N/A' }} | {{ $client->phone ?? 'No Phone' }}</p>
                </a>
            @empty
                <p class="text-xs text-gray-400 italic pl-2">No clients found.</p>
            @endforelse
        </div>

        <!-- Employees Results -->
        <div class="space-y-4">
            <h4 class="text-sm font-bold text-gray-400 uppercase tracking-widest pl-2">Employees ({{ $employees->count() }})</h4>
            @forelse($employees as $employee)
                <a href="{{ route('employees.show', $employee->id) }}" class="block p-5 bg-white rounded-2xl shadow-theme-xs border border-gray-100 hover:border-blue-500 transition-all dark:bg-white/[0.03] dark:border-white/[0.05]">
                    <h5 class="font-bold text-gray-800 dark:text-white/90">{{ $employee->first_name }} {{ $employee->last_name }}</h5>
                    <p class="text-xs text-gray-500 mt-1">Role: {{ $employee->role }} | {{ $employee->phone ?? 'No Phone' }}</p>
                </a>
            @empty
                <p class="text-xs text-gray-400 italic pl-2">No employees found.</p>
            @endforelse
        </div>

        <!-- Intakes Results -->
        <div class="space-y-4">
            <h4 class="text-sm font-bold text-gray-400 uppercase tracking-widest pl-2">Intakes ({{ $intakes->count() }})</h4>
            @forelse($intakes as $intake)
                <a href="{{ route('intakes.show', $intake->id) }}" class="block p-5 bg-white rounded-2xl shadow-theme-xs border border-gray-100 hover:border-orange-500 transition-all dark:bg-white/[0.03] dark:border-white/[0.05]">
                    <h5 class="font-bold text-gray-800 dark:text-white/90">{{ $intake->first_name }} {{ $intake->last_name }}</h5>
                    <p class="text-xs text-gray-500 mt-1">Source: {{ $intake->source ?? 'N/A' }} | {{ $intake->phone ?? 'No Phone' }}</p>
                </a>
            @empty
                <p class="text-xs text-gray-400 italic pl-2">No intakes found.</p>
            @endforelse
        </div>
    </div>
@endsection
