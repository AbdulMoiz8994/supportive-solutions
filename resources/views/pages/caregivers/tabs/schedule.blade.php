<div class="rounded-2xl border border-[#e6eef9] bg-white p-5">
    <div class="mb-4 flex items-center justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-[#0f172a]">Schedule / Visits</h3>
            <p class="text-sm text-[#64748b]">All scheduled events assigned to {{ $caregiver->first_name }}.</p>
        </div>
        @can('create', \App\Models\Schedule::class)
            <a href="{{ route('schedule.create', ['employee_id' => $caregiver->id]) }}" class="rounded-lg bg-[#2563eb] px-3 py-2 text-sm font-bold text-white">New Event</a>
        @endcan
    </div>

    @if ($caregiver->schedules->isEmpty())
        <div class="flex flex-col items-center py-10 text-center">
            <div class="w-12 h-12 rounded-full bg-[#eff4ff] flex items-center justify-center mb-3">
                <svg class="w-6 h-6 text-[#2563eb]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2" ry="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/></svg>
            </div>
            <p class="text-sm font-bold text-[#0f172a]">No schedule events yet</p>
            <p class="text-sm text-[#94a3b8] mt-1">Events assigned to this caregiver will appear here.</p>
        </div>
    @else
        <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
                <thead>
                    <tr class="border-b border-[#e6eef9] text-xs uppercase tracking-widest text-[#94a3b8]">
                        <th class="px-3 py-2">Date</th>
                        <th class="px-3 py-2">Title</th>
                        <th class="px-3 py-2">Type</th>
                        <th class="px-3 py-2">Client</th>
                        <th class="px-3 py-2">Status</th>
                        <th class="px-3 py-2"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($caregiver->schedules->sortByDesc(fn ($s) => $s->start_at ?? $s->date) as $event)
                        <tr class="border-b border-[#f1f5f9] hover:bg-[#f8fafc]">
                            <td class="px-3 py-3 text-[#475569]">{{ ($event->start_at ?? $event->date)?->format('M j, Y') ?? '—' }}</td>
                            <td class="px-3 py-3 font-semibold text-[#0f172a]">{{ $event->title ?? '—' }}</td>
                            <td class="px-3 py-3 text-[#475569]">{{ $event->event_type_label ?? $event->type ?? '—' }}</td>
                            <td class="px-3 py-3">
                                @if ($event->client)
                                    <a href="{{ route('clients.show', $event->client->id) }}" class="font-semibold text-[#2563eb] hover:underline">
                                        {{ $event->client->first_name }} {{ $event->client->last_name }}
                                    </a>
                                @else
                                    <span class="text-[#94a3b8]">—</span>
                                @endif
                            </td>
                            <td class="px-3 py-3">
                                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold
                                    {{ $event->status === 'Completed' ? 'bg-green-100 text-green-700' :
                                       ($event->status === 'Cancelled' ? 'bg-red-100 text-red-700' : 'bg-blue-50 text-blue-700') }}">
                                    {{ $event->status ?? 'Scheduled' }}
                                </span>
                            </td>
                            <td class="px-3 py-3 text-right">
                                <a href="{{ route('schedule.show', $event->id) }}" class="font-semibold text-[#2563eb] hover:text-[#1d4ed8]">View</a>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
</div>
