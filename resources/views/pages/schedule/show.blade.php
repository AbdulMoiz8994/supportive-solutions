@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Schedule Event" />

    @if (session('success'))
        <div class="mb-4 rounded-xl border border-green-200 bg-green-50 px-4 py-3 text-sm font-medium text-green-700">
            {{ session('success') }}
        </div>
    @endif

    <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-theme-xs dark:border-white/[0.05] dark:bg-white/[0.03]">
        <div class="mb-8 flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">{{ $schedule->title }}</h2>
                <p class="mt-1 text-sm text-gray-500">{{ $schedule->event_type_label }} · {{ ($schedule->start_at ?? $schedule->date)?->format('M j, Y') }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full bg-orange-100 px-3 py-1 text-xs font-black uppercase tracking-widest text-orange-700">{{ $schedule->status }}</span>
                @can('update', $schedule)
                    <a href="{{ route('schedule.edit', $schedule->id) }}" class="rounded-lg bg-brand-600 px-4 py-2 text-sm font-bold text-white hover:bg-brand-700">Edit</a>
                @endcan
                <a href="{{ route('schedule.index') }}" class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-bold text-gray-600">Back</a>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-6 md:grid-cols-2">
            <div class="rounded-2xl border border-gray-100 p-5 dark:border-white/[0.05]">
                <h3 class="mb-4 text-xs font-black uppercase tracking-widest text-gray-400">Schedule Details</h3>
                <dl class="space-y-3 text-sm">
                    <div><dt class="font-bold text-gray-500">Date</dt><dd class="text-gray-800 dark:text-white/90">{{ ($schedule->start_at ?? $schedule->date)?->format('l, M j, Y') }}</dd></div>
                    <div><dt class="font-bold text-gray-500">Time</dt><dd class="text-gray-800 dark:text-white/90">{{ $schedule->start_time }} – {{ $schedule->end_time }}</dd></div>
                    <div><dt class="font-bold text-gray-500">Timezone</dt><dd class="text-gray-800 dark:text-white/90">{{ $schedule->timezone ?: config('app.timezone') }}</dd></div>
                    <div><dt class="font-bold text-gray-500">Location</dt><dd class="text-gray-800 dark:text-white/90">{{ $schedule->address ?: '—' }}</dd></div>
                </dl>
            </div>

            <div class="rounded-2xl border border-gray-100 p-5 dark:border-white/[0.05]">
                <h3 class="mb-4 text-xs font-black uppercase tracking-widest text-gray-400">Assignments</h3>
                <dl class="space-y-3 text-sm">
                    <div><dt class="font-bold text-gray-500">Client</dt><dd class="text-gray-800 dark:text-white/90">
                        @if ($schedule->client)
                            <a href="{{ route('clients.show', $schedule->client->id) }}" class="font-semibold text-brand-600 hover:text-brand-700 hover:underline">{{ $schedule->client->first_name }} {{ $schedule->client->last_name }}</a>
                        @else
                            —
                        @endif
                    </dd></div>
                    <div><dt class="font-bold text-gray-500">Caregiver</dt><dd class="text-gray-800 dark:text-white/90">
                        @if ($schedule->employee)
                            @if ($schedule->employee->position === 'Caregiver')
                                <a href="{{ route('caregivers.show', $schedule->employee->id) }}" class="font-semibold text-brand-600 hover:text-brand-700 hover:underline">{{ $schedule->employee->first_name }} {{ $schedule->employee->last_name }}</a>
                            @else
                                <a href="{{ route('employees.show', $schedule->employee->id) }}" class="font-semibold text-brand-600 hover:text-brand-700 hover:underline">{{ $schedule->employee->first_name }} {{ $schedule->employee->last_name }}</a>
                            @endif
                        @else
                            —
                        @endif
                    </dd></div>
                    <div><dt class="font-bold text-gray-500">Created By</dt><dd class="text-gray-800 dark:text-white/90">{{ $schedule->creator?->name ?? '—' }}</dd></div>
                </dl>
            </div>

            @if ($schedule->description)
                <div class="rounded-2xl border border-gray-100 p-5 md:col-span-2 dark:border-white/[0.05]">
                    <h3 class="mb-4 text-xs font-black uppercase tracking-widest text-gray-400">Description</h3>
                    <p class="whitespace-pre-wrap text-sm text-gray-700 dark:text-gray-300">{{ $schedule->description }}</p>
                </div>
            @endif
        </div>

        @can('update', $schedule)
            <div class="mt-8 flex flex-wrap gap-3 border-t border-gray-100 pt-6 dark:border-white/[0.05]">
                @if (! $schedule->isCancelled())
                    <form method="POST" action="{{ route('schedule.cancel', $schedule->id) }}" onsubmit="return confirm('Cancel this schedule event?')">
                        @csrf
                        <button type="submit" class="rounded-xl border border-orange-200 px-5 py-3 text-sm font-bold text-orange-700 hover:bg-orange-50">Cancel Event</button>
                    </form>
                @endif
                @can('delete', $schedule)
                    <form method="POST" action="{{ route('schedule.destroy', $schedule->id) }}" onsubmit="return confirm('Remove this schedule event?')">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-xl border border-red-200 px-5 py-3 text-sm font-bold text-red-700 hover:bg-red-50">Delete</button>
                    </form>
                @endcan
            </div>
        @endcan
    </div>
@endsection
