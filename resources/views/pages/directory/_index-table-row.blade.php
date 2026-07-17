@php
    $rowClass = 'border-b border-[#f1f5f9] transition hover:bg-[#f8fafc]';
    $cellClass = 'px-3.5 py-2.5 text-[13px] text-[#334155]';
@endphp

<tr class="{{ $rowClass }} cursor-pointer" onclick="window.location='{{ route('directory.show', $contact->id) }}'">
    @switch($tableKey)
        @case('payers')
            <td class="{{ $cellClass }}">
                <div class="flex items-center">
                    <span class="mr-2 inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg bg-gradient-to-br from-[#2563eb] to-[#1e40af] text-[11px] font-bold text-white">{{ $contact->initials() }}</span>
                    <span class="font-semibold text-[#0f172a]">{{ $contact->name }}</span>
                </div>
            </td>
            <td class="{{ $cellClass }}">
                @if ($contact->claimChannelLabel())
                    <span class="inline-flex rounded-md px-1.5 py-0.5 text-[11px] font-bold {{ $contact->claimChannelBadgeClasses() }}">{{ $contact->claimChannelLabel() }}</span>
                @else
                    <span class="text-[#94a3b8]">—</span>
                @endif
            </td>
            <td class="{{ $cellClass }}">
                @if ($contact->phone)
                    {{ $contact->phone }}
                    @if ($contact->job_title)<span class="block text-[10.5px] text-[#94a3b8]">{{ $contact->job_title }}</span>@endif
                @else
                    <span class="text-[#94a3b8]">—</span>
                @endif
            </td>
            <td class="{{ $cellClass }} font-semibold tabular-nums text-[#0f172a]">{{ $contact->formattedContractedRate() ?: '—' }}</td>
            <td class="{{ $cellClass }} font-semibold tabular-nums text-[#0f172a]">{{ $contact->clients_count }}</td>
            <td class="{{ $cellClass }}"><x-ui.pill :variant="$contact->is_active ? 'green' : 'gray'" size="xs">{{ $contact->is_active ? 'Active' : 'Inactive' }}</x-ui.pill></td>
            @break

        @case('asws')
            <td class="{{ $cellClass }}">
                <div class="flex items-center">
                    <span class="mr-2 inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg bg-gradient-to-br from-[#8b5cf6] to-[#6d28d9] text-[11px] font-bold text-white">{{ $contact->initials() }}</span>
                    <span class="font-semibold text-[#0f172a]">{{ $contact->name }}</span>
                </div>
            </td>
            <td class="{{ $cellClass }}">{{ $contact->county ?: ($contact->city ?: '—') }}</td>
            <td class="{{ $cellClass }}">{{ $contact->phone ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->fax ?: '—' }}</td>
            <td class="{{ $cellClass }} font-semibold tabular-nums text-[#0f172a]">{{ $contact->clients_count }}</td>
            <td class="{{ $cellClass }}"><x-ui.pill :variant="$contact->is_active ? 'green' : 'gray'" size="xs">{{ $contact->is_active ? 'Active' : 'Inactive' }}</x-ui.pill></td>
            @break

        @case('coordinators')
            <td class="{{ $cellClass }}">
                <div class="flex items-center">
                    <span class="mr-2 inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg bg-gradient-to-br from-[#0ea5e9] to-[#0369a1] text-[11px] font-bold text-white">{{ $contact->initials() }}</span>
                    <span class="font-semibold text-[#0f172a]">{{ $contact->name }}</span>
                </div>
            </td>
            <td class="{{ $cellClass }}">{{ $contact->clinic_name ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->phone ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->email ?: '—' }}</td>
            <td class="{{ $cellClass }} font-semibold tabular-nums text-[#0f172a]">{{ $contact->clients_count }}</td>
            <td class="{{ $cellClass }}"><x-ui.pill :variant="$contact->is_active ? 'green' : 'gray'" size="xs">{{ $contact->is_active ? 'Active' : 'Inactive' }}</x-ui.pill></td>
            @break

        @case('physicians')
            <td class="{{ $cellClass }}">
                <div class="flex items-center">
                    <span class="mr-2 inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg bg-gradient-to-br from-[#10b981] to-[#047857] text-[11px] font-bold text-white">{{ $contact->initials() }}</span>
                    <span class="font-semibold text-[#0f172a]">{{ $contact->name }}</span>
                </div>
            </td>
            <td class="{{ $cellClass }}">{{ $contact->provider_id ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->clinic_name ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->fax ?: '—' }}</td>
            <td class="{{ $cellClass }} font-semibold tabular-nums text-[#0f172a]">{{ $contact->clients_count }}</td>
            <td class="{{ $cellClass }}"><x-ui.pill :variant="$contact->is_active ? 'green' : 'gray'" size="xs">{{ $contact->is_active ? 'Active' : 'Inactive' }}</x-ui.pill></td>
            @break

        @case('referrals')
            <td class="{{ $cellClass }}">
                <div class="flex items-center">
                    <span class="mr-2 inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg bg-gradient-to-br from-[#f59e0b] to-[#b45309] text-[11px] font-bold text-white">{{ $contact->initials() }}</span>
                    <span class="font-semibold text-[#0f172a]">{{ $contact->name }}</span>
                </div>
            </td>
            <td class="{{ $cellClass }}">{{ $contact->job_title ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->phone ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->fax ?: '—' }}</td>
            <td class="{{ $cellClass }} font-semibold tabular-nums text-[#0f172a]">{{ $contact->clients_count }}</td>
            <td class="{{ $cellClass }}"><x-ui.pill :variant="$contact->is_active ? 'green' : 'gray'" size="xs">{{ $contact->is_active ? 'Active' : 'Inactive' }}</x-ui.pill></td>
            @break

        @case('state_systems')
            <td class="{{ $cellClass }}">
                <div class="flex items-center">
                    <span class="mr-2 inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg bg-gradient-to-br from-[#475569] to-[#1e293b] text-[11px] font-bold text-white">{{ $contact->initials() }}</span>
                    <span class="font-semibold text-[#0f172a]">{{ $contact->name }}</span>
                </div>
            </td>
            <td class="{{ $cellClass }}">{{ \Illuminate\Support\Str::limit($contact->job_title, 40) ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->clinic_name ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->provider_id ?: 'RPA' }}</td>
            <td class="{{ $cellClass }} font-semibold tabular-nums text-[#0f172a]">{{ $contact->clients_count }}</td>
            <td class="{{ $cellClass }}"><x-ui.pill :variant="$contact->is_active ? 'green' : 'gray'" size="xs">{{ $contact->is_active ? 'Active' : 'Inactive' }}</x-ui.pill></td>
            @break

        @case('vendors')
            <td class="{{ $cellClass }}">
                <div class="flex items-center">
                    <span class="mr-2 inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg bg-gradient-to-br from-[#ec4899] to-[#9d174d] text-[11px] font-bold text-white">{{ $contact->initials() }}</span>
                    <span class="font-semibold text-[#0f172a]">{{ $contact->name }}</span>
                </div>
            </td>
            <td class="{{ $cellClass }}">{{ $contact->job_title ?: 'Vendor / integration' }}</td>
            <td class="{{ $cellClass }}">{{ \Illuminate\Support\Str::limit($contact->data_flow, 50) ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->phone ?: '—' }}</td>
            <td class="{{ $cellClass }}"><x-ui.pill :variant="$contact->is_active ? 'green' : 'gray'" size="xs">{{ $contact->is_active ? 'Active' : 'Inactive' }}</x-ui.pill></td>
            @break

        @case('pharmacies')
            <td class="{{ $cellClass }}">
                <div class="flex items-center">
                    <span class="mr-2 inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg bg-gradient-to-br from-[#0ea5e9] to-[#0e7490] text-[11px] font-bold text-white">{{ $contact->initials() }}</span>
                    <span class="font-semibold text-[#0f172a]">{{ $contact->name }}</span>
                </div>
            </td>
            <td class="{{ $cellClass }}">{{ $contact->city ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->phone ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->fax ?: '—' }}</td>
            <td class="{{ $cellClass }} font-semibold tabular-nums text-[#0f172a]">{{ $contact->clients_count }}</td>
            <td class="{{ $cellClass }}"><x-ui.pill :variant="$contact->is_active ? 'green' : 'gray'" size="xs">{{ $contact->is_active ? 'Active' : 'Inactive' }}</x-ui.pill></td>
            @break

        @default
            <td class="{{ $cellClass }}">
                <div class="flex items-center">
                    <span class="mr-2 inline-flex h-[30px] w-[30px] items-center justify-center rounded-lg bg-gradient-to-br from-[#2563eb] to-[#1e40af] text-[11px] font-bold text-white">{{ $contact->initials() }}</span>
                    <span class="font-semibold text-[#0f172a]">{{ $contact->name }}</span>
                </div>
            </td>
            <td class="{{ $cellClass }}"><x-ui.pill variant="blue" size="xs">{{ $contact->type }}</x-ui.pill></td>
            <td class="{{ $cellClass }}">{{ $contact->clinic_name ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->phone ?: '—' }}</td>
            <td class="{{ $cellClass }}">{{ $contact->email ?: '—' }}</td>
            <td class="{{ $cellClass }} font-semibold tabular-nums text-[#0f172a]">{{ $contact->clients_count }}</td>
            <td class="{{ $cellClass }}"><x-ui.pill :variant="$contact->is_active ? 'green' : 'gray'" size="xs">{{ $contact->is_active ? 'Active' : 'Inactive' }}</x-ui.pill></td>
    @endswitch
    <td class="{{ $cellClass }} text-right" onclick="event.stopPropagation()">
        <a href="{{ route('directory.show', $contact->id) }}" class="text-[12px] font-semibold text-[#2563eb] hover:text-[#1d4ed8]">Open ›</a>
    </td>
</tr>
