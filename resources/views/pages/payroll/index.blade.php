@extends('layouts.app')

@section('content')
@php
    $queryParams = fn (array $extra = []) => array_merge(
        request()->except('page'),
        array_filter([
            'period'         => $filters['period'],
            'status'         => $filters['status'] ?? null,
            'search'         => $filters['search'] ?? null,
            'caregiver_type' => $filters['caregiver_type'] ?? null,
            'live_in'        => $filters['live_in'] ?? null,
            'in_grace'       => $filters['in_grace'] ?? null,
        ]),
        $extra
    );
@endphp
<div class="space-y-6">
    {{-- Header --}}
    <div>
        <p class="text-[12px] font-semibold text-[#2563eb] mb-1">Financial</p>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Payroll</h1>
            <div class="flex flex-col items-stretch sm:items-end gap-2 shrink-0">
                @can('buildBatch', \App\Models\PayRecord::class)
                    @if(auth()->user()->isSuperAdmin() && ($batchOrganizationOptions ?? collect())->count() > 1)
                        <select name="organization_id" form="payroll-build-batch-form" required
                                class="h-9 px-3 text-[12px] border border-[#e2e8f0] rounded-xl bg-white text-[#475569] sm:min-w-[200px]">
                            <option value="">Select organization…</option>
                            @foreach($batchOrganizationOptions as $org)
                                <option value="{{ $org->id }}">{{ $org->name }}</option>
                            @endforeach
                        </select>
                    @endif
                @endcan
                <div class="flex items-center justify-end gap-2 flex-nowrap">
                    @can('export', \App\Models\PayRecord::class)
                    <a href="{{ route('payroll.export', request()->query()) }}"
                       class="inline-flex items-center justify-center h-9 px-4 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition whitespace-nowrap">
                        Export
                    </a>
                    @endcan
                    <a href="{{ config('payroll.accountants_world_url') }}" target="_blank" rel="noopener"
                       class="inline-flex items-center justify-center h-9 px-4 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition whitespace-nowrap">
                        Open payroll portal
                    </a>
                    @can('viewAny', \App\Models\PayRecord::class)
                    <a href="{{ route('payroll.batch-queue') }}"
                       class="inline-flex items-center justify-center h-9 px-4 text-[12px] font-semibold text-white bg-[#16a34a] rounded-xl hover:bg-[#15803d] transition shadow-sm whitespace-nowrap">
                        Approval Queue
                    </a>
                    @endcan
                    @can('buildBatch', \App\Models\PayRecord::class)
                    <form id="payroll-build-batch-form" action="{{ route('payroll.build-batch') }}" method="POST" class="inline-flex">
                        @csrf
                        <input type="hidden" name="period" value="{{ $filters['period'] }}">
                        @if(auth()->user()->isSuperAdmin() && ($batchOrganizationOptions ?? collect())->count() === 1)
                            <input type="hidden" name="organization_id" value="{{ $batchOrganizationOptions->first()->id }}">
                        @endif
                        <button type="submit" class="inline-flex items-center justify-center h-9 px-4 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition whitespace-nowrap">
                            Build batch
                        </button>
                    </form>
                    @endcan
                </div>
            </div>
        </div>
        <p class="text-[13px] text-[#64748b] mt-1.5">{{ $subtitle }}</p>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif
    @if(session('warning'))
        <x-ui.alert variant="warning">{{ session('warning') }}</x-ui.alert>
    @endif

    {{-- Period selector --}}
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('payroll', $queryParams(['period' => $prevPeriod->format('Y-m')])) }}"
               class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-[#e2e8f0] bg-white text-[#64748b] hover:border-[#cbd5e1]">&larr;</a>
            <span class="px-4 py-2 text-[13px] font-semibold text-[#0f172a] bg-white border border-[#e2e8f0] rounded-xl min-w-[120px] text-center">
                {{ $period->format('M Y') }}
            </span>
            <a href="{{ route('payroll', $queryParams(['period' => $nextPeriod->format('Y-m')])) }}"
               class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-[#e2e8f0] bg-white text-[#64748b] hover:border-[#cbd5e1]">&rarr;</a>
            @foreach($periodOptions as $opt)
                <a href="{{ route('payroll', $queryParams(['period' => $opt['value']])) }}"
                   class="px-3.5 py-1.5 text-[12px] font-semibold rounded-full border transition {{ $filters['period'] === $opt['value'] ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0] hover:border-[#cbd5e1]' }}">
                    {{ $opt['label'] }}
                </a>
            @endforeach
            <span class="px-3.5 py-1.5 text-[12px] font-semibold rounded-full bg-white text-[#475569] border border-[#e2e8f0]">{{ $period->format('Y') }} YTD</span>
        </div>
        <p class="text-[12px] text-[#94a3b8]">Hours, gross, and payout status reload for the selected pay cycle.</p>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
        @include('pages.payroll.partials.summary-stat', [
            'label' => 'Gross this cycle ('.$period->format('M').')',
            'value' => '$'.number_format($summary['gross_amount'], 0),
            'sub' => $summary['caregiver_count'].' caregivers · W-2',
        ])
        @include('pages.payroll.partials.summary-stat', [
            'label' => 'Ready for batch',
            'value' => (string) $summary['ready_count'],
            'sub' => 'verified · grace cleared',
            'valueClass' => 'text-[#10b981]',
        ])
        @include('pages.payroll.partials.summary-stat', [
            'label' => 'In grace window',
            'value' => (string) $summary['in_grace_count'],
            'sub' => '~'.config('payroll.grace_days').' day hold',
            'valueClass' => 'text-[#f59e0b]',
        ])
        @include('pages.payroll.partials.summary-stat', [
            'label' => 'Late form — rolled',
            'value' => (string) $summary['late_rolled_count'],
            'sub' => 'to next week\'s run',
        ])
        @include('pages.payroll.partials.summary-stat', [
            'label' => 'Held / review',
            'value' => (string) $summary['held_count'],
            'sub' => 'eligibility / re-check',
            'valueClass' => 'text-[#ef4444]',
        ])
    </div>

    {{-- Banners --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="flex items-start justify-between gap-3 p-4 rounded-2xl border border-[#bfdbfe] bg-[#eff6ff]">
            <div class="flex items-start gap-3 min-w-0">
                <div class="mt-0.5 w-9 h-9 flex items-center justify-center rounded-xl bg-[#dbeafe] text-[#2563eb] shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                </div>
                <div>
                    <p class="text-[13px] font-semibold text-[#1e40af]">Manual payroll — run each caregiver's pay in your payroll portal, then mark them Processed here</p>
                    <p class="text-[12px] text-[#1d4ed8] mt-0.5">
                        Hours come from compliance forms and EVV. After you process a caregiver in the payroll portal, click "Mark Processed" on their row, then attach their pay stub.
                    </p>
                </div>
            </div>
            <span class="text-[11px] font-semibold text-[#2563eb] whitespace-nowrap shrink-0">Manual step</span>
        </div>
        <div class="flex items-start justify-between gap-3 p-4 rounded-2xl border border-[#fde68a] bg-[#fffbeb] {{ $summary['in_grace_count'] === 0 ? 'opacity-60' : '' }}">
            <div class="flex items-start gap-3 min-w-0">
                <div class="mt-0.5 w-9 h-9 flex items-center justify-center rounded-xl bg-[#fef3c7] text-[#d97706] shrink-0">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/></svg>
                </div>
                <div>
                    <p class="text-[13px] font-semibold text-[#92400e]">Pay grace window holding {{ $summary['in_grace_count'] }} caregiver{{ $summary['in_grace_count'] === 1 ? '' : 's' }}</p>
                    <p class="text-[12px] text-[#b45309] mt-0.5">
                        ~{{ config('payroll.grace_days') }} days between compliance-form receipt and payout — anti-fraud hold that cannot be bypassed.
                    </p>
                </div>
            </div>
            @if($summary['in_grace_count'] > 0)
                <span class="text-[11px] font-bold text-[#92400e] bg-[#fef3c7] px-2 py-1 rounded-lg whitespace-nowrap shrink-0">{{ $summary['in_grace_count'] }} in grace</span>
            @endif
        </div>
    </div>

    {{-- Table --}}
    <div class="bg-white rounded-2xl border border-[#e6eef9] shadow-sm overflow-hidden">
        <div class="flex flex-wrap gap-1 p-4 border-b border-[#e6eef9] bg-[#f8fafc]">
            @php
                $tabs = [
                    ['key' => null, 'label' => 'All', 'count' => $tabCounts['all']],
                    ['key' => 'ready', 'label' => 'Ready for batch', 'count' => $tabCounts['ready']],
                    ['key' => 'in_grace', 'label' => 'In grace window', 'count' => $tabCounts['in_grace']],
                    ['key' => 'late_rolled', 'label' => 'Late — rolled', 'count' => $tabCounts['late_rolled']],
                    ['key' => 'held', 'label' => 'Held / review', 'count' => $tabCounts['held']],
                    ['key' => 'paid', 'label' => 'Paid', 'count' => $tabCounts['paid']],
                ];
            @endphp
            @foreach($tabs as $tab)
                <a href="{{ route('payroll', $queryParams(['status' => $tab['key'], 'page' => null])) }}"
                   class="px-3 py-1.5 text-[12px] font-semibold rounded-lg transition {{ ($filters['status'] ?? null) === $tab['key'] || (!$filters['status'] && !$tab['key']) ? 'bg-white text-[#2563eb] shadow-sm border border-[#dbe6ff]' : 'text-[#64748b] hover:text-[#334155]' }}">
                    {{ $tab['label'] }} {{ $tab['count'] }}
                </a>
            @endforeach
        </div>

        <form method="GET" action="{{ route('payroll') }}" class="p-4 flex flex-wrap items-center gap-3 border-b border-[#e6eef9]">
            @if($filters['status'])
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
            @endif
            <input type="hidden" name="period" value="{{ $filters['period'] }}">
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Filter by caregiver..."
                   class="flex-1 min-w-[200px] px-3.5 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
            @php
                $hasTypeFilters = ($filters['caregiver_type'] ?? null) || ($filters['live_in'] ?? null) || ($filters['in_grace'] ?? null);
            @endphp
            <a href="{{ route('payroll', $queryParams(['caregiver_type' => null, 'live_in' => null, 'in_grace' => null, 'page' => null])) }}"
               class="px-3 py-1.5 text-[12px] font-semibold rounded-full border transition {{ ! $hasTypeFilters ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0]' }}">
                All
            </a>
            @foreach([
                'family' => 'Family caregiver',
                'agency' => 'Agency-sourced',
            ] as $typeKey => $typeLabel)
                <a href="{{ route('payroll', $queryParams(['caregiver_type' => ($filters['caregiver_type'] ?? '') === $typeKey ? null : $typeKey, 'page' => null])) }}"
                   class="px-3 py-1.5 text-[12px] font-semibold rounded-full border transition {{ ($filters['caregiver_type'] ?? '') === $typeKey ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0]' }}">
                    {{ $typeLabel }}
                </a>
            @endforeach
            <a href="{{ route('payroll', $queryParams(['live_in' => $filters['live_in'] ? null : 1, 'page' => null])) }}"
               class="px-3 py-1.5 text-[12px] font-semibold rounded-full border transition {{ $filters['live_in'] ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0]' }}">
                Live-in
            </a>
            <a href="{{ route('payroll', $queryParams(['in_grace' => $filters['in_grace'] ? null : 1, 'page' => null])) }}"
               class="px-3 py-1.5 text-[12px] font-semibold rounded-full border transition {{ $filters['in_grace'] ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0]' }}">
                In grace
            </a>
            <div class="ml-auto flex items-center gap-2 text-[12px] text-[#64748b]">
                <span class="font-semibold">Sort:</span>
                <span class="px-3 py-1.5 font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl">Status</span>
            </div>
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-[#e6eef9] bg-[#f8fafc]">
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase">Caregiver</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase">Client</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase">Period</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase">Verified Hrs</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase">Wage</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase">Wage (Total)</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase">Status</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f5f9]">
                    @forelse($records as $record)
                        @php
                            $emp = $record->employee;
                            $initials = strtoupper(substr($emp?->first_name ?? '?', 0, 1).substr($emp?->last_name ?? '', 0, 1));
                            $graceDays = $record->status === \App\Models\PayRecord::STATUS_IN_GRACE && $record->complianceForm?->submitted_at
                                ? app(\App\Services\PayrollGraceWindowService::class)->daysRemaining($record->complianceForm->submitted_at)
                                : null;
                            // EVV weekly-consistency % (P3) — coaching signal only, never affects pay.
                            $evv = (! $record->isPaid() && $record->isRegular())
                                ? app(\App\Services\EvvComplianceRateService::class)->rateForRecord($record)
                                : null;
                            $evvChip = [
                                'good'  => 'bg-[#ecfdf3] text-[#067647] border-[#abefc6]',
                                'watch' => 'bg-[#fffaeb] text-[#b54708] border-[#fedf89]',
                                'low'   => 'bg-[#fef3f2] text-[#b42318] border-[#fecdca]',
                            ];
                        @endphp
                        <tr class="hover:bg-[#f8fafc] transition">
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-[#eff4ff] text-[#2563eb] text-[11px] font-bold shrink-0">{{ $initials }}</div>
                                    <div>
                                        <div class="text-[13px] font-semibold text-[#0f172a]">{{ $emp?->name }}</div>
                                        <div class="flex flex-wrap gap-1 mt-0.5">
                                            @if($record->isSupplemental())
                                                <span class="text-[9px] font-bold text-[#b54708] bg-[#fffaeb] border border-[#fedf89] rounded px-1 py-0.5 uppercase tracking-wide">＋ Supplemental</span>
                                            @elseif($record->isReversal())
                                                <span class="text-[9px] font-bold text-[#b42318] bg-[#fef3f2] border border-[#fecdca] rounded px-1 py-0.5 uppercase tracking-wide">↩ Reversal</span>
                                            @endif
                                            @if($record->caregiver_type === 'family')
                                                <span class="text-[10px] font-bold text-[#64748b]">Family</span>
                                            @endif
                                            @if($emp?->live_in)
                                                <span class="text-[10px] font-bold text-[#64748b]">Live-in</span>
                                            @endif
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <div class="text-[13px] text-[#0f172a]">{{ $record->client?->first_name }} {{ $record->client?->last_name }}</div>
                                @if($record->program_tag)
                                    <x-ui.pill :variant="$record->program_tag === 'MICH' ? 'blue' : 'gray'" size="xs">{{ $record->program_tag }}</x-ui.pill>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#475569]">{{ $record->period }}</td>
                            <td class="px-4 py-3.5">
                                <div class="text-[13px] font-medium text-[#0f172a]">{{ $record->hours !== null ? number_format($record->hours, 1) : '—' }}</div>
                                @if($record->hours_source)
                                    <div class="text-[11px] text-[#94a3b8]">{{ $record->hours_source }}</div>
                                @endif
                                @if($evv)
                                    <div class="mt-1 inline-flex items-center gap-1 px-1.5 py-0.5 rounded-md border text-[10px] font-bold {{ $evvChip[$evv['band']] }}"
                                         title="EVV weekly consistency for {{ $period->format('M Y') }}: {{ $evv['clocked'] }}h clocked vs {{ $evv['approved'] }}h approved, spread across {{ $evv['weeks'] }} weeks. Coaching signal only — it never affects pay.">
                                        <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                                        EVV {{ $evv['rate'] }}%
                                    </div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#475569]">${{ number_format((float)($record->rate ?? 0), 2) }}/hr</td>
                            <td class="px-4 py-3.5 text-[13px] font-semibold {{ $record->isReversal() ? 'text-[#b42318]' : 'text-[#0f172a]' }}">
                                @if($record->isReversal())
                                    &minus;${{ number_format((float)($record->recovery_amount ?? 0), 2) }}
                                @else
                                    {{ $record->gross !== null ? '$'.number_format($record->gross, 2) : '—' }}
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                @if($record->isReversal())
                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold {{ $record->recovery_status === \App\Models\PayRecord::RECOVERY_RECOVERED ? 'bg-[#ecfdf3] text-[#067647] border border-[#abefc6]' : 'bg-[#fef3f2] text-[#b42318] border border-[#fecdca]' }}">
                                        Reversal · {{ $record->recovery_status ?? 'Requested' }}
                                    </span>
                                @else
                                    @include('pages.payroll.partials.status-badge', ['status' => $record->status, 'daysRemaining' => $graceDays])
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                <div x-data="{ stubOpen: false, suppOpen: false, revOpen: false, adjustOpen: false }" class="flex items-center justify-end gap-2">
                                    @if($record->isReversal())
                                        @if($record->recovery_status !== \App\Models\PayRecord::RECOVERY_RECOVERED)
                                            <form method="POST" action="{{ route('payroll.reversal-recovered', $record) }}" class="inline-flex">
                                                @csrf
                                                <button type="submit" class="inline-flex items-center h-7 px-2.5 text-[11px] font-semibold text-white bg-[#16a34a] rounded-lg hover:bg-[#15803d] transition">Mark recovered</button>
                                            </form>
                                        @else
                                            <span class="text-[11px] font-semibold text-[#067647]">✓ Recovered</span>
                                        @endif
                                    @else
                                        @if($record->isPaid())
                                            @if($record->stub_path)
                                                <a href="{{ route('payroll.stub', $record) }}" class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#067647] hover:underline">Pay stub ↓</a>
                                            @endif
                                        @else
                                            @if($record->processed_payroll_at)
                                                <span class="inline-flex items-center gap-1 text-[11px] font-semibold text-[#067647]" title="Processed by {{ $record->processedByUser?->name }} on {{ $record->processed_payroll_at->format('M j, Y · g:i A') }}">
                                                    ✓ Processed
                                                </span>
                                                <button type="button" @click="stubOpen = true" class="inline-flex items-center h-7 px-2.5 text-[11px] font-semibold text-white bg-[#2563eb] rounded-lg hover:bg-[#1d4ed8] transition">
                                                    Add pay stub
                                                </button>
                                            @else
                                                <form method="POST" action="{{ route('payroll.mark-processed', $record) }}" class="inline-flex">
                                                    @csrf
                                                    <button type="submit" class="inline-flex items-center h-7 px-2.5 text-[11px] font-semibold text-[#2563eb] bg-white border border-[#dbe6ff] rounded-lg hover:bg-[#eff4ff] transition">
                                                        Mark Processed
                                                    </button>
                                                </form>
                                            @endif
                                        @endif
                                        @if($record->isRegular())
                                            <div class="relative">
                                                <button type="button" @click="adjustOpen = !adjustOpen" @click.outside="adjustOpen = false" class="inline-flex items-center h-7 px-2.5 text-[11px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-lg hover:bg-[#f8fafc] transition">Adjust ▾</button>
                                                <div x-show="adjustOpen" x-cloak class="absolute right-0 mt-1 w-52 bg-white border border-[#e6eef9] rounded-xl shadow-lg z-30 py-1 text-left" style="display:none;">
                                                    <button type="button" @click="suppOpen = true; adjustOpen = false" class="block w-full text-left px-3 py-2 text-[12px] font-medium text-[#0f172a] hover:bg-[#f8fafc]">＋ Supplemental payment</button>
                                                    <button type="button" @click="revOpen = true; adjustOpen = false" class="block w-full text-left px-3 py-2 text-[12px] font-medium text-[#b42318] hover:bg-[#fef3f2]">↩ Record reversal</button>
                                                </div>
                                            </div>
                                        @endif
                                    @endif
                                    <a href="{{ route('payroll.show', $record) }}" class="text-[12px] font-semibold text-[#2563eb] hover:underline whitespace-nowrap">
                                        {{ $record->status === \App\Models\PayRecord::STATUS_HELD ? 'Review' : 'View' }} ›
                                    </a>

                                    {{-- Pay stub capture modal --}}
                                    <div x-show="stubOpen" x-cloak @keydown.escape.window="stubOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                                        <div class="absolute inset-0 bg-black/40" @click="stubOpen = false"></div>
                                        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-5 text-left">
                                            <div class="flex items-center justify-between mb-1">
                                                <h3 class="text-[16px] font-bold text-[#0f172a]">Pay stub — {{ $emp?->name }}</h3>
                                                <button type="button" @click="stubOpen = false" class="text-[#94a3b8] hover:text-[#475569] text-[18px] leading-none">&times;</button>
                                            </div>
                                            <p class="text-[12px] text-[#64748b] mb-4">Attach the stub — AI reads these fields from it. Review and edit before saving.</p>
                                            <form method="POST" action="{{ route('payroll.pay-stub', $record) }}" enctype="multipart/form-data" class="space-y-3">
                                                @csrf
                                                <div>
                                                    <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Pay stub file</label>
                                                    <input type="file" name="stub" accept=".pdf,.png,.jpg,.jpeg" class="block w-full text-[12px] text-[#475569] file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[12px] file:font-semibold file:bg-[#eff4ff] file:text-[#2563eb]">
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Pay date</label>
                                                        <input type="date" name="pay_date" value="{{ now()->format('Y-m-d') }}" required class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Program</label>
                                                        <input type="text" name="program_tag" value="{{ $record->program_tag }}" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Gross ($)</label>
                                                        <input type="number" step="0.01" min="0" name="gross" value="{{ $record->gross }}" required class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Net ($)</label>
                                                        <input type="number" step="0.01" min="0" name="net" placeholder="0.00" required class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                                                    </div>
                                                    <div class="col-span-2">
                                                        <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Hours</label>
                                                        <input type="number" step="0.1" min="0" name="hours" value="{{ $record->hours }}" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                                                    </div>
                                                </div>
                                                <div class="flex justify-end gap-2 pt-1">
                                                    <button type="button" @click="stubOpen = false" class="px-3.5 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc]">Cancel</button>
                                                    <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8]">Save pay stub</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    {{-- P5 · Supplemental payment modal --}}
                                    <div x-show="suppOpen" x-cloak @keydown.escape.window="suppOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                                        <div class="absolute inset-0 bg-black/40" @click="suppOpen = false"></div>
                                        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-5 text-left">
                                            <div class="flex items-center justify-between mb-1">
                                                <h3 class="text-[16px] font-bold text-[#0f172a]">Supplemental payment — {{ $emp?->name }}</h3>
                                                <button type="button" @click="suppOpen = false" class="text-[#94a3b8] hover:text-[#475569] text-[18px] leading-none">&times;</button>
                                            </div>
                                            <p class="text-[12px] text-[#64748b] mb-4">An extra payment on the {{ $record->period }} period — a backdated/missed case or an underpayment. Adds a second record; the original run is unchanged.</p>
                                            <form method="POST" action="{{ route('payroll.supplemental', $record) }}" enctype="multipart/form-data" class="space-y-3">
                                                @csrf
                                                <div>
                                                    <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Reason</label>
                                                    <input type="text" name="reason" required placeholder="e.g. backdated case · underpaid April hours" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                                                </div>
                                                <div class="grid grid-cols-2 gap-3">
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Pay date</label>
                                                        <input type="date" name="pay_date" value="{{ now()->format('Y-m-d') }}" required class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Date(s) of service</label>
                                                        <input type="text" name="service_dates" placeholder="e.g. Apr 3–7" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Gross ($)</label>
                                                        <input type="number" step="0.01" min="0" name="gross" required class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                                                    </div>
                                                    <div>
                                                        <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Net ($)</label>
                                                        <input type="number" step="0.01" min="0" name="net" required class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                                                    </div>
                                                    <div class="col-span-2">
                                                        <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Hours (optional)</label>
                                                        <input type="number" step="0.1" min="0" name="hours" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                                                    </div>
                                                    <div class="col-span-2">
                                                        <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Pay stub (optional)</label>
                                                        <input type="file" name="stub" accept=".pdf,.png,.jpg,.jpeg" class="block w-full text-[12px] text-[#475569] file:mr-3 file:py-1.5 file:px-3 file:rounded-lg file:border-0 file:text-[12px] file:font-semibold file:bg-[#eff4ff] file:text-[#2563eb]">
                                                    </div>
                                                </div>
                                                <div class="flex justify-end gap-2 pt-1">
                                                    <button type="button" @click="suppOpen = false" class="px-3.5 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc]">Cancel</button>
                                                    <button type="submit" class="px-3.5 py-2 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8]">Record supplemental</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>

                                    {{-- P5 · Reversal / clawback modal --}}
                                    <div x-show="revOpen" x-cloak @keydown.escape.window="revOpen = false" class="fixed inset-0 z-50 flex items-center justify-center p-4" style="display:none;">
                                        <div class="absolute inset-0 bg-black/40" @click="revOpen = false"></div>
                                        <div class="relative bg-white rounded-2xl shadow-xl w-full max-w-md p-5 text-left">
                                            <div class="flex items-center justify-between mb-1">
                                                <h3 class="text-[16px] font-bold text-[#0f172a]">Reversal / clawback — {{ $emp?->name }}</h3>
                                                <button type="button" @click="revOpen = false" class="text-[#94a3b8] hover:text-[#475569] text-[18px] leading-none">&times;</button>
                                            </div>
                                            <p class="text-[12px] text-[#64748b] mb-4">Record an overpayment to recover for {{ $record->period }}. Tracked as <b>Requested</b> until the funds are clawed back, then mark it Recovered.</p>
                                            <form method="POST" action="{{ route('payroll.reversal', $record) }}" class="space-y-3">
                                                @csrf
                                                <div>
                                                    <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Amount to recover ($)</label>
                                                    <input type="number" step="0.01" min="0.01" name="recovery_amount" required class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#b42318]/20 focus:border-[#b42318] outline-none">
                                                </div>
                                                <div>
                                                    <label class="block text-[11px] font-semibold text-[#64748b] uppercase mb-1">Reason</label>
                                                    <input type="text" name="reason" required placeholder="e.g. overpaid 8 hrs in error" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#b42318]/20 focus:border-[#b42318] outline-none">
                                                </div>
                                                <div class="flex justify-end gap-2 pt-1">
                                                    <button type="button" @click="revOpen = false" class="px-3.5 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc]">Cancel</button>
                                                    <button type="submit" style="background-color:#b42318" class="px-3.5 py-2 text-[12px] font-semibold text-white rounded-xl hover:opacity-90">Request reversal</button>
                                                </div>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="8" class="px-4 py-12 text-center text-[13px] text-[#94a3b8]">No payroll records for this period.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($records->total() > 0)
            <div class="px-4 py-3 border-t border-[#e6eef9] flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p class="text-[12px] text-[#64748b]">
                    Showing {{ $records->firstItem() }}–{{ $records->lastItem() }} of {{ $records->total() }} caregivers · {{ $period->format('M Y') }}
                </p>
                @if($records->hasPages())
                    <div>{{ $records->links() }}</div>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection
