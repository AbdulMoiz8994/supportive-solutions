@props([
    'url',                       // POST endpoint returning { ok, result:{ summary, next_action, flags[] } }
    'title' => 'AI brief',
    'event' => 'open-ai-panel',  // window event that opens the panel
])

{{--
    AI Assistant Panel (Claude case summary). A right slide-over that opens on a
    window event, calls the summary endpoint once, and renders the staff-facing
    summary + the single next action + attention flags. Degrades gracefully when
    the AI key isn't configured (503) — shows a friendly "not switched on" note.
--}}
<div x-data="aiSummaryPanel({ url: '{{ $url }}' })"
     x-on:{{ $event }}.window="openPanel()"
     x-cloak>
    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-[999999]">
            {{-- Scrim --}}
            <div x-show="open" x-transition.opacity class="absolute inset-0 bg-black/40 backdrop-blur-sm" @click="open = false"></div>

            {{-- Panel --}}
            <div x-show="open"
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="translate-x-full" x-transition:enter-end="translate-x-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="translate-x-0" x-transition:leave-end="translate-x-full"
                 class="absolute right-0 top-0 h-full w-full max-w-md bg-white shadow-2xl flex flex-col">

                {{-- Header --}}
                <div class="px-6 py-5 border-b border-[#eef2f9] flex items-start justify-between gap-3 shrink-0">
                    <div class="flex items-center gap-2.5">
                        <span class="w-9 h-9 rounded-xl bg-[#2563eb] text-white flex items-center justify-center shrink-0">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 2a2 2 0 0 1 2 2c0 .74-.4 1.39-1 1.73V7h1a7 7 0 0 1 7 7h1a1 1 0 0 1 1 1v3a1 1 0 0 1-1 1h-1a7 7 0 0 1-7 7H10a7 7 0 0 1-7-7H2a1 1 0 0 1-1-1v-3a1 1 0 0 1 1-1h1a7 7 0 0 1 7-7h1V5.73A2 2 0 0 1 12 2z"/><circle cx="8.5" cy="14" r="1"/><circle cx="15.5" cy="14" r="1"/></svg>
                        </span>
                        <div>
                            <h3 class="text-[15px] font-bold text-[#0f172a] leading-tight">{{ $title }}</h3>
                            <p class="text-[11px] font-semibold text-[#94a3b8]">AI assistant · Claude</p>
                        </div>
                    </div>
                    <button type="button" @click="open = false" class="w-8 h-8 rounded-full border border-[#eef2f9] flex items-center justify-center text-[#94a3b8] hover:bg-[#f8fafc] shrink-0">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
                    </button>
                </div>

                {{-- Body --}}
                <div class="flex-1 overflow-y-auto px-6 py-5">

                    {{-- Loading --}}
                    <div x-show="loading" class="flex flex-col items-center justify-center py-16 text-center">
                        <svg class="w-7 h-7 text-[#2563eb] animate-spin mb-3" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/><path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.4 0 0 5.4 0 12h4z"/></svg>
                        <p class="text-[12.5px] font-semibold text-[#64748b]">Reading the chart and writing your brief…</p>
                    </div>

                    {{-- Not configured (503) --}}
                    <div x-show="notConfigured" x-cloak class="rounded-xl border border-[#fdecc8] bg-[#fffaf0] p-4">
                        <p class="text-[13px] font-bold text-[#92400e] mb-1">AI isn't switched on yet</p>
                        <p class="text-[12px] text-[#b45309] leading-relaxed">The case-summary engine is built and tested. It goes live once the agency adds the Claude API key (and signs the Anthropic/Bedrock BAA for real client data).</p>
                    </div>

                    {{-- Error --}}
                    <div x-show="error && !notConfigured" x-cloak class="rounded-xl border border-[#fee4e2] bg-[#fef3f2] p-4">
                        <p class="text-[13px] font-bold text-[#b42318] mb-1">Couldn't generate the brief</p>
                        <p class="text-[12px] text-[#d92d20] leading-relaxed" x-text="error"></p>
                        <button type="button" @click="reload()" class="mt-3 inline-flex items-center gap-1.5 text-[12px] font-bold text-[#2563eb] hover:text-[#1d4ed8]">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M23 4v6h-6M1 20v-6h6"/><path d="M3.51 9a9 9 0 0114.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0020.49 15"/></svg>
                            Try again
                        </button>
                    </div>

                    {{-- Result --}}
                    <div x-show="data && !loading" x-cloak class="space-y-5">
                        {{-- Summary --}}
                        <div>
                            <p class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider mb-1.5">Summary</p>
                            <p class="text-[13.5px] text-[#1e293b] leading-relaxed" x-text="data?.summary || '—'"></p>
                        </div>

                        {{-- Next action --}}
                        <div x-show="data?.next_action" class="rounded-xl border border-[#cdddf5] bg-[#eff4ff] p-4">
                            <div class="flex items-center gap-1.5 mb-1">
                                <svg class="w-3.5 h-3.5 text-[#2563eb]" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="9 18 15 12 9 6"/></svg>
                                <p class="text-[10px] font-black text-[#2563eb] uppercase tracking-wider">Next best action</p>
                            </div>
                            <p class="text-[13px] font-semibold text-[#1d4ed8] leading-relaxed" x-text="data?.next_action"></p>
                        </div>

                        {{-- Flags --}}
                        <div x-show="data?.flags && data.flags.length">
                            <p class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider mb-2">Attention flags</p>
                            <div class="flex flex-wrap gap-2">
                                <template x-for="flag in (data?.flags || [])" :key="flag">
                                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[11px] font-bold bg-[#fef3f2] text-[#b42318] border border-[#fee4e2]">
                                        <span class="w-1.5 h-1.5 rounded-full bg-current opacity-70"></span>
                                        <span x-text="flag"></span>
                                    </span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Footer --}}
                <div class="px-6 py-3.5 border-t border-[#eef2f9] shrink-0 bg-[#f8fafc]">
                    <p class="text-[10.5px] text-[#94a3b8] leading-snug">AI-generated from this chart's data — review before acting. Nothing is changed automatically.</p>
                </div>
            </div>
        </div>
    </template>
</div>

@once
@push('scripts')
<script>
    function aiSummaryPanel(config) {
        return {
            open: false, loading: false, error: '', notConfigured: false, data: null, loaded: false,
            openPanel() {
                this.open = true;
                if (!this.loaded && !this.loading) this.reload();
            },
            reload() {
                this.loading = true; this.error = ''; this.notConfigured = false; this.data = null;
                fetch(config.url, {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').getAttribute('content'),
                        'Accept': 'application/json',
                    },
                })
                .then(async (r) => {
                    const body = await r.json().catch(() => ({}));
                    if (r.ok && body.ok) {
                        this.data = body.result; this.loaded = true;
                    } else if (r.status === 503) {
                        this.notConfigured = true;
                    } else {
                        this.error = body.error || 'The AI service returned an error.';
                    }
                })
                .catch(() => { this.error = 'Could not reach the AI service.'; })
                .finally(() => { this.loading = false; });
            },
        };
    }
</script>
@endpush
@endonce
