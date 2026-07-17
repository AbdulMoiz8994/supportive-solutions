@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Schedule" />

    <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-4">
            <h2 class="text-2xl font-bold text-gray-800 dark:text-white/90">
                {{ \Carbon\Carbon::create()->month($month)->format('F') }} {{ $year }}
            </h2>
            <div class="flex gap-2">
                <a href="?month={{ $month == 1 ? 12 : $month - 1 }}&year={{ $month == 1 ? $year - 1 : $year }}" class="p-2 bg-white rounded-lg border border-gray-100 hover:text-brand-500 shadow-theme-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"></path></svg>
                </a>
                <a href="?month={{ $month == 12 ? 1 : $month + 1 }}&year={{ $month == 12 ? $year + 1 : $year }}" class="p-2 bg-white rounded-lg border border-gray-100 hover:text-brand-500 shadow-theme-xs">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"></path></svg>
                </a>
            </div>
        </div>
        <div class="flex gap-4">
            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <span class="w-3 h-3 bg-brand-500 rounded-sm"></span> Scheduled
            </div>
            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <span class="w-3 h-3 bg-orange-500 rounded-sm"></span> In Progress
            </div>
            <div class="flex items-center gap-2 text-[10px] font-black uppercase tracking-widest text-gray-400">
                <span class="w-3 h-3 bg-green-500 rounded-sm"></span> Completed
            </div>
        </div>
    </div>

    <!-- Calendar Grid -->
    <div x-data="{ 
        selectedShift: null, 
        showModal: false,
        openShift(shift) {
            this.selectedShift = shift;
            this.showModal = true;
        }
    }">
        <div class="bg-white rounded-2xl shadow-theme-xs overflow-hidden border border-gray-100 dark:bg-white/[0.03] dark:border-white/[0.05]">
            <div class="grid grid-cols-7 border-b border-gray-100 dark:border-white/[0.05]">
                @foreach(['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'] as $dayName)
                    <div class="p-4 text-center text-xs font-bold text-gray-400 uppercase tracking-widest">{{ $dayName }}</div>
                @endforeach
            </div>

            <div class="grid grid-cols-7">
                @foreach($calendarDays as $day)
                    <div class="min-h-[140px] p-2 border-b border-r border-gray-50 dark:border-white/[0.02] {{ $day['isCurrentMonth'] ? '' : 'bg-gray-50/50' }}">
                        <div class="flex justify-between items-center mb-2 px-1">
                            <span class="text-sm font-bold {{ $day['isCurrentMonth'] ? 'text-gray-800 dark:text-white/90' : 'text-gray-300' }}">
                                {{ $day['day'] }}
                            </span>
                            @if($day['date'] === now()->toDateString())
                                <span class="px-2 py-0.5 text-[10px] bg-brand-500 text-white rounded-full">Today</span>
                            @endif
                        </div>

                        <div class="space-y-1 overflow-y-auto max-h-[100px] no-scrollbar">
                            @foreach($day['schedules'] as $shift)
                                <div 
                                    @click="openShift({{ json_encode($shift->load(['client', 'employee'])) }})"
                                    class="p-1 px-2 rounded text-[10px] font-black leading-tight truncate cursor-pointer transition-all hover:scale-95 uppercase tracking-tighter
                                    {{ $shift->status === 'Completed' ? 'bg-green-100 text-green-700 dark:bg-green-500/10 dark:text-green-400' : 
                                       (in_array($shift->status, ['In-Progress', 'Clocked In']) ? 'bg-orange-100 text-orange-700 dark:bg-orange-500/10 dark:text-orange-400' : 'bg-brand-50 text-brand-700 dark:bg-brand-500/10 dark:text-brand-400 border-l-2 border-brand-500') }}">
                                    {{ $shift->client?->first_name }} - {{ $shift->start_time }}
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

        <!-- Shift Interactivity Modal -->
        <template x-if="showModal">
            <div class="fixed inset-0 z-99999 flex items-center justify-center p-4 bg-gray-900/60 backdrop-blur-sm">
                <div class="bg-white dark:bg-gray-900 rounded-3xl shadow-2xl w-full max-w-md overflow-hidden transform transition-all" @click.away="showModal = false">
                    <div class="p-8">
                        <div class="flex items-center justify-between mb-6">
                            <span class="px-3 py-1 text-[10px] font-bold uppercase rounded-full" 
                                :class="selectedShift.status === 'Completed' ? 'bg-green-100 text-green-700' : 'bg-brand-50 text-brand-700'">
                                <span x-text="selectedShift.status || 'Scheduled'"></span>
                            </span>
                            <button @click="showModal = false" class="text-gray-400 hover:text-gray-600">
                                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M6 18L18 6M6 6l12 12"></path></svg>
                            </button>
                        </div>

                        <h3 class="text-2xl font-bold text-gray-800 dark:text-white/90 mb-2">Visit Details</h3>
                        <p class="text-sm text-gray-500 mb-8" x-text="new Date(selectedShift.date).toLocaleDateString('en-US', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })"></p>

                        <div class="space-y-6">
                            <div class="flex items-center gap-4 p-4 border border-gray-100 rounded-2xl dark:border-white/[0.05]">
                                <div class="w-12 h-12 bg-brand-500/10 text-brand-500 rounded-xl flex items-center justify-center font-bold">C</div>
                                <div>
                                    <span class="block text-[10px] font-bold text-gray-400 uppercase">Client</span>
                                    <span class="font-bold text-gray-800 dark:text-white/90" x-text="selectedShift.client.first_name + ' ' + selectedShift.client.last_name"></span>
                                </div>
                            </div>
                            <div class="flex items-center gap-4 p-4 border border-gray-100 rounded-2xl dark:border-white/[0.05]">
                                <div class="w-12 h-12 bg-orange-500/10 text-orange-500 rounded-xl flex items-center justify-center font-bold">E</div>
                                <div>
                                    <span class="block text-[10px] font-bold text-gray-400 uppercase">Caregiver</span>
                                    <span class="font-bold text-gray-800 dark:text-white/90" x-text="selectedShift.employee.first_name + ' ' + selectedShift.employee.last_name"></span>
                                </div>
                            </div>
                        </div>

                        <div class="mt-8 grid grid-cols-2 gap-4">
                            <div class="p-4 bg-gray-50 rounded-2xl dark:bg-white/[0.02]">
                                <span class="block text-[10px] font-bold text-gray-400 uppercase">Start Time</span>
                                <span class="font-bold text-gray-800 dark:text-white/90" x-text="selectedShift.start_time"></span>
                            </div>
                            <div class="p-4 bg-gray-50 rounded-2xl dark:bg-white/[0.02]">
                                <span class="block text-[10px] font-bold text-gray-400 uppercase">End Time</span>
                                <span class="font-bold text-gray-800 dark:text-white/90" x-text="selectedShift.end_time"></span>
                            </div>
                        </div>
                    </div>
                    <div class="p-6 bg-gray-50 dark:bg-white/[0.02] flex gap-3">
                        <button class="flex-1 py-3 text-sm font-bold text-gray-700 bg-white border border-gray-200 rounded-xl hover:bg-gray-50 dark:bg-gray-800 dark:border-white/[0.1] dark:text-white/90">Modify Shift</button>
                        <button class="flex-1 py-3 text-sm font-bold text-brand-500 bg-white border border-brand-500 rounded-xl hover:bg-brand-50 dark:bg-gray-800">Dispatch Update</button>
                    </div>
                </div>
            </div>
        </template>
    </div>
@endsection
