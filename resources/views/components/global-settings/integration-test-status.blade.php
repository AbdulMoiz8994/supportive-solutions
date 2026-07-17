@props(['slug', 'fallbackStatus' => '', 'fallbackBadge' => 'bg-slate-100 text-slate-600 border border-slate-200'])

<div class="space-y-2 max-w-md">
    <div class="flex flex-wrap items-center gap-2">
        <span
            class="inline-flex px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wide"
            :class="badgeFor('{{ $slug }}', '{{ $fallbackBadge }}')"
            x-text="testFeedback['{{ $slug }}']?.status_label || integrationStatuses['{{ $slug }}']?.label || 'Status'">
        </span>
        <span
            class="text-[10px] font-bold text-[#94a3b8] uppercase tracking-wide"
            x-show="(testFeedback['{{ $slug }}']?.latency_ms || integrationStatuses['{{ $slug }}']?.latency_ms)"
            x-text="((testFeedback['{{ $slug }}']?.latency_ms || integrationStatuses['{{ $slug }}']?.latency_ms) || 0) + 'ms'">
        </span>
    </div>
    <p class="text-xs font-bold text-[#64748b] leading-relaxed" x-text="statusFor('{{ $slug }}', @js($fallbackStatus))"></p>
    <ul class="space-y-1.5" x-show="(testFeedback['{{ $slug }}']?.checks || integrationStatuses['{{ $slug }}']?.checks || []).length">
        <template x-for="check in (testFeedback['{{ $slug }}']?.checks || integrationStatuses['{{ $slug }}']?.checks || [])" :key="check.name">
            <li class="flex items-start gap-2 text-[11px] font-semibold leading-snug">
                <span class="mt-0.5 shrink-0" :class="check.passed ? 'text-emerald-600' : 'text-red-500'" x-text="check.passed ? '✓' : '✕'"></span>
                <span>
                    <span class="font-black text-[#1e293b]" x-text="check.name"></span>
                    <span class="text-[#94a3b8]"> — </span>
                    <span class="text-[#64748b]" x-text="check.detail"></span>
                </span>
            </li>
        </template>
    </ul>
    <p
        class="text-[11px] font-bold text-amber-700 bg-amber-50 border border-amber-100 rounded-lg px-3 py-2"
        x-show="testFeedback['{{ $slug }}']?.recommendation || integrationStatuses['{{ $slug }}']?.recommendation"
        x-text="testFeedback['{{ $slug }}']?.recommendation || integrationStatuses['{{ $slug }}']?.recommendation">
    </p>
</div>
