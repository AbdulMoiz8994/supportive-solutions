@extends('layouts.app')

@section('content')
<div class="max-w-full mx-auto pb-12" x-data="dashboardApprovals(@js([
    'approvals' => $approvals,
    'approvalCount' => $approvalCount,
    'approvalChips' => $approvalChips,
    'automationPct' => $fleet['automation'],
    'csrfToken' => $csrfToken,
    'approveUrlTemplate' => route('dashboard.approve', ['type' => '__TYPE__', 'id' => '__ID__']),
]))">

    {{-- Flash messages --}}
    @if(session('success'))
        <div x-data="{show:true}" x-show="show" x-transition
             class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-3 text-sm font-semibold text-[#067647]">
            <span>{{ session('success') }}</span>
            <button @click="show=false" class="text-[#067647]/60 hover:text-[#067647]">&times;</button>
        </div>
    @endif
    @if(session('error'))
        <div x-data="{show:true}" x-show="show" x-transition
             class="mb-4 flex items-center justify-between gap-3 rounded-xl border border-[#fee4e2] bg-[#fef3f2] px-4 py-3 text-sm font-semibold text-[#d92d20]">
            <span>{{ session('error') }}</span>
            <button @click="show=false" class="text-[#d92d20]/60 hover:text-[#d92d20]">&times;</button>
        </div>
    @endif

    {{-- ── Greeting + quick actions ──────────────────────────────────────── --}}
    <div class="flex flex-wrap items-start justify-between gap-4 mb-5">
        <div>
            <h1 class="text-2xl font-extrabold text-[#0f172a] tracking-tight">{{ $greeting }}, {{ $firstName }}</h1>
            <p class="text-sm text-[#64748b] mt-1">{{ $todayLabel }} · here's what needs you and how the agency is running</p>
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            <x-ui.btn variant="outline" href="{{ route('intakes.index') }}">New intake</x-ui.btn>
            <x-ui.btn variant="outline" href="{{ route('billing-claims-audit.index') }}">Generate bill</x-ui.btn>
            <x-ui.btn variant="outline" href="{{ route('communications.index', ['compose' => 'efax']) }}">Send eFax</x-ui.btn>
            <x-ui.btn variant="outline" href="{{ route('reports.index') }}">Run report</x-ui.btn>
            <x-ui.btn variant="ghost" onclick="window.location.reload()">
                <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 12a9 9 0 1 0 3-6.7L3 8"/><path d="M3 3v5h5"/></svg>
                Refresh
            </x-ui.btn>
            <x-ui.btn variant="primary" x-on:click="$dispatch('open-ai-panel')">Daily brief</x-ui.btn>
        </div>
    </div>

    {{-- ── Approval banner ───────────────────────────────────────────────── --}}
    <div class="rounded-2xl p-6 text-white relative overflow-hidden shadow-[0_8px_24px_rgba(37,99,235,0.25)]"
         style="background:linear-gradient(110deg,#2563eb 0%,#3b82f6 60%,#4f8ff7 100%);">
        <div class="absolute -right-10 -top-16 w-64 h-64 rounded-full bg-white/10"></div>
        <div class="absolute right-24 -bottom-20 w-52 h-52 rounded-full bg-white/5"></div>
        <div class="relative flex items-start justify-between gap-6">
            <div class="flex items-start gap-5">
                <div class="text-5xl font-extrabold leading-none shrink-0" x-text="approvalCount"></div>
                <div>
                    <template x-if="approvalCount > 0">
                        <div>
                            <div class="text-base font-bold leading-tight">items need your approval</div>
                            <p class="text-sm text-white/85 mt-1.5 max-w-2xl leading-relaxed">
                                You're the single approval gate — agents handled <span x-text="automationPct"></span>% automatically and queued the rest.
                            </p>
                            <div class="flex flex-wrap gap-2 mt-3.5">
                                <template x-for="chip in approvalChips" :key="chip.label">
                                    <span class="bg-white/15 border border-white/20 rounded-full px-3 py-1 text-xs font-semibold backdrop-blur-sm" x-text="chip.label"></span>
                                </template>
                            </div>
                        </div>
                    </template>
                    <template x-if="approvalCount === 0">
                        <div>
                            <div class="text-base font-bold leading-tight">You're all caught up</div>
                            <p class="text-sm text-white/85 mt-1.5 max-w-2xl leading-relaxed">
                                Agents handled everything automatically — nothing is waiting on your approval right now.
                            </p>
                        </div>
                    </template>
                </div>
            </div>
            <a href="{{ route('workflow-queues') }}"
               class="shrink-0 bg-white/15 hover:bg-white/25 border border-white/30 rounded-[10px] px-4 py-2.5 text-sm font-semibold whitespace-nowrap transition-colors backdrop-blur-sm inline-flex items-center gap-1">
                Open approval queue <span>&rsaquo;</span>
            </a>
        </div>
    </div>

    {{-- ── KPI strip ─────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-6 gap-3 mt-4">
        @foreach($kpis as $kpi)
            <x-ui.stat-card :label="$kpi['label']" :value="$kpi['value']" :sub="$kpi['sub']" />
        @endforeach
    </div>

    {{-- ── Needs approval + Coming up ────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-4 mt-4">
        <x-ui.panel class="lg:col-span-2" title="Needs your approval" :link="route('workflow-queues')" linkLabel="Workflow Queues">
            <div x-show="toast" x-transition class="mb-3 rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-2.5 text-sm font-semibold text-[#067647]" x-text="toast"></div>
            <div class="divide-y divide-[#f1f5f9] -mt-1">
                <template x-for="item in approvals" :key="item.key">
                    <div class="flex items-center justify-between gap-4 py-3.5">
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-[#0f172a] truncate" x-text="item.title"></div>
                            <div class="text-sm text-[#64748b] mt-0.5 truncate" x-text="item.subtitle"></div>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <a :href="item.reviewUrl" class="inline-flex items-center justify-center font-semibold rounded-[10px] px-3 py-1.5 text-xs bg-white border border-[#d8e2f0] text-[#334155] hover:border-[#94a3b8] transition-colors">Review</a>
                            <template x-if="item.canApprove">
                                <button type="button"
                                    @click="approve(item)"
                                    :disabled="approvingKey === item.key"
                                    class="inline-flex items-center justify-center font-semibold rounded-[10px] px-3 py-1.5 text-xs bg-[#2563eb] text-white hover:bg-[#1d4ed8] disabled:opacity-60 transition-colors"
                                    x-text="approvingKey === item.key ? 'Approving…' : 'Approve'">
                                </button>
                            </template>
                            <template x-if="!item.canApprove">
                                <a :href="item.reviewUrl" class="inline-flex items-center justify-center font-semibold rounded-[10px] px-3 py-1.5 text-xs bg-white border border-[#d8e2f0] text-[#334155] hover:border-[#94a3b8] transition-colors">Verify</a>
                            </template>
                        </div>
                    </div>
                </template>
                <div x-show="approvals.length === 0" x-cloak class="py-10 text-center">
                    <div class="text-sm font-semibold text-[#0f172a]">Nothing in the queue</div>
                    <div class="text-sm text-[#94a3b8] mt-1">All approvals are handled. Check back later.</div>
                </div>
            </div>
        </x-ui.panel>

        <x-ui.panel title="Coming up" :link="route('calendar')" linkLabel="Calendar">
            <div class="space-y-2.5 -mt-1">
                @foreach($comingUp as $c)
                    <div class="flex items-center gap-3 rounded-xl border border-[#eef2f9] bg-[#fafcff] pl-3 pr-3 py-2.5 border-l-2 border-l-[#2563eb]">
                        <div class="text-center w-8 shrink-0">
                            <div class="text-lg font-extrabold text-[#0f172a] leading-none">{{ $c['day'] }}</div>
                            <div class="text-xs font-semibold text-[#94a3b8] uppercase mt-0.5">{{ $c['dow'] }}</div>
                        </div>
                        <div class="min-w-0">
                            <div class="text-sm font-bold text-[#0f172a] truncate">{{ $c['title'] }}</div>
                            <div class="text-xs text-[#94a3b8] truncate">{{ $c['meta'] }}</div>
                        </div>
                    </div>
                @endforeach
            </div>
        </x-ui.panel>
    </div>

    {{-- ── Financial snapshot + Fleet health ─────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4 mt-4">
        <x-ui.panel title="Financial snapshot · {{ $financial['period'] }}" :link="route('reports.index')" linkLabel="Reports">
            <div class="grid grid-cols-2 gap-y-6 gap-x-4 pt-1">
                <div>
                    <div class="text-sm font-medium text-[#94a3b8]">Billed</div>
                    <div class="text-2xl font-extrabold text-[#0f172a] mt-1 tracking-tight">${{ number_format($financial['billed']) }}</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-[#94a3b8]">Collected</div>
                    <div class="text-2xl font-extrabold text-[#0f172a] mt-1 tracking-tight">${{ number_format($financial['collected']) }}</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-[#94a3b8]">Outstanding</div>
                    <div class="text-2xl font-extrabold text-[#0f172a] mt-1 tracking-tight">${{ number_format($financial['outstanding']) }}</div>
                </div>
                <div>
                    <div class="text-sm font-medium text-[#94a3b8]">Blended margin</div>
                    <div class="text-2xl font-extrabold text-[#0f172a] mt-1 tracking-tight">~{{ $financial['margin'] }}%</div>
                </div>
            </div>
        </x-ui.panel>

        <x-ui.panel title="AI fleet health" :link="route('staff.index')" linkLabel="Staff &amp; AI Agents">
            <div class="space-y-4 pt-2">
                <x-ui.progress-bar label="Automation rate" :value="$fleet['automation']" :display="$fleet['automation'].'%'" color="#22c55e" />
                <x-ui.progress-bar label="Fleet uptime" :value="$fleet['uptime']" :display="$fleet['uptime'].'%'" color="#22c55e" />
                <x-ui.progress-bar label="Miss-rate (&lt;2%)" :value="100 - $fleet['missRate']" :display="$fleet['missRate'].'%'"
                                    :color="$fleet['missRate'] < 2 ? '#22c55e' : '#f79009'" />
            </div>
            <div class="text-xs text-[#94a3b8] mt-5">{{ $fleet['note'] }}</div>
        </x-ui.panel>
    </div>

    {{-- ── Recent activity ───────────────────────────────────────────────── --}}
    <div class="mt-4">
        <x-ui.panel title="Recent Activity" subtitle="View recent progress, status changes, and important workflow activity.">
            <div class="relative pl-1 pt-2">
                @forelse($recentActivity as $i => $a)
                    <div class="relative flex gap-4 {{ !$loop->last ? 'pb-6' : '' }}">
                        {{-- timeline line --}}
                        @if(!$loop->last)
                            <span class="absolute left-[5px] top-4 bottom-0 w-px bg-[#e6eef9]"></span>
                        @endif
                        <span class="relative z-10 mt-1 w-[11px] h-[11px] rounded-full shrink-0 ring-4 ring-white" style="background: {{ $a['color'] }}"></span>
                        <div class="min-w-0 -mt-0.5">
                            <div class="text-xs font-semibold text-[#94a3b8] mb-0.5">{{ $a['ago'] }}</div>
                            <div class="text-sm leading-relaxed text-[#334155]">
                                <span class="font-bold text-[#0f172a]">{{ $a['title'] }}:</span> {{ $a['desc'] }}
                            </div>
                            <div class="flex items-center gap-1.5 mt-1.5 text-xs font-semibold text-[#64748b]">
                                <svg class="w-3.5 h-3.5 text-[#94a3b8]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/></svg>
                                {{ $a['who'] }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="py-8 text-center text-sm text-[#94a3b8]">No recent activity yet.</div>
                @endforelse
            </div>
        </x-ui.panel>
    </div>

</div>

<script>
function dashboardApprovals(initial) {
    return {
        approvals: initial.approvals || [],
        approvalCount: initial.approvalCount || 0,
        approvalChips: initial.approvalChips || [],
        automationPct: initial.automationPct || 0,
        csrfToken: initial.csrfToken,
        approvingKey: null,
        toast: null,
        async approve(item) {
            if (!item.approveType || !item.approveId || this.approvingKey) return;
            this.approvingKey = item.key;
            this.toast = null;
            const url = initial.approveUrlTemplate
                .replace('__TYPE__', encodeURIComponent(item.approveType))
                .replace('__ID__', encodeURIComponent(item.approveId));
            try {
                const response = await fetch(url, {
                    method: 'POST',
                    headers: {
                        'Accept': 'application/json',
                        'Content-Type': 'application/json',
                        'X-CSRF-TOKEN': this.csrfToken,
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                });
                const data = await response.json();
                if (!response.ok) {
                    throw new Error(data.message || 'Approval failed.');
                }
                this.approvals = [...(data.approvals || [])];
                this.approvalCount = Number(data.approvalCount ?? this.approvals.length);
                this.approvalChips = [...(data.approvalChips || [])];
                this.toast = data.message || 'Item approved.';
                window.dispatchEvent(new CustomEvent('sidebar-badges:refresh'));
            } catch (error) {
                this.toast = error.message || 'Approval failed.';
            } finally {
                this.approvingKey = null;
            }
        },
    };
}
</script>
@endsection
