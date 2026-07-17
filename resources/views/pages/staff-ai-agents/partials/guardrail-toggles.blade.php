@props(['guardrails', 'ceiling'])

<div class="flex items-center justify-between py-2.5 border-b border-[#f1f5f9]">
    <div>
        <p class="font-semibold text-[#0f172a] text-[13px]">Always hold on CP-01 fail</p>
        <p class="text-[11px] text-[#94a3b8]">Never bill over an unpaid prior balance / closed case</p>
    </div>
    <label class="relative inline-flex cursor-pointer">
        <input type="hidden" name="hold_on_gate_fail" value="0">
        <input type="checkbox" name="hold_on_gate_fail" value="1" @checked($guardrails['hold_on_gate_fail'] ?? false) class="sr-only peer">
        <span class="w-[38px] h-[22px] rounded-full bg-[#cbd5e1] peer-checked:bg-[#2563eb] relative after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-[18px] after:h-[18px] after:bg-white after:rounded-full peer-checked:after:translate-x-4 transition"></span>
    </label>
</div>

<div class="flex items-center justify-between py-2.5 border-b border-[#f1f5f9]">
    <div>
        <p class="font-semibold text-[#0f172a] text-[13px]">Auto-resubmit clean rejections</p>
    </div>
    <label class="relative inline-flex cursor-pointer">
        <input type="hidden" name="auto_resubmit" value="0">
        <input type="checkbox" name="auto_resubmit" value="1" @checked($guardrails['auto_resubmit'] ?? false) class="sr-only peer">
        <span class="w-[38px] h-[22px] rounded-full bg-[#cbd5e1] peer-checked:bg-[#2563eb] relative after:content-[''] after:absolute after:top-0.5 after:left-0.5 after:w-[18px] after:h-[18px] after:bg-white after:rounded-full peer-checked:after:translate-x-4 transition"></span>
    </label>
</div>

<div class="flex items-center justify-between py-2.5 border-b border-[#f1f5f9]">
    <div>
        <p class="font-semibold text-[#0f172a] text-[13px]">Require approval above</p>
    </div>
    <div class="flex items-center gap-1">
        <span class="text-[13px] font-bold text-[#64748b]">$</span>
        <input type="number" name="approval_threshold" min="0" step="100"
               value="{{ $guardrails['approval_threshold'] ?? 0 }}"
               class="w-28 px-2 py-1 text-[13px] border border-[#e2e8f0] rounded-lg">
    </div>
</div>

<div class="flex items-center justify-between py-2.5">
    <div>
        <p class="font-semibold text-[#0f172a] text-[13px]">Miss-rate ceiling</p>
    </div>
    <x-ui.pill variant="red" size="sm">{{ $ceiling }}%</x-ui.pill>
</div>
