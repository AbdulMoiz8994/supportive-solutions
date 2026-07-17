@php($type = $type ?? 'text')
<div class="space-y-1.5">
    <label class="text-[10px] font-black text-[#94a3b8] uppercase tracking-wider">{{ $label }}</label>
    <input type="{{ $type }}" name="{{ $name }}" value="{{ $value }}" @if($type==='number') step="0.01" @endif
        {!! $attrs ?? '' !!}
        class="w-full px-4 py-2.5 bg-blue-50/40 border border-blue-200 rounded-xl text-[13px] font-semibold text-[#1e293b] outline-none focus:ring-2 focus:ring-blue-500/15">
</div>
