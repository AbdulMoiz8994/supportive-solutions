@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="New Schedule Event" />

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-theme-xs dark:border-white/[0.05] dark:bg-white/[0.03]">
        <form method="POST" action="{{ route('schedule.store') }}" class="space-y-6">
            @csrf
            @include('pages.schedule._form', [
                'clients' => $clients,
                'employees' => $employees,
                'eventTypes' => $eventTypes,
                'preselectedClientId' => $preselectedClientId ?? null,
                'preselectedEmployeeId' => $preselectedEmployeeId ?? null,
            ])

            <div class="flex gap-3 border-t border-gray-100 pt-6 dark:border-white/[0.05]">
                <a href="{{ route('schedule.index') }}" class="rounded-xl border border-gray-200 px-5 py-3 text-sm font-bold text-gray-600">Cancel</a>
                <button type="submit" class="rounded-xl bg-brand-600 px-6 py-3 text-sm font-black uppercase tracking-widest text-white hover:bg-brand-700">Create Event</button>
            </div>
        </form>
    </div>
@endsection
