<div class="space-y-2">
    <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">{{ $label }}</label>
    <div class="flex gap-2">
        <button type="button" @click="form.{{ $model }}=true"
            :class="form.{{ $model }} ? 'bg-[#2563eb] text-white' : 'bg-white text-[#475569] border border-[#e2e8f0]'"
            class="px-6 py-2 rounded-lg text-[12px] font-bold transition-all">Yes</button>
        <button type="button" @click="form.{{ $model }}=false"
            :class="!form.{{ $model }} ? 'bg-[#2563eb] text-white' : 'bg-white text-[#475569] border border-[#e2e8f0]'"
            class="px-6 py-2 rounded-lg text-[12px] font-bold transition-all">No</button>
    </div>
</div>
