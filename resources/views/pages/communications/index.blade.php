@extends('layouts.app')

@section('content')
@php
    $queryParams = fn (array $extra = []) => array_merge(
        request()->except('page'),
        array_filter([
            'period' => $filters['period'] ?? $period->format('Y-m'),
            'tab' => $filters['tab'] ?? null,
            'party' => $filters['party'] ?? null,
            'search' => $filters['search'] ?? null,
            'sort' => $filters['sort'] ?? null,
        ]),
        $extra
    );

    $activePeriod = $filters['period'] ?? $period->format('Y-m');
    $activeTab = $filters['tab'] ?? 'all';
    $activeParty = $filters['party'] ?? 'all';

    $tabs = [
        'all' => 'All',
        'need_reply' => 'Need reply',
        'call' => 'Calls',
        'sms' => 'SMS',
        'fax' => 'eFax',
        'email' => 'Email',
        'wellness' => 'Wellness calls',
    ];

    $parties = [
        'all' => 'All parties',
        'client' => 'Clients',
        'caregiver' => 'Caregivers',
        'case_coordinator' => 'Case Coordinator',
        'mco_portal' => 'MCO / Portal',
        'needs_review' => 'Needs review',
    ];
@endphp

@php
    $composeJsConfig = [
        'integration' => $integrationStatus,
        'templates' => $composeTemplates->map(fn ($t) => [
            'id' => $t->id,
            'name' => $t->name,
            'channel' => $t->channel,
            'subject' => $t->subject,
            'body' => $t->body,
        ])->values(),
        'searchUrl' => route('communications.directory-search'),
        'clientDocumentsUrl' => url('/communications/clients/__CLIENT__/documents'),
    ];
    $exportQuery = http_build_query(array_filter([
        'period' => $filters['period'] ?? $period->format('Y-m'),
        'tab' => $filters['tab'] ?? null,
        'party' => $filters['party'] ?? null,
        'search' => $filters['search'] ?? null,
    ]));
@endphp

