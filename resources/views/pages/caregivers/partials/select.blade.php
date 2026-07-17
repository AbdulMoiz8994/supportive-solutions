<div class="space-y-1.5">
    <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">{{ $label }}</label>
    <div class="relative">
        <select name="{{ $name }}" x-model="form.{{ $model }}"
            class="w-full px-4 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[13px] font-semibold text-[#1e293b] outline-none appearance-none focus:ring-2 focus:ring-blue-500/10">
            <option value="">— Select —</option>
            @foreach($options as $opt)
                <option value="{{ $opt }}">{{ $opt }}</option>
            @endforeach
        </select>
        <svg class="w-4 h-4 absolute right-4 top-3 text-[#94a3b8] pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/></svg>
    </div>
</div>
