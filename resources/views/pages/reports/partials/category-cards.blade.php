@props(['categories', 'active', 'queryParams'])

<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-3">
    @foreach($categories as $cat)
        @php
            $tones = [
                'fin' => 'bg-[#dbeafe]',
                'ops' => 'bg-[#d1fae5]',
                'comp' => 'bg-[#ede9fe]',
                'hr' => 'bg-[#fce7f3]',
                'ai' => 'bg-[#e0e7ff]',
                'cust' => 'bg-[#f1f5f9]',
            ];
            $isActive = $active === $cat['key'];
        @endphp
        <a href="{{ route('reports.index', $queryParams(['category' => $cat['key']])) }}"
           class="block rounded-xl border bg-white p-3.5 text-center transition {{ $isActive ? 'border-[#2563eb] ring-2 ring-[#dbeafe]' : 'border-[#e2e8f0] hover:border-[#cbd5e1]' }}">
            <div class="w-10 h-10 rounded-[11px] {{ $tones[$cat['tone']] ?? 'bg-[#f1f5f9]' }} flex items-center justify-center text-lg mx-auto mb-2">{{ $cat['icon'] }}</div>
            <div class="text-[12.5px] font-semibold text-[#0f172a]">{{ $cat['label'] }}</div>
            <div class="text-[11px] text-[#94a3b8] mt-0.5">{{ $cat['count'] }}</div>
        </a>
    @endforeach
</div>
