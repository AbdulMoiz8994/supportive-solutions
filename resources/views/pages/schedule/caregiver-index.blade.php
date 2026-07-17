@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="My Visits" />

    <div class="space-y-6">
        <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">Today's Schedule</h3>

        @forelse($schedules as $visit)
            <div class="p-6 bg-white rounded-xl dark:bg-white/[0.03] shadow-theme-xs border border-gray-100 dark:border-white/[0.05]">
                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <div class="flex items-center gap-4">
                        <div class="flex items-center justify-center w-12 h-12 bg-blue-100 text-blue-600 rounded-lg dark:bg-blue-500/10">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path></svg>
                        </div>
                        <div>
                            <h4 class="font-bold text-gray-800 dark:text-white/90">
                                {{ $visit->client?->first_name }} {{ $visit->client?->last_name }}
                            </h4>
                            <p class="text-xs text-gray-500">{{ $visit->date->format('l, M d') }} | {{ $visit->start_time }} - {{ $visit->end_time }}</p>
                        </div>
                    </div>

                    <div class="flex items-center gap-3">
                        <span class="px-3 py-1 text-xs font-medium bg-blue-50 text-blue-600 rounded-full">
                            {{ $visit->status ?? 'Scheduled' }}
                        </span>

                        @if($visit->status === 'Scheduled')
                            <form action="{{ route('schedule.clock-in', $visit->id) }}" method="POST">
                                @csrf
                                <button type="submit" class="px-4 py-2 text-sm font-bold text-white bg-green-600 rounded-lg hover:bg-green-700 transition-all shadow-lg hover:scale-105">
                                    Clock In
                                </button>
                            </form>
                        @elseif($visit->status === 'Clocked In' || $visit->status === 'In-Progress')
                            <button type="button" 
                                    onclick="document.getElementById('clock-out-modal-{{$visit->id}}').classList.remove('hidden')"
                                    class="px-4 py-2 text-sm font-bold text-white bg-red-600 rounded-lg animate-pulse hover:bg-red-700">
                                Clock Out
                            </button>
                        @elseif($visit->status === 'Completed')
                            <span class="text-xs font-bold text-green-600 flex items-center gap-1">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path></svg>
                                Task Completed ({{ $visit->total_hours }}h)
                            </span>
                        @endif
                    </div>
                </div>
            </div>

            <!-- Simple Clock Out Modal -->
            <div id="clock-out-modal-{{$visit->id}}" class="fixed inset-0 z-[999] hidden items-center justify-center bg-gray-900/50 backdrop-blur-sm">
                <div class="w-full max-w-md p-8 bg-white rounded-2xl shadow-2xl dark:bg-gray-800">
                    <h3 class="mb-4 text-xl font-bold text-gray-800 dark:text-white">Ending Your Visit</h3>
                    <form action="{{ route('schedule.clock-out', $visit->id) }}" method="POST">
                        @csrf
                        <div class="mb-6">
                            <label class="block mb-2 text-sm font-medium text-gray-700 dark:text-gray-300">Visit Notes</label>
                            <textarea name="note" class="w-full p-3 border border-gray-100 rounded-xl bg-gray-50 dark:bg-transparent" rows="3" placeholder="Describe the care provided today..."></textarea>
                        </div>
                        <div class="flex gap-4">
                            <button type="button" onclick="this.closest('.fixed').classList.add('hidden')" class="flex-1 px-4 py-2 font-bold text-gray-500 border border-gray-100 rounded-lg hover:bg-gray-50">Cancel</button>
                            <button type="submit" class="flex-1 px-4 py-2 font-bold text-white bg-red-600 rounded-lg hover:bg-red-700">End Shift</button>
                        </div>
                    </form>
                </div>
            </div>
        @empty
            <div class="p-12 text-center bg-gray-50 rounded-xl border border-dashed border-gray-200">
                <p class="text-gray-500 italic">No visits assigned for today.</p>
            </div>
        @endforelse
    </div>
@endsection
