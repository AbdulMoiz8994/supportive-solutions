@extends('layouts.app')

@section('content')
@php
    $boardJs = [
        'canManage' => $canManage,
        'assignmentMap' => $assignmentMap,
        'allClients' => $allClients,
        'csrfToken' => csrf_token(),
        'storeUrl' => route('schedule.store'),
        'moveUrlTemplate' => route('schedule.board.move', ['id' => '__ID__']),
    ];
@endphp

<div class="space-y-6" x-data="visitSchedulingBoard(@js($boardJs))">
    @if (session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif
    <div x-show="dragError" x-cloak x-transition x-on:click="dragError = null"
         class="cursor-pointer rounded-xl border border-[#fecaca] bg-[#fef2f2] px-4 py-3 text-[13px] font-semibold text-[#b91c1c]"
         x-text="dragError"></div>

    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-[12px] font-semibold text-[#2563eb] mb-1">Engagement</p>
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Visit Scheduling</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5">
                {{ $weekLabel }}
                · {{ $stats['caregivers'] }} caregivers
                · {{ $stats['visits'] }} visits scheduled
                @if ($stats['unassigned'] > 0)
                    · <span class="text-[#ea580c] font-semibold">{{ $stats['unassigned'] }} unassigned</span>
                @endif
            </p>
            @if ($canManage)
                <p class="text-[11px] text-[#94a3b8] mt-1">Drag a visit onto another caregiver or day to reschedule it.</p>
            @endif
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <x-ui.btn variant="outline" size="sm" :href="route('schedule.index')">Calendar</x-ui.btn>
            @if ($canManage)
                <x-ui.btn variant="primary" size="sm" type="button" @click="openSchedule()">+ Schedule visit</x-ui.btn>
            @endif
        </div>
    </div>

    {{-- Week navigation + search --}}
    <div class="rounded-2xl border border-[#e6eef9] bg-white p-4 shadow-[0_1px_3px_rgba(15,23,42,0.04)]">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-2">
                <a href="{{ route('schedule.board', array_merge(request()->except('week'), ['week' => $prevWeek])) }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#e2e8f0] text-[#64748b] hover:border-[#2563eb] hover:text-[#2563eb]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </a>
                <span class="min-w-[180px] text-center text-[15px] font-bold text-[#0f172a]">{{ $weekLabel }}</span>
                <a href="{{ route('schedule.board', array_merge(request()->except('week'), ['week' => $nextWeek])) }}"
                   class="inline-flex h-9 w-9 items-center justify-center rounded-lg border border-[#e2e8f0] text-[#64748b] hover:border-[#2563eb] hover:text-[#2563eb]">
                    <svg class="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
                </a>
                <a href="{{ route('schedule.board', request()->except('week')) }}"
                   class="rounded-full px-3.5 py-1.5 text-[12.5px] font-semibold text-[#64748b] hover:bg-[#eef4ff] hover:text-[#2563eb]">
                    This week
                </a>
            </div>
            <form method="GET" class="flex items-center gap-2 flex-1 max-w-md lg:justify-end">
                @if (request('week'))
                    <input type="hidden" name="week" value="{{ request('week') }}">
                @endif
                <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Filter caregivers…"
                       class="w-full rounded-xl border border-[#e2e8f0] bg-white px-3 py-2 text-[13px] focus:border-[#2563eb] focus:ring-2 focus:ring-[#dbeafe] outline-none">
            </form>
        </div>
    </div>

    {{-- Board grid --}}
    <div class="rounded-2xl border border-[#e6eef9] bg-white overflow-hidden shadow-[0_1px_3px_rgba(15,23,42,0.04)]" data-testid="visit-scheduling-board">
        <div class="overflow-x-auto">
            <table class="min-w-[960px] w-full text-left border-collapse">
                <thead>
                    <tr class="bg-[#f8fafc] border-b border-[#e2e8f0]">
                        <th class="sticky left-0 z-10 bg-[#f8fafc] px-4 py-3 text-[11px] font-bold uppercase tracking-wider text-[#64748b] min-w-[180px] border-r border-[#e2e8f0]">
                            Caregiver
                        </th>
                        @foreach ($days as $day)
                            <th class="px-3 py-3 text-center min-w-[120px] {{ $day['isToday'] ? 'bg-[#eff6ff]' : '' }}">
                                <div class="text-[11px] font-bold uppercase tracking-wider {{ $day['isToday'] ? 'text-[#2563eb]' : 'text-[#64748b]' }}">{{ $day['label'] }}</div>
                                <div class="text-[12px] font-semibold text-[#0f172a] mt-0.5">{{ $day['short'] }}</div>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f5f9]">
                    @forelse ($caregivers as $caregiver)
                        <tr class="hover:bg-[#fafbfc]/80">
                            <td class="sticky left-0 z-10 bg-white px-4 py-3 border-r border-[#f1f5f9]">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-full bg-[#eff6ff] text-[#2563eb] text-[11px] font-bold flex items-center justify-center shrink-0">
                                        {{ $caregiver['initials'] }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-[13px] font-bold text-[#0f172a] truncate">{{ $caregiver['name'] }}</div>
                                        <div class="text-[11px] text-[#94a3b8]">{{ $caregiver['client_count'] }} assigned {{ Str::plural('client', $caregiver['client_count']) }}</div>
                                    </div>
                                </div>
                            </td>
                            @foreach ($days as $day)
                                @php $visits = $caregiver['days'][$day['key']] ?? []; @endphp
                                <td class="px-2 py-2 align-top {{ $day['isToday'] ? 'bg-[#f8fbff]/60' : '' }}">
                                    <div class="space-y-1.5 min-h-[52px] rounded-lg transition-colors"
                                         @if ($canManage)
                                             x-on:dragover.prevent="dragOverKey = '{{ $caregiver['id'] }}|{{ $day['date'] }}'"
                                             x-on:dragleave="dragOverKey === '{{ $caregiver['id'] }}|{{ $day['date'] }}' && (dragOverKey = null)"
                                             x-on:drop.prevent="onDrop({{ $caregiver['id'] }}, '{{ $day['date'] }}')"
                                             :class="dragOverKey === '{{ $caregiver['id'] }}|{{ $day['date'] }}' ? 'bg-[#eff6ff] ring-2 ring-inset ring-[#93c5fd]' : ''"
                                         @endif>
                                        @foreach ($visits as $visit)
                                            <a href="{{ $visit['url'] }}"
                                               @if ($canManage)
                                                   draggable="true"
                                                   x-on:dragstart="onDragStart($event, {{ $visit['id'] }})"
                                                   x-on:dragend="draggingVisitId = null"
                                               @endif
                                               class="block rounded-lg border border-[#dbeafe] bg-[#eff6ff] px-2 py-1.5 hover:border-[#2563eb] transition-colors group {{ $canManage ? 'cursor-move' : '' }}"
                                               data-testid="visit-board-card">
                                                <div class="text-[11px] font-bold text-[#1d4ed8] truncate group-hover:text-[#2563eb]">{{ $visit['client_name'] }}</div>
                                                <div class="text-[10px] text-[#64748b]">{{ $visit['start_time'] }}–{{ $visit['end_time'] }}</div>
                                            </a>
                                        @endforeach
                                        @if ($canManage)
                                            <button type="button"
                                                    @click="openSchedule({{ $caregiver['id'] }}, '{{ $day['date'] }}')"
                                                    class="w-full rounded-lg border border-dashed border-[#cbd5e1] px-2 py-1 text-[10px] font-semibold text-[#94a3b8] hover:border-[#2563eb] hover:text-[#2563eb] transition-colors">
                                                +
                                            </button>
                                        @endif
                                    </div>
                                </td>
                            @endforeach
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ count($days) + 1 }}" class="px-5 py-12 text-center text-[14px] text-[#64748b]">
                                No active caregivers match your filter.
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    {{-- Unassigned + clients without visits --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="rounded-2xl border border-[#e6eef9] bg-white p-5">
            <h3 class="text-[14px] font-bold text-[#0f172a] mb-3">Unassigned visits this week</h3>
            @if (count($unassignedVisits) === 0)
                <p class="text-[13px] text-[#94a3b8]">All care visits have a caregiver assigned.</p>
            @else
                <div class="space-y-2">
                    @foreach ($unassignedVisits as $visit)
                        <a href="{{ $visit['url'] }}" class="flex items-center justify-between rounded-xl border border-[#fed7aa] bg-[#fff7ed] px-3 py-2.5 hover:border-[#ea580c]">
                            <div>
                                <div class="text-[13px] font-bold text-[#9a3412]">{{ $visit['client_name'] }}</div>
                                <div class="text-[11px] text-[#c2410c]">{{ $visit['date'] }} · {{ $visit['start_time'] }}–{{ $visit['end_time'] }}</div>
                            </div>
                            <span class="text-[11px] font-bold text-[#ea580c]">Assign →</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>

        <div class="rounded-2xl border border-[#e6eef9] bg-white p-5">
            <h3 class="text-[14px] font-bold text-[#0f172a] mb-3">Active clients without visits this week</h3>
            @if (count($clientsWithoutVisits) === 0)
                <p class="text-[13px] text-[#94a3b8]">Every active client has at least one visit scheduled.</p>
            @else
                <div class="flex flex-wrap gap-2">
                    @foreach ($clientsWithoutVisits as $client)
                        @if ($canManage)
                            <button type="button" @click="openSchedule(null, '{{ today()->toDateString() }}', {{ $client['id'] }})"
                                    class="rounded-full border border-[#e2e8f0] bg-[#f8fafc] px-3 py-1.5 text-[12px] font-semibold text-[#475569] hover:border-[#2563eb] hover:text-[#2563eb]">
                                {{ $client['name'] }}
                            </button>
                        @else
                            <span class="rounded-full border border-[#e2e8f0] bg-[#f8fafc] px-3 py-1.5 text-[12px] font-semibold text-[#475569]">{{ $client['name'] }}</span>
                        @endif
                    @endforeach
                </div>
            @endif
        </div>
    </div>

    {{-- Quick schedule modal --}}
    @if ($canManage)
        <div x-show="modalOpen" x-cloak
             class="fixed inset-0 z-[999] flex items-center justify-center bg-gray-900/50 backdrop-blur-sm p-4"
             @keydown.escape.window="modalOpen = false">
            <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" @click.outside="modalOpen = false">
                <h3 class="text-lg font-bold text-[#0f172a] mb-1">Schedule care visit</h3>
                <p class="text-[13px] text-[#64748b] mb-5">Pair a caregiver with a client for a visit this week.</p>

                <form method="POST" action="{{ route('schedule.store') }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="redirect_to" value="board">
                    <input type="hidden" name="event_type" value="care_visit">
                    <input type="hidden" name="timezone" value="{{ config('app.timezone', 'America/Detroit') }}">

                    <div>
                        <label class="block text-[12px] font-bold text-[#64748b] mb-1">Caregiver <span class="text-red-500">*</span></label>
                        <select name="employee_id" x-model="form.employee_id" required
                                @change="onCaregiverChange()"
                                class="w-full rounded-xl border border-[#e2e8f0] px-3 py-2.5 text-[13px]">
                            <option value="">Select caregiver</option>
                            @foreach ($caregivers as $cg)
                                <option value="{{ $cg['id'] }}">{{ $cg['name'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div>
                        <label class="block text-[12px] font-bold text-[#64748b] mb-1">Client <span class="text-red-500">*</span></label>
                        <select name="client_id" x-model="form.client_id" required
                                @change="updateTitle()"
                                class="w-full rounded-xl border border-[#e2e8f0] px-3 py-2.5 text-[13px]">
                            <option value="">Select client</option>
                            <template x-for="client in availableClients" :key="client.id">
                                <option :value="client.id" x-text="client.name"></option>
                            </template>
                        </select>
                        <p x-show="form.employee_id && availableClients.length === 0" class="text-[11px] text-[#ea580c] mt-1">
                            No active assignments — pick any client below or assign on the caregiver profile first.
                        </p>
                    </div>

                    <div>
                        <label class="block text-[12px] font-bold text-[#64748b] mb-1">Title <span class="text-red-500">*</span></label>
                        <input type="text" name="title" x-model="form.title" required maxlength="255"
                               class="w-full rounded-xl border border-[#e2e8f0] px-3 py-2.5 text-[13px]">
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <div>
                            <label class="block text-[12px] font-bold text-[#64748b] mb-1">Date</label>
                            <input type="date" name="date" x-model="form.date" required
                                   class="w-full rounded-xl border border-[#e2e8f0] px-3 py-2.5 text-[13px]">
                        </div>
                        <div>
                            <label class="block text-[12px] font-bold text-[#64748b] mb-1">Start</label>
                            <input type="time" name="start_time" x-model="form.start_time" required
                                   class="w-full rounded-xl border border-[#e2e8f0] px-3 py-2.5 text-[13px]">
                        </div>
                        <div>
                            <label class="block text-[12px] font-bold text-[#64748b] mb-1">End</label>
                            <input type="time" name="end_time" x-model="form.end_time" required
                                   class="w-full rounded-xl border border-[#e2e8f0] px-3 py-2.5 text-[13px]">
                        </div>
                    </div>

                    <div class="flex justify-end gap-2 pt-2">
                        <button type="button" @click="modalOpen = false"
                                class="rounded-xl border border-[#e2e8f0] px-4 py-2.5 text-[13px] font-semibold text-[#64748b]">Cancel</button>
                        <button type="submit"
                                class="rounded-xl bg-[#2563eb] px-4 py-2.5 text-[13px] font-semibold text-white hover:bg-[#1d4ed8]">
                            Save visit
                        </button>
                    </div>
                </form>
            </div>
        </div>
    @endif
</div>

@push('scripts')
<script>
function visitSchedulingBoard(config) {
    const allClients = config.allClients || [];

    return {
        modalOpen: false,
        assignmentMap: config.assignmentMap || {},
        allClients,
        availableClients: [],
        draggingVisitId: null,
        dragOverKey: null,
        dragError: null,
        form: {
            employee_id: '',
            client_id: '',
            title: '',
            date: '{{ today()->toDateString() }}',
            start_time: '09:00',
            end_time: '13:00',
        },

        onDragStart(event, visitId) {
            this.draggingVisitId = visitId;
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', String(visitId));
        },

        async onDrop(employeeId, date) {
            const visitId = this.draggingVisitId;
            this.dragOverKey = null;
            this.draggingVisitId = null;
            if (!visitId) return;

            this.dragError = null;

            try {
                const response = await fetch(config.moveUrlTemplate.replace('__ID__', visitId), {
                    method: 'PATCH',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': config.csrfToken,
                    },
                    body: JSON.stringify({ employee_id: employeeId, date }),
                });

                const data = await response.json().catch(() => ({}));

                if (!response.ok) {
                    this.dragError = data.message || 'Could not reschedule this visit.';
                    return;
                }

                window.location.reload();
            } catch (e) {
                this.dragError = 'Could not reschedule this visit. Please try again.';
            }
        },

        openSchedule(employeeId = null, date = null, clientId = null) {
            this.form.employee_id = employeeId ? String(employeeId) : '';
            this.form.client_id = clientId ? String(clientId) : '';
            this.form.date = date || '{{ today()->toDateString() }}';
            this.form.start_time = '09:00';
            this.form.end_time = '13:00';
            this.onCaregiverChange();
            if (clientId) {
                this.form.client_id = String(clientId);
            }
            this.updateTitle();
            this.modalOpen = true;
        },

        onCaregiverChange() {
            const assigned = this.assignmentMap[this.form.employee_id] || [];
            this.availableClients = assigned.length > 0 ? assigned : this.allClients;
            if (this.form.client_id && !this.availableClients.find(c => String(c.id) === String(this.form.client_id))) {
                const match = this.allClients.find(c => String(c.id) === String(this.form.client_id));
                if (match) {
                    this.availableClients = [match, ...this.availableClients];
                }
            }
        },

        updateTitle() {
            const client = this.availableClients.find(c => String(c.id) === String(this.form.client_id))
                || this.allClients.find(c => String(c.id) === String(this.form.client_id));
            if (client) {
                this.form.title = 'Care visit — ' + client.name;
            }
        },
    };
}
</script>
@endpush
@endsection
