@extends('layouts.app')

@section('content')
@php
    $queryParams = fn (array $extra = []) => array_merge(
        request()->except('page'),
        array_filter([
            'period' => $filters['period'],
            'status' => $filters['status'] ?? null,
            'billing_status' => $filters['billing_status'] ?? null,
            'audit_status' => $filters['audit_status'] ?? null,
            'authorization_status' => $filters['authorization_status'] ?? null,
            'coverage_type' => $filters['coverage_type'] ?? null,
            'payment_status' => $filters['payment_status'] ?? null,
            'issue_type' => $filters['issue_type'] ?? null,
            'search' => $filters['search'] ?? null,
            'program' => $filters['program'] ?? null,
            'sort' => $filters['sort'] ?? null,
        ]),
        $extra
    );
@endphp
<div class="space-y-6">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-[12px] font-semibold text-[#2563eb] mb-1">Billing & Claims</p>
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Billing & Claims</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5">
                {{ $period->format('M Y') }} cycle
                — {{ $tabCounts['submitted'] ?? $summary['submitted_count'] }} submitted
                — {{ $tabCounts['on_hold'] ?? $summary['on_hold_count'] }} on hold
                — {{ $summary['ytd_billed_label'] }} billed YTD
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('billing-claims-audit.export', request()->query()) }}"
               class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition">
                Export
            </a>
            <a href="{{ route('billing-claims-audit.aging', ['period' => $period->format('Y-m')]) }}"
               class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition">
                Aging report
            </a>
            @can('runActions', \App\Models\BillingClaimAudit::class)
            <form action="{{ route('billing-claims-audit.refresh-availity-status') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
                <button type="submit" class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#2563eb] bg-[#eff6ff] border border-[#bfdbfe] rounded-xl hover:bg-[#dbeafe] transition">
                    Refresh Availity status
                </button>
            </form>
            <form action="{{ route('billing-claims-audit.generate-submit') }}" method="POST" class="inline">
                @csrf
                <input type="hidden" name="period" value="{{ $period->format('Y-m') }}">
                <button type="submit" class="inline-flex items-center px-4 py-2.5 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8] transition shadow-sm">
                    Generate & submit now
                </button>
            </form>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif
    @if(session('warning'))
        <x-ui.alert variant="warning">{{ session('warning') }}</x-ui.alert>
    @endif
    @if(!empty(session('submission_errors')))
        <x-ui.alert variant="warning">
            <p class="font-semibold mb-1">Some submissions failed:</p>
            <ul class="list-disc pl-4 space-y-0.5 text-[12px]">
                @foreach(session('submission_errors') as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    {{-- Period selector --}}
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('billing-claims-audit.index', $queryParams(['period' => $prevPeriod->format('Y-m')])) }}"
               class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-[#e2e8f0] bg-white text-[#64748b] hover:border-[#cbd5e1]">
                <span aria-hidden="true">&larr;</span>
            </a>
            <span class="px-4 py-2 text-[13px] font-semibold text-[#0f172a] bg-white border border-[#e2e8f0] rounded-xl min-w-[120px] text-center">
                {{ $period->format('M Y') }}
            </span>
            <a href="{{ route('billing-claims-audit.index', $queryParams(['period' => $nextPeriod->format('Y-m')])) }}"
               class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-[#e2e8f0] bg-white text-[#64748b] hover:border-[#cbd5e1]">
                <span aria-hidden="true">&rarr;</span>
            </a>
            @foreach($periodOptions as $opt)
                <a href="{{ route('billing-claims-audit.index', $queryParams(['period' => $opt['value']])) }}"
                   class="px-3.5 py-1.5 text-[12px] font-semibold rounded-full border transition {{ $filters['period'] === $opt['value'] ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0] hover:border-[#cbd5e1]' }}">
                    {{ $opt['label'] }}
                </a>
            @endforeach
            <span class="px-3.5 py-1.5 text-[12px] font-semibold rounded-full bg-white text-[#475569] border border-[#e2e8f0]">Q{{ ceil($period->month / 3) }} YTD</span>
            <span class="px-3.5 py-1.5 text-[12px] font-semibold rounded-full bg-white text-[#475569] border border-[#e2e8f0]">{{ $period->format('Y') }} YTD</span>
        </div>
        <p class="text-[12px] text-[#94a3b8]">Any month is viewable — claims &amp; payment status reload for the selected period.</p>
    </div>

    {{-- Summary cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
        @include('pages.billing-claims-audit.partials.summary-stat', [
            'label' => 'Billed this cycle ('.$period->format('M').')',
            'value' => '$'.number_format($summary['billed_amount'], 0),
            'sub' => $summary['billed_count'].' claims/invoices',
        ])
        @include('pages.billing-claims-audit.partials.summary-stat', [
            'label' => 'Paid / confirmed',
            'value' => '$'.number_format($summary['paid_amount'], 0),
            'sub' => 'EOB + Sigma posted',
            'valueClass' => 'text-[#10b981]',
        ])
        @include('pages.billing-claims-audit.partials.summary-stat', [
            'label' => 'Awaiting payment',
            'value' => '$'.number_format($summary['awaiting_amount'], 0),
            'sub' => 'submitted, in flight',
            'valueClass' => 'text-[#f59e0b]',
        ])
        @include('pages.billing-claims-audit.partials.summary-stat', [
            'label' => 'On hold (CP-01)',
            'value' => (string) $summary['on_hold_count'],
            'sub' => 'prior balance / closure',
            'valueClass' => 'text-[#ef4444]',
        ])
        @include('pages.billing-claims-audit.partials.summary-stat', [
            'label' => 'Rejected / rework',
            'value' => (string) $summary['rejected_count'],
            'sub' => 're-submit needed',
            'valueClass' => 'text-[#ef4444]',
        ])
    </div>

    @php $wf = $summary['workflow'] ?? []; @endphp
    @if(!empty($wf))
    <div class="flex flex-wrap gap-3 p-4 rounded-2xl border border-[#e6eef9] bg-white text-[12px]">
        <span class="font-semibold text-[#64748b] w-full sm:w-auto">Audit workflow:</span>
        @foreach([
            ['label' => 'Ready to bill', 'count' => $wf['ready_to_bill'] ?? 0, 'filter' => ['billing_status' => 'ready_to_bill']],
            ['label' => 'Blocked', 'count' => $wf['blocked'] ?? 0, 'filter' => ['billing_status' => 'blocked']],
            ['label' => 'Sent / submitted', 'count' => $wf['sent_submitted'] ?? 0, 'filter' => ['billing_status' => 'submitted']],
            ['label' => 'Missing EOB', 'count' => $wf['missing_eob'] ?? 0, 'filter' => ['payment_status' => 'missing_eob']],
            ['label' => 'Expiring auth', 'count' => $wf['expiring_auth'] ?? 0, 'filter' => ['authorization_status' => 'expiring_soon']],
            ['label' => 'Outstanding', 'count' => '$'.number_format($wf['outstanding_balance'] ?? 0, 0), 'filter' => []],
        ] as $chip)
            @if(is_numeric($chip['count']) ? $chip['count'] > 0 : true)
            <a href="{{ !empty($chip['filter']) ? route('billing-claims-audit.index', $queryParams($chip['filter'])) : '#' }}"
               class="px-3 py-1.5 rounded-full border border-[#e2e8f0] bg-[#f8fafc] text-[#475569] hover:border-[#2563eb] hover:text-[#2563eb] transition">
                {{ $chip['label'] }} <strong class="text-[#0f172a]">{{ $chip['count'] }}</strong>
            </a>
            @endif
        @endforeach
    </div>
    @endif

    {{-- Banners --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <div class="flex items-start justify-between gap-3 p-4 rounded-2xl border border-[#bbf7d0] bg-[#ecfdf3]">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 w-2 h-2 rounded-full bg-[#10b981] shrink-0"></span>
                <div>
                    <p class="text-[13px] font-semibold text-[#065f46]">Auto-billing is on</p>
                    <p class="text-[12px] text-[#047857] mt-0.5">
                        @if($summary['eligible_count'] === 0)
                            No clients with clean visits and valid authorization found for this cycle yet.
                        @else
                            {{ $summary['auto_generated_count'] }} of {{ $summary['eligible_count'] }} eligible bills generated &amp; submitted automatically this cycle.
                        @endif
                        @if($summary['on_hold_count'] > 0)
                            {{ $summary['on_hold_count'] }} held by the gate below.
                        @endif
                    </p>
                </div>
            </div>
            @if($summary['auto_billing_on'])
                <x-ui.pill variant="green" size="xs">Auto · running</x-ui.pill>
            @endif
        </div>
        <div class="flex items-start justify-between gap-3 p-4 rounded-2xl border border-[#fde68a] bg-[#fffbeb] {{ $summary['on_hold_count'] === 0 ? 'opacity-60' : '' }}">
            <div class="flex items-start gap-3">
                <span class="mt-0.5 w-2 h-2 rounded-full bg-[#f59e0b] shrink-0"></span>
                <div>
                    <p class="text-[13px] font-semibold text-[#92400e]">Pre-Billing Verification Gate (CP-01)</p>
                    <p class="text-[12px] text-[#b45309] mt-0.5">
                        @if($summary['on_hold_count'] > 0)
                            held {{ $summary['on_hold_count'] }} bills — last month's payment must clear first.
                        @else
                            No bills currently held — prior month payments cleared.
                        @endif
                    </p>
                </div>
            </div>
            @if($summary['on_hold_count'] > 0)
                <a href="{{ route('billing-claims-audit.index', ['period' => $period->format('Y-m'), 'status' => 'on_hold']) }}"
                   class="text-[12px] font-semibold text-[#2563eb] whitespace-nowrap hover:underline">{{ $summary['on_hold_count'] }} to review</a>
            @endif
        </div>
    </div>

    {{-- Table section --}}
    <div class="bg-white rounded-2xl border border-[#e6eef9] shadow-sm overflow-hidden">
        {{-- Tabs --}}
        <div class="flex flex-wrap gap-1 p-4 border-b border-[#e6eef9] bg-[#f8fafc]">
            @php
                $tabs = [
                    ['key' => null, 'label' => 'All', 'count' => $tabCounts['all']],
                    ['key' => 'submitted', 'label' => 'Submitted', 'count' => $tabCounts['submitted']],
                    ['key' => 'on_hold', 'label' => 'On hold (CP-01)', 'count' => $tabCounts['on_hold']],
                    ['key' => 'awaiting_payment', 'label' => 'Awaiting payment', 'count' => $tabCounts['awaiting_payment']],
                    ['key' => 'paid', 'label' => 'Paid / confirmed', 'count' => $tabCounts['paid']],
                    ['key' => 'rejected', 'label' => 'Rejected', 'count' => $tabCounts['rejected']],
                ];
            @endphp
            @foreach($tabs as $tab)
                <a href="{{ route('billing-claims-audit.index', array_merge(request()->except(['page', 'status']), $tab['key'] ? ['status' => $tab['key']] : [], ['period' => $filters['period']])) }}"
                   class="px-3 py-1.5 text-[12px] font-semibold rounded-lg transition {{ ($filters['status'] ?? null) === $tab['key'] || (!$filters['status'] && !$tab['key']) ? 'bg-white text-[#2563eb] shadow-sm border border-[#dbe6ff]' : 'text-[#64748b] hover:text-[#334155]' }}">
                    {{ $tab['label'] }} {{ $tab['count'] }}
                </a>
            @endforeach
        </div>

        {{-- Filters --}}
        <form method="GET" action="{{ route('billing-claims-audit.index') }}" class="p-4 flex flex-wrap items-center gap-3 border-b border-[#e6eef9]">
            @if($filters['status'])
                <input type="hidden" name="status" value="{{ $filters['status'] }}">
            @endif
            <input type="search" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Client, member ID, claim #, payer, caregiver..."
                   class="flex-1 min-w-[200px] px-3.5 py-2 text-[13px] border border-[#e2e8f0] rounded-xl focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
            <select name="billing_status" onchange="this.form.submit()" class="px-3 py-2 text-[12px] border border-[#e2e8f0] rounded-xl bg-white">
                <option value="">Billing status</option>
                @foreach(\App\Models\BillingClaimAudit::billingStatuses() as $bs)
                    <option value="{{ $bs }}" @selected(($filters['billing_status'] ?? '') === $bs)>{{ ucwords(str_replace('_', ' ', $bs)) }}</option>
                @endforeach
            </select>
            <select name="authorization_status" onchange="this.form.submit()" class="px-3 py-2 text-[12px] border border-[#e2e8f0] rounded-xl bg-white">
                <option value="">Auth status</option>
                @foreach(\App\Models\BillingClaimAudit::authorizationStatuses() as $as)
                    <option value="{{ $as }}" @selected(($filters['authorization_status'] ?? '') === $as)>{{ ucwords(str_replace('_', ' ', $as)) }}</option>
                @endforeach
            </select>
            <select name="audit_status" onchange="this.form.submit()" class="px-3 py-2 text-[12px] border border-[#e2e8f0] rounded-xl bg-white">
                <option value="">Audit status</option>
                @foreach(\App\Models\BillingClaimAudit::auditStatuses() as $aus)
                    <option value="{{ $aus }}" @selected(($filters['audit_status'] ?? '') === $aus)>{{ ucwords(str_replace('_', ' ', $aus)) }}</option>
                @endforeach
            </select>
            <div class="flex gap-1">
                @foreach(['' => 'All programs', 'MICH' => 'MICH', 'DHS' => 'DHS'] as $val => $label)
                    <a href="{{ route('billing-claims-audit.index', $queryParams(['program' => $val ?: null, 'page' => null])) }}"
                       class="px-3 py-1.5 text-[12px] font-semibold rounded-full border transition {{ ($filters['program'] ?? '') === $val ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0]' }}">
                        {{ $label }}
                    </a>
                @endforeach
            </div>
            <select name="period" onchange="this.form.submit()"
                    class="px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl bg-white">
                @foreach($periodOptions as $opt)
                    <option value="{{ $opt['value'] }}" @selected($filters['period'] === $opt['value'])>{{ $opt['label'] }}</option>
                @endforeach
            </select>
            <select name="sort" onchange="this.form.submit()"
                    class="px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-xl bg-white ml-auto">
                <option value="status" @selected(($filters['sort'] ?? 'status') === 'status')>Sort: Status</option>
                <option value="client" @selected(($filters['sort'] ?? '') === 'client')>Sort: Client</option>
                <option value="amount" @selected(($filters['sort'] ?? '') === 'amount')>Sort: Amount</option>
            </select>
            @if($filters['search'] || $filters['program'])
                <a href="{{ route('billing-claims-audit.index', array_filter(['period' => $filters['period'], 'status' => $filters['status']])) }}"
                   class="text-[12px] font-semibold text-[#64748b] hover:text-[#2563eb]">Reset filters</a>
            @endif
        </form>

        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-[#e6eef9] bg-[#f8fafc]">
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase tracking-wide">Client</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase tracking-wide">Program</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase tracking-wide">Period</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase tracking-wide">Hours / Days</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase tracking-wide">Rate</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase tracking-wide">Amount</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase tracking-wide">Channel</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase tracking-wide">Status</th>
                        <th class="px-4 py-3 text-[11px] font-semibold text-[#64748b] uppercase tracking-wide text-right">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f5f9]">
                    @forelse($claims as $claim)
                        <tr class="hover:bg-[#f8fafc] transition">
                            <td class="px-4 py-3.5">
                                <div class="flex items-center gap-2.5">
                                    @php $initials = strtoupper(substr($claim->client?->first_name ?? '?', 0, 1).substr($claim->client?->last_name ?? '', 0, 1)); @endphp
                                    <div class="w-8 h-8 flex items-center justify-center rounded-full bg-[#eff4ff] text-[#2563eb] text-[11px] font-bold shrink-0">{{ $initials }}</div>
                                    <span class="text-[13px] font-semibold text-[#0f172a]">{{ $claim->client?->first_name }} {{ $claim->client?->last_name }}</span>
                                </div>
                            </td>
                            <td class="px-4 py-3.5">
                                <x-ui.pill :variant="$claim->program_type === 'MICH' ? 'blue' : 'gray'">{{ $claim->program_type }}</x-ui.pill>
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#475569]">{{ $claim->billing_period->format('M Y') }}</td>
                            <td class="px-4 py-3.5">
                                <div class="text-[13px] font-medium text-[#0f172a]">{{ number_format($claim->total_hours, 0) }} hrs</div>
                                @if($claim->service_code)
                                    <div class="text-[11px] text-[#94a3b8]">{{ $claim->service_code }} - {{ $claim->units }} units</div>
                                @elseif($claim->days_met_status)
                                    <div class="text-[11px] text-[#94a3b8]">{{ $claim->total_days }} days · {{ $claim->days_met_status }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-[13px] text-[#475569]">${{ number_format($claim->hourly_rate, 2) }}/hr</td>
                            <td class="px-4 py-3.5 text-[13px] font-semibold text-[#0f172a]">${{ number_format($claim->total_amount, 2) }}</td>
                            <td class="px-4 py-3.5">
                                @php $channelUrl = $claim->submissionChannelUrl(); @endphp
                                @if($channelUrl)
                                    <a href="{{ $claim->usesSigmaPortal() ? route('billing-claims-audit.sigma-portal', $claim) : $channelUrl }}"
                                       @if(!$claim->usesSigmaPortal()) target="_blank" rel="noopener" @endif
                                       class="text-[13px] text-[#2563eb] font-medium hover:underline">
                                        {{ $claim->submissionChannelLabel() }}
                                    </a>
                                @else
                                    <div class="text-[13px] text-[#475569]">{{ $claim->submissionChannelLabel() }}</div>
                                @endif
                                @if($claim->channelDisplaySubtext())
                                    <div class="text-[11px] text-[#94a3b8]">{{ $claim->channelDisplaySubtext() }}</div>
                                @endif
                                @if($claim->usesAvaility() && $claim->availity_reference_id)
                                    <div class="text-[10px] text-[#2563eb] mt-0.5">Ref: {{ $claim->availity_reference_id }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5">
                                <x-ui.pill :variant="$claim->statusBadgeVariant()" size="xs">{{ $claim->statusLabel() }}</x-ui.pill>
                                @if($claim->usesAvaility() && $claim->availity_status)
                                    <div class="text-[10px] text-[#2563eb] mt-1">Availity: {{ $claim->availityStatusLabel() }}</div>
                                @endif
                                @if($claim->authorization_status)
                                    <div class="text-[10px] text-[#94a3b8] mt-1">Auth: {{ ucwords(str_replace('_', ' ', $claim->authorization_status)) }}</div>
                                @endif
                                @if($claim->hasIssueFlags())
                                    <div class="text-[10px] text-[#f59e0b] mt-0.5">{{ implode(', ', array_slice($claim->issueFlagLabels(), 0, 2)) }}</div>
                                @endif
                            </td>
                            <td class="px-4 py-3.5 text-right">
                                @if($claim->hasDownloadablePdf())
                                    <a href="{{ route('billing-claims-audit.pdf.download', $claim) }}" class="text-[12px] font-semibold text-[#2563eb] hover:underline">
                                        View PDF &gt;
                                    </a>
                                @else
                                    <a href="{{ route('billing-claims-audit.show', $claim) }}" class="text-[12px] font-semibold text-[#2563eb] hover:underline">
                                        View claim &gt;
                                    </a>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9" class="px-4 py-16 text-center">
                                <p class="text-[15px] font-semibold text-[#64748b]">No billing claims found</p>
                                <p class="text-[13px] text-[#94a3b8] mt-1">Try adjusting your filters or selecting a different billing period.</p>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($claims->total() > 0)
        <div class="px-4 py-3 border-t border-[#e6eef9] flex flex-wrap items-center justify-between gap-3 text-[12px] text-[#64748b]">
            <span>Showing {{ $claims->firstItem() }}-{{ $claims->lastItem() }} of {{ $claims->total() }} bills — {{ $period->format('M Y') }}</span>
            {{ $claims->links() }}
        </div>
        @endif
    </div>
</div>
@endsection