<div class="space-y-6" x-data="communicationsCompose(@js($composeJsConfig))" x-init="init()">
    {{-- Header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div>
            <p class="text-[12px] font-semibold text-[#2563eb] mb-1">Engagement</p>
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Communications</h1>
            <p class="text-[13px] text-[#64748b] mt-1.5">
                {{ $period->format('M Y') }}
                · {{ number_format($summary['total']) }} communications
                · {{ $summary['ai_percent'] }}% AI-handled
                · <span class="text-[#ea580c] font-semibold">{{ $summary['need_reply'] }} need your reply</span>
            </p>
        </div>
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('communications.export') }}{{ $exportQuery ? '?'.$exportQuery : '' }}"
               class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition">
                Export
            </a>
            @can('send', \App\Models\Communication::class)
                <button type="button" @click="showEfax = true"
                        class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition">
                    New eFax
                </button>
                <button type="button" @click="showMessage = true"
                        class="inline-flex items-center px-4 py-2.5 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8] transition shadow-sm">
                    New message
                </button>
            @endcan
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    {{-- Period selector --}}
    <div class="flex flex-col gap-2">
        <div class="flex flex-wrap items-center gap-2">
            <a href="{{ route('communications.index', $queryParams(['period' => $prevPeriod->format('Y-m')])) }}"
               class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-[#e2e8f0] bg-white text-[#64748b] hover:border-[#cbd5e1]">
                <span aria-hidden="true">&larr;</span>
            </a>
            <span class="inline-flex items-center gap-2 px-4 py-2 text-[13px] font-semibold text-[#0f172a] bg-white border border-[#e2e8f0] rounded-xl min-w-[130px] justify-center">
                <svg class="w-4 h-4 text-[#64748b]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/></svg>
                {{ $period->format('M Y') }}
            </span>
            <a href="{{ route('communications.index', $queryParams(['period' => $nextPeriod->format('Y-m')])) }}"
               class="inline-flex items-center justify-center w-9 h-9 rounded-xl border border-[#e2e8f0] bg-white text-[#64748b] hover:border-[#cbd5e1]">
                <span aria-hidden="true">&rarr;</span>
            </a>
            @foreach($periodOptions as $opt)
                <a href="{{ route('communications.index', $queryParams(['period' => $opt['value']])) }}"
                   class="px-3.5 py-1.5 text-[12px] font-semibold rounded-full border transition {{ $activePeriod === $opt['value'] ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0] hover:border-[#cbd5e1]' }}">
                    {{ $opt['label'] }}
                </a>
            @endforeach
            <span class="ml-auto text-[12px] text-[#94a3b8] hidden lg:inline">Log reloads for the selected period &amp; channel</span>
        </div>
    </div>

    {{-- KPI cards --}}
    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4">
        @include('pages.communications.partials.summary-stat', [
            'label' => 'Communications ('.$period->format('M').')',
            'value' => number_format($summary['total']),
            'sub' => 'all channels',
        ])
        @include('pages.communications.partials.summary-stat', [
            'label' => 'AI / VA handled',
            'value' => $summary['ai_percent'].'%',
            'sub' => number_format($summary['ai_handled']).' auto-resolved',
            'valueClass' => 'text-[#10b981]',
        ])
        @include('pages.communications.partials.summary-stat', [
            'label' => 'Need your reply',
            'value' => (string) $summary['need_reply'],
            'sub' => 'routed to queue',
            'valueClass' => 'text-[#ea580c]',
        ])
        @include('pages.communications.partials.summary-stat', [
            'label' => 'eFaxes (in / out)',
            'value' => (string) $summary['efax'],
            'sub' => 'Case Coordinator · MCO · portals',
        ])
        @include('pages.communications.partials.summary-stat', [
            'label' => 'Wellness calls',
            'value' => $summary['wellness_completed'].'/'.$summary['wellness_total'],
            'sub' => $period->format('M').' cycle · '.$summary['wellness_pending'].' pending',
        ])
    </div>

    {{-- Campaign banner --}}
    @if($summary['wellness_total'] > 0)
        <div class="rounded-2xl border border-[#bfdbfe] bg-[#eff6ff] px-5 py-4 flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
            <div class="flex items-start gap-3">
                <span class="w-10 h-10 rounded-xl bg-white border border-[#bfdbfe] flex items-center justify-center text-[#2563eb] shrink-0">
                    <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2M20 14h2M15 13v2M9 13v2"/></svg>
                </span>
                <div>
                    <p class="text-[14px] font-bold text-[#1d4ed8]">
                        Monthly wellness-call campaign — VA called {{ $summary['wellness_completed'] }} of {{ $summary['wellness_total'] }} caregivers
                        @if($summary['concerns'] > 0)
                            ; {{ $summary['concerns'] }} concern {{ Str::plural('note', $summary['concerns']) }} raised
                        @endif
                    </p>
                    <p class="text-[12px] text-[#3b82f6] mt-1 max-w-3xl">
                        Bilingual AI/VA calls confirm services and health status. Summaries are logged automatically; flagged concerns route to your review queue.
                    </p>
                </div>
            </div>
            <span class="inline-flex self-start lg:self-center items-center rounded-full bg-[#ecfdf5] text-[#047857] border border-[#a7f3d0] px-3 py-1 text-[11px] font-bold uppercase tracking-wide">
                Auto · {{ $summary['wellness_reached_percent'] }}% reached
            </span>
        </div>
    @endif

    {{-- Channel tabs --}}
    <div class="border-b border-[#e2e8f0]">
        <div class="flex flex-wrap gap-1 -mb-px">
            @foreach($tabs as $key => $label)
                <a href="{{ route('communications.index', $queryParams(['tab' => $key === 'all' ? null : $key])) }}"
                   class="px-4 py-2.5 text-[12px] font-semibold border-b-2 transition {{ $activeTab === $key ? 'border-[#2563eb] text-[#2563eb]' : 'border-transparent text-[#64748b] hover:text-[#0f172a]' }}">
                    {{ $label }}
                    <span class="ml-1 text-[#94a3b8]">{{ number_format($channelCounts[$key] ?? 0) }}</span>
                </a>
            @endforeach
        </div>
    </div>

    {{-- Filters row --}}
    <form method="GET" class="flex flex-col xl:flex-row xl:items-center gap-3">
        @if($activePeriod)
            <input type="hidden" name="period" value="{{ $activePeriod }}">
        @endif
        @if($activeTab !== 'all')
            <input type="hidden" name="tab" value="{{ $activeTab }}">
        @endif

        <div class="relative flex-1 max-w-md">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-[#94a3b8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/></svg>
            <input type="text" name="search" value="{{ $filters['search'] ?? '' }}" placeholder="Filter by name or number..."
                   class="w-full pl-10 pr-4 py-2.5 rounded-xl border border-[#e2e8f0] bg-white text-[13px] text-[#0f172a] placeholder-[#94a3b8] focus:border-[#2563eb] focus:ring-2 focus:ring-[#dbeafe] outline-none">
        </div>

        <div class="flex flex-wrap items-center gap-2 flex-1">
            @foreach($parties as $key => $label)
                <a href="{{ route('communications.index', $queryParams(['party' => $key === 'all' ? null : $key])) }}"
                   class="px-3.5 py-1.5 text-[12px] font-semibold rounded-full border transition {{ $activeParty === $key ? 'bg-[#2563eb] text-white border-[#2563eb]' : 'bg-white text-[#475569] border-[#e2e8f0] hover:border-[#cbd5e1]' }}">
                    {{ $label }}
                </a>
            @endforeach
        </div>

        <select name="sort" onchange="this.form.submit()" class="rounded-xl border border-[#e2e8f0] bg-white px-3 py-2 text-[12px] font-semibold text-[#475569]">
            <option value="newest" @selected(($filters['sort'] ?? 'newest') === 'newest')>Sort: Newest</option>
            <option value="oldest" @selected(($filters['sort'] ?? '') === 'oldest')>Sort: Oldest</option>
        </select>
    </form>

    {{-- Table --}}
    <div class="rounded-2xl border border-[#e2e8f0] bg-white overflow-hidden shadow-sm">
        <div class="overflow-x-auto">
            <table class="w-full text-left">
                <thead>
                    <tr class="bg-[#f8fafc] border-b border-[#e2e8f0]">
                        <th class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-wider text-[#64748b]">Party</th>
                        <th class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-wider text-[#64748b]">Channel</th>
                        <th class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-wider text-[#64748b]">Dir.</th>
                        <th class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-wider text-[#64748b] min-w-[240px]">AI summary</th>
                        <th class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-wider text-[#64748b]">Handled by</th>
                        <th class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-wider text-[#64748b]">When</th>
                        <th class="px-5 py-3.5 text-[10px] font-bold uppercase tracking-wider text-[#64748b] text-right">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f5f9]">
                    @forelse($communications as $index => $item)
                        @php $p = $presenters[$index]; @endphp
                        <tr class="hover:bg-[#f8fafc]/80 transition-colors">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="w-9 h-9 rounded-full bg-[#eff6ff] text-[#2563eb] text-[11px] font-bold flex items-center justify-center shrink-0">
                                        {{ $p->partyInitials() }}
                                    </span>
                                    <div class="min-w-0">
                                        <div class="text-[13px] font-bold text-[#0f172a] truncate">{{ $p->partyName() }}</div>
                                        @if($p->partyContext())
                                            <div class="text-[11px] text-[#94a3b8] truncate">{{ $p->partyContext() }}</div>
                                        @endif
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @include('pages.communications.partials.channel-icon', ['icon' => $p->channelIcon(), 'label' => $p->channelLabel()])
                            </td>
                            <td class="px-5 py-4">
                                <span class="inline-flex rounded-md bg-[#eff6ff] text-[#2563eb] px-2 py-0.5 text-[10px] font-black tracking-wide">
                                    {{ $p->directionLabel() }}
                                </span>
                            </td>
                            <td class="px-5 py-4">
                                <div class="flex items-start gap-2">
                                    <span class="text-[13px] text-[#334155] leading-snug">{{ $p->summary() }}</span>
                                    @if($p->hasBillingLink())
                                        <a href="{{ $p->billingClaimUrl() ?? route('billing-claims-audit.index') }}" class="shrink-0 text-[10px] font-bold text-[#047857] hover:underline whitespace-nowrap">{{ $p->billingLinkLabel() }}</a>
                                    @endif
                                    @if($p->hasArabicTag())
                                        <span class="shrink-0 inline-flex rounded bg-[#eff6ff] text-[#2563eb] px-1.5 py-0.5 text-[9px] font-black">AR</span>
                                    @endif
                                </div>
                            </td>
                            <td class="px-5 py-4">
                                @include('pages.communications.partials.handled-badge', ['label' => $p->handledLabel(), 'tone' => $p->handledTone()])
                            </td>
                            <td class="px-5 py-4 text-[12px] text-[#64748b] whitespace-nowrap">{{ $p->whenLabel() }}</td>
                            <td class="px-5 py-4 text-right">
                                <a href="{{ route('communications.show', $item) }}" class="inline-flex items-center gap-0.5 text-[12px] font-bold text-[#2563eb] hover:text-[#1d4ed8]">
                                    {{ $p->actionLabel() }}
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><path d="m9 18 6-6-6-6"/></svg>
                                </a>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="7" class="px-5 py-16 text-center">
                                <p class="text-[14px] font-semibold text-[#64748b]">No communications for this period yet</p>
                                <p class="text-[12px] text-[#94a3b8] mt-1">Send a request, log a call, or wait for inbound sync from RingCentral / Google Workspace.</p>
                                @can('send', \App\Models\Communication::class)
                                    <div class="flex justify-center gap-2 mt-4">
                                        <a href="{{ route('communications.send-request.create') }}" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Send request</a>
                                        <span class="text-[#cbd5e1]">·</span>
                                        <button type="button" x-data @click="document.getElementById('manual-log-panel')?.classList.toggle('hidden')" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Log call / note</button>
                                    </div>
                                @endcan
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="px-5 py-3.5 border-t border-[#f1f5f9] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 bg-[#fafbfc]">
            <p class="text-[12px] text-[#64748b]">
                Showing {{ $communications->firstItem() ?? 0 }}–{{ $communications->lastItem() ?? 0 }}
                of {{ number_format($communications->total()) }} communications · {{ $period->format('M Y') }}
            </p>
            <div>{{ $communications->links() }}</div>
        </div>
    </div>

    @can('send', \App\Models\Communication::class)
        <div id="manual-log-panel" class="hidden">
            @include('pages.communications.partials.manual-log-form')
        </div>
    @endcan

    @can('send', \App\Models\Communication::class)
        @include('pages.communications.partials.compose-modals')
    @endcan
</div>
@endsection
