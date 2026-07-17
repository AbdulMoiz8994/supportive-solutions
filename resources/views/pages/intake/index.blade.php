@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Intake Management" />

    <div class="overflow-hidden rounded-xl bg-white dark:bg-white/[0.03] shadow-theme-xs">
        <div class="flex flex-col gap-4 p-6 sm:flex-row sm:items-center sm:justify-between border-b border-gray-100 dark:border-white/[0.05]">
            <div>
                <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">Incoming Intakes</h3>
                <p class="text-sm text-gray-500 font-medium mt-1">Capture and track potential client intakes.</p>
            </div>
            <div class="flex items-center gap-3">
                <a href="{{ route('intakes.wizard') }}" class="px-4 py-2 text-sm font-medium text-white transition rounded-lg bg-brand-500 hover:bg-brand-600 inline-flex items-center gap-2">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7V5a2 2 0 0 1 2-2h2"/><path d="M16 3h2a2 2 0 0 1 2 2v2"/><path d="M20 17v2a2 2 0 0 1-2 2h-2"/><path d="M8 21H6a2 2 0 0 1-2-2v-2"/><rect x="8" y="8" width="8" height="8" rx="1"/></svg>
                    Add New Intake
                </a>
            </div>
        </div>

        <div class="p-4 grid grid-cols-1 md:grid-cols-4 gap-4 bg-gray-50/50 dark:bg-white/[0.01]">
            <!-- Tracking Stats -->
            <div class="p-4 bg-white dark:bg-dark-900 border border-gray-100 dark:border-white/[0.05] rounded-xl text-center">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">New Intakes</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $intakes->filter(fn ($i) => $i->displayStatus() === 'New' || $i->displayStatus() === 'New Lead')->count() }}</p>
            </div>
            <div class="p-4 bg-white dark:bg-dark-900 border border-gray-100 dark:border-white/[0.05] rounded-xl text-center">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Contacted</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $intakes->filter(fn ($i) => $i->displayStatus() === 'Contacted')->count() }}</p>
            </div>
            <div class="p-4 bg-white dark:bg-dark-900 border border-gray-100 dark:border-white/[0.05] rounded-xl text-center">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Converted</p>
                <p class="text-2xl font-bold text-green-600">{{ $intakes->filter(fn ($i) => $i->displayStatus() === 'Converted')->count() }}</p>
            </div>
            <div class="p-4 bg-white dark:bg-dark-900 border border-gray-100 dark:border-white/[0.05] rounded-xl text-center">
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest mb-1">Total</p>
                <p class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $intakes->count() }}</p>
            </div>
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="min-w-full text-left">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                        <th class="px-6 py-4 font-semibold text-gray-700 dark:text-white/90">Client Name</th>
                        <th class="px-6 py-4 font-semibold text-gray-700 dark:text-white/90">Source</th>
                        <th class="px-6 py-4 font-semibold text-gray-700 dark:text-white/90">Created Date</th>
                        <th class="px-6 py-4 font-semibold text-gray-700 dark:text-white/90">Status</th>
                        <th class="px-6 py-4 font-semibold text-gray-700 dark:text-white/90 text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.05]">
                    @forelse($intakes as $intake)
                        @php
                            $displayStatus = $intake->displayStatus();
                            $statusColor = match($displayStatus) {
                                'Converted' => 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400',
                                'Contacted' => 'bg-orange-100 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400',
                                'New', 'New Lead' => 'bg-blue-100 text-blue-700 dark:bg-blue-500/10 dark:text-blue-400',
                                default => 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-400',
                            };
                        @endphp
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.01]">
                            <td class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                                <div class="flex items-center gap-3">
                                    <div class="w-8 h-8 flex items-center justify-center bg-brand-500/10 text-brand-500 rounded-lg text-xs font-bold">
                                        {{ substr($intake->first_name, 0, 1) }}
                                    </div>
                                    <div>
                                        <p class="font-medium text-gray-800 dark:text-white/90">{{ $intake->first_name }} {{ $intake->last_name }}</p>
                                        <p class="text-xs text-gray-500">{{ $intake->phone ?? 'No Phone' }}</p>
                                    </div>
                                </div>
                            </td>
                            <td class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                                <span class="px-2 py-1 text-xs bg-gray-100 rounded-md dark:bg-white/5">{{ $intake->source ?? 'Unknown' }}</span>
                            </td>
                            <td class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                                <p class="text-sm text-gray-600 dark:text-gray-400">{{ $intake->created_at->format('M d, Y') }}</p>
                            </td>
                            <td class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.05]">
                                <span class="px-3 py-1 text-[10px] font-black rounded-full uppercase tracking-widest {{ $statusColor }}">
                                    {{ $displayStatus }}
                                </span>
                            </td>
                            <td class="px-6 py-4 border-b border-gray-100 dark:border-white/[0.05] text-right">
                                <a href="{{ route('intakes.show', $intake->id) }}" class="text-brand-500 hover:text-brand-600 font-medium text-sm">View Profile</a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="5" class="px-6 py-20 text-center text-gray-500">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-12 h-12 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                                    <p>No intakes found. New leads will appear here.</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
