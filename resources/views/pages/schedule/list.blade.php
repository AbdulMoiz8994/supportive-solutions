@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Calendar" />

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="mb-4 flex flex-wrap items-center gap-3">
        <a href="{{ route('schedule.index') }}" class="rounded-xl bg-brand-600 px-4 py-2 text-sm font-bold text-white">Calendar views</a>
    </div>

    <div class="overflow-hidden rounded-xl border border-gray-100 bg-white shadow-theme-xs dark:border-white/[0.05] dark:bg-white/[0.03]">
        <div class="flex flex-col gap-4 border-b border-gray-100 p-6 dark:border-white/[0.05] lg:flex-row lg:items-end lg:justify-between">
            <form method="GET" action="{{ route('schedule.index') }}" class="flex w-full flex-col gap-3 lg:flex-row lg:flex-wrap lg:items-end">
                <input type="hidden" name="view" value="list">
                <div class="min-w-[220px] flex-1">
                    <label class="mb-1 block text-[10px] font-black uppercase tracking-widest text-gray-400">Search</label>
                    <input type="text" name="search" value="{{ $filters['search'] }}" maxlength="100" placeholder="Title, client, caregiver..."
                           class="w-full rounded-2xl border border-gray-100 bg-gray-50 px-4 py-3 text-sm font-medium outline-none focus:ring-2 focus:ring-brand-500/10 dark:border-white/10 dark:bg-white/5 dark:text-white">
                </div>
                <div class="flex gap-2">
                    <button type="submit" class="rounded-2xl bg-brand-600 px-5 py-3 text-sm font-black uppercase tracking-widest text-white hover:bg-brand-700">Apply</button>
                </div>
            </form>
            @if ($canManage)
                <a href="{{ route('schedule.create') }}" class="inline-flex rounded-2xl bg-brand-600 px-6 py-3 text-sm font-black uppercase tracking-widest text-white hover:bg-brand-700">New Event</a>
            @endif
        </div>

        <div class="max-w-full overflow-x-auto">
            <table class="min-w-full text-left">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/[0.05]">
                        <th class="px-6 py-4 text-[10px] font-semibold uppercase tracking-widest text-gray-700">Date / Time</th>
                        <th class="px-6 py-4 text-[10px] font-semibold uppercase tracking-widest text-gray-700">Title</th>
                        <th class="px-6 py-4 text-[10px] font-semibold uppercase tracking-widest text-gray-700">Type</th>
                        <th class="px-6 py-4 text-[10px] font-semibold uppercase tracking-widest text-gray-700">Status</th>
                        <th class="px-6 py-4 text-right text-[10px] font-semibold uppercase tracking-widest text-gray-700">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/[0.05]">
                    @forelse ($schedules as $schedule)
                        <tr class="hover:bg-gray-50 dark:hover:bg-white/[0.01]">
                            <td class="px-6 py-4">
                                <p class="text-sm font-bold text-gray-800">{{ ($schedule->start_at ?? $schedule->date)?->format('M j, Y') }}</p>
                                <p class="text-[10px] uppercase tracking-widest text-gray-500">{{ $schedule->start_time }} - {{ $schedule->end_time }}</p>
                            </td>
                            <td class="px-6 py-4 text-sm font-bold text-gray-800">{{ $schedule->title }}</td>
                            <td class="px-6 py-4 text-xs text-gray-600">{{ $schedule->event_type_label }}</td>
                            <td class="px-6 py-4"><span class="rounded-full bg-orange-100 px-3 py-1 text-[10px] font-black uppercase text-orange-700">{{ $schedule->status }}</span></td>
                            <td class="px-6 py-4 text-right">
                                <a href="{{ route('schedule.show', $schedule->id) }}" class="text-[10px] font-bold uppercase text-brand-600">View</a>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-6 py-10 text-center text-sm text-gray-500">No schedule events found.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if ($schedules->hasPages())
            <div class="border-t border-gray-100 px-6 py-4">{{ $schedules->links() }}</div>
        @endif
    </div>
@endsection
