@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-[12px] font-semibold text-[#2563eb] mb-1">Billing & Claims</p>
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight">Aging report</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5">
                Outstanding receivables as of {{ $asOf->format('M j, Y') }} — submitted but not yet confirmed paid.
            </p>
        </div>
        <div class="flex flex-wrap gap-2">
            <a href="{{ route('billing-claims-audit.aging.export', ['period' => $asOf->format('Y-m'), 'program' => $program]) }}"
               class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc]">Export</a>
            @can('runActions', \App\Models\BillingClaimAudit::class)
            <form action="{{ route('billing-claims-audit.chase-overdue') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="period" value="{{ $asOf->format('Y-m') }}">
                <input type="hidden" name="program" value="{{ $program }}">
                <button type="submit" class="inline-flex items-center px-4 py-2.5 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8]">Chase overdue</button>
            </form>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="flex flex-col gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-3">
            <select name="period" onchange="this.form.submit()" class="px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl bg-white">
                @foreach($periodOptions as $opt)
                    <option value="{{ $opt['value'] }}" @selected(request('period', $asOf->format('Y-m')) === $opt['value'])>As of {{ $opt['label'] }}</option>
                @endforeach
            </select>
            <span class="text-[12px] font-semibold text-[#64748b]">Aging snapshot view</span>
            <div class="flex gap-1">
                @foreach(['all' => 'All programs', 'MICH' => 'MICH only', 'DHS' => 'DHS only'] as $val => $label)
                    <a href="{{ route('billing-claims-audit.aging', ['period' => request('period', $asOf->format('Y-m')), 'program' => $val]) }}"
                       class="px-3.5 py-1.5 text-[12px] font-semibold rounded-full border transition {{ $program === $val ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0]' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <span class="text-[12px] text-[#94a3b8] ml-auto">Buckets recompute for the selected date &amp; program.</span>
        </form>
    </div>

    @php
        $bucketLabels = [
            'current' => ['label' => 'Current (0-30d)', 'valueClass' => 'text-[#10b981]'],
            '31_60' => ['label' => '31-60 days', 'valueClass' => 'text-[#f59e0b]'],
            '61_90' => ['label' => '61-90 days', 'valueClass' => 'text-[#ef4444]'],
            '90_plus' => ['label' => '90+ days', 'valueClass' => 'text-[#991b1b]'],
        ];
        $bucketSubs = [
            'current' => 'on track',
            '31_60' => 'follow up',
            '61_90' => 'escalate',
            '90_plus' => 'at risk',
        ];
    @endphp
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
        @foreach($bucketLabels as $key => $meta)
            @include('pages.billing-claims-audit.partials.summary-stat', [
                'label' => $meta['label'],
                'value' => '$'.number_format($aging['buckets'][$key]['amount'], 0),
                'sub' => $aging['buckets'][$key]['count'].' bills - '.$bucketSubs[$key],
                'valueClass' => $meta['valueClass'],
            ])
        @endforeach
        @include('pages.billing-claims-audit.partials.summary-stat', [
            'label' => 'Total outstanding',
            'value' => '$'.number_format($aging['total_outstanding'], 0),
            'sub' => $aging['total_count'].' bills awaiting',
        ])
    </div>

    <div class="bg-white rounded-2xl border border-[#e6eef9] shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-[#e6eef9]">
            <h2 class="text-[15px] font-bold text-[#0f172a]">Aging by program & channel</h2>
            <p class="text-[12px] text-[#64748b] mt-0.5">Where the receivables sit — MICH (Availity/EOB) vs DHS (Sigma)</p>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <thead class="bg-[#f8fafc] border-b border-[#e6eef9]">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-[#64748b]">Program - Channel</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b] text-right">Current 0-30d</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b] text-right">31-60d</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b] text-right">61-90d</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b] text-right">90+d</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b] text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f5f9]">
                    @forelse($aging['by_channel'] as $row)
                        <tr>
                            <td class="px-4 py-3">
                                <x-ui.pill :variant="$row['program_type'] === 'MICH' ? 'blue' : 'gray'" class="mr-2">{{ $row['program_type'] }}</x-ui.pill>
                                {{ $row['channel'] }}
                            </td>
                            <td class="px-4 py-3 text-right">${{ number_format($row['current'], 0) }}</td>
                            <td class="px-4 py-3 text-right">${{ number_format($row['31_60'], 0) }}</td>
                            <td class="px-4 py-3 text-right">${{ number_format($row['61_90'], 0) }}</td>
                            <td class="px-4 py-3 text-right">${{ number_format($row['90_plus'], 0) }}</td>
                            <td class="px-4 py-3 text-right font-semibold">${{ number_format($row['total'], 0) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-4 py-12 text-center text-[#94a3b8]">No outstanding receivables for this snapshot.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    <div class="bg-white rounded-2xl border border-[#e6eef9] shadow-sm overflow-hidden">
        <div class="px-5 py-4 border-b border-[#e6eef9] flex items-center justify-between">
            <div>
                <h2 class="text-[15px] font-bold text-[#0f172a]">Overdue bills (31+ days)</h2>
                <p class="text-[12px] text-[#64748b] mt-0.5">Oldest first — AI follow-up routes to your Workflow Queue</p>
            </div>
            @if($aging['overdue_total'] > 0)
                <x-ui.pill variant="amber">{{ $aging['overdue_total'] }} overdue</x-ui.pill>
            @endif
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left text-[13px]">
                <thead class="bg-[#f8fafc] border-b border-[#e6eef9]">
                    <tr>
                        <th class="px-4 py-3 font-semibold text-[#64748b]">Client</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b]">Program</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b]">Period</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b]">Amount</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b]">Channel</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b]">Age</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b]">Status</th>
                        <th class="px-4 py-3 font-semibold text-[#64748b] text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f5f9]">
                    @forelse($overdueClaims as $claim)
                        @php $age = $claim->ageInDays($asOf); @endphp
                        <tr>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    @php $initials = strtoupper(substr($claim->client?->first_name ?? '?', 0, 1).substr($claim->client?->last_name ?? '', 0, 1)); @endphp
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-[#eff4ff] text-[#2563eb] text-[11px] font-bold">{{ $initials }}</div>
                                    <span class="font-semibold">{{ $claim->client?->first_name }} {{ $claim->client?->last_name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3"><x-ui.pill :variant="$claim->program_type === 'MICH' ? 'blue' : 'gray'">{{ $claim->program_type }}</x-ui.pill></td>
                            <td class="px-4 py-3">{{ $claim->billing_period->format('M Y') }}</td>
                            <td class="px-4 py-3 font-semibold">${{ number_format($claim->total_amount, 2) }}</td>
                            <td class="px-4 py-3">{{ $claim->submission_channel }}</td>
                            <td class="px-4 py-3">
                                <x-ui.pill :variant="$age >= 60 ? 'red' : 'amber'" size="xs">{{ $age }} days</x-ui.pill>
                            </td>
                            <td class="px-4 py-3 text-[#64748b]">{{ $claim->status_detail }}</td>
                            <td class="px-4 py-3 text-right">
                                @php $action = $claim->overdueActionLabel($asOf); @endphp
                                @if($action === 'Escalate to AI')
                                    @can('runActions', \App\Models\BillingClaimAudit::class)
                                    <form action="{{ route('billing-claims-audit.escalate', $claim) }}" method="POST" class="inline">
                                        @csrf
                                        <button type="submit" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Escalate to AI &gt;</button>
                                    </form>
                                    @else
                                    <a href="{{ route('billing-claims-audit.show', $claim) }}" class="text-[12px] font-semibold text-[#2563eb] hover:underline">View claim &gt;</a>
                                    @endcan
                                @else
                                    <a href="{{ route('billing-claims-audit.show', $claim) }}" class="text-[12px] font-semibold text-[#2563eb] hover:underline">{{ $action }} &gt;</a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8" class="px-4 py-12 text-center text-[#94a3b8]">No overdue bills for this snapshot.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        @if($overdueClaims->total() > 0)
        <div class="px-4 py-3 border-t border-[#e6eef9] flex flex-wrap items-center justify-between gap-3 text-[12px] text-[#64748b]">
            <span>Showing {{ $overdueClaims->firstItem() }}-{{ $overdueClaims->lastItem() }} of {{ $overdueClaims->total() }} overdue bills</span>
            {{ $overdueClaims->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
