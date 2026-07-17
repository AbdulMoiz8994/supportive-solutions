@php($type = $type ?? 'text')
@php($placeholder = $placeholder ?? '')
<div class="space-y-1.5">
    <label class="text-[11px] font-black text-[#94a3b8] uppercase tracking-wider">{{ $label }}</label>
    <input type="{{ $type }}" name="{{ $name }}" x-model="form.{{ $model }}" placeholder="{{ $placeholder }}"
        @if($type === 'number') step="0.01" @endif
        {!! $attrs ?? '' !!}
        class="w-full px-4 py-2.5 bg-white border border-[#e2e8f0] rounded-xl text-[13px] font-semibold text-[#1e293b] outline-none focus:ring-2 focus:ring-blue-500/10">
</div>
