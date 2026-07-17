@extends('layouts.app')

@section('content')
<div class="max-w-lg mx-auto py-12 px-4 text-center">
    <div class="w-12 h-12 mx-auto rounded-xl bg-[#f1f5f9] text-[#64748b] flex items-center justify-center mb-4">
        <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
    </div>
    <p class="text-xs font-semibold text-[#2563eb] uppercase tracking-wide mb-1">Coming soon</p>
    <h1 class="text-xl font-bold text-[#0f172a]">{{ $title }}</h1>
    <p class="text-sm text-[#64748b] mt-2 leading-relaxed">
        This module is not live yet. Use the sidebar for completed areas like Clients, Schedule, and Billing.
    </p>
    <div class="mt-6 flex flex-col sm:flex-row items-center justify-center gap-2">
        @if(!empty($backRoute))
            <x-ui.btn :href="$backRoute" variant="outline">{{ $backLabel ?? 'Back' }}</x-ui.btn>
        @endif
        <x-ui.btn href="{{ route('dashboard') }}" variant="primary">Dashboard</x-ui.btn>
    </div>
</div>
@endsection
