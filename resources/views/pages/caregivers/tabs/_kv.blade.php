{{-- read-only key/value box: $label, $value --}}
<div class="space-y-1.5">
    <label class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">{{ $label }}</label>
    <div class="px-4 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[13px] font-semibold text-[#1e293b]">{{ $value !== null && $value !== '' ? $value : '—' }}</div>
</div>
