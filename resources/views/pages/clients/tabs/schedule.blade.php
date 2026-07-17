<div x-show="activeTab === 'schedule'" x-cloak>
    <div class="rounded-2xl border border-[#e6eef9] bg-white p-5">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-base font-bold text-[#0f172a]">Visits / Schedule</h3>
                <p class="text-sm text-[#64748b]">Scheduled visits and events linked to this client.</p>
            </div>
            @can('create', \App\Models\Schedule::class)
                <a href="{{ route('schedule.create', ['client_id' => $client->id]) }}" class="rounded-lg bg-[#2563eb] px-3 py-2 text-sm font-bold text-white">New Event</a>
            @endcan
        </div>

        @if ($client->schedules->isEmpty())
            <p class="text-sm text-[#64748b]">No schedule events recorded for this client.</p>
        @else
            <div class="overflow-x-auto">
                <table class="min-w-full text-left text-sm">
                    <thead>
                        <tr class="border-b border-[#e6eef9] text-xs uppercase tracking-widest text-[#94a3b8]">
                            <th class="px-3 py-2">Date</th>
                            <th class="px-3 py-2">Title</th>
                            <th class="px-3 py-2">Type</th>
                            <th class="px-3 py-2">Caregiver</th>
                            <th class="px-3 py-2">Status</th>
                            <th class="px-3 py-2"></th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($client->schedules->sortByDesc(fn ($s) => $s->start_at ?? $s->date) as $event)
                            <tr class="border-b border-[#f1f5f9]">
                                <td class="px-3 py-3">{{ ($event->start_at ?? $event->date)?->format('M j, Y') }}</td>
                                <td class="px-3 py-3 font-semibold text-[#0f172a]">{{ $event->title }}</td>
                                <td class="px-3 py-3">{{ $event->event_type_label }}</td>
                                <td class="px-3 py-3">{{ $event->employee ? $event->employee->first_name.' '.$event->employee->last_name : '—' }}</td>
                                <td class="px-3 py-3">{{ $event->status }}</td>
                                <td class="px-3 py-3 text-right">
                                    <a href="{{ route('schedule.show', $event->id) }}" target="_blank" rel="noopener noreferrer" class="font-semibold text-[#2563eb] hover:text-[#1d4ed8]">View</a>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
