@props(['headers' => []])

<div class="overflow-x-auto -mx-1">
    <table class="w-full min-w-[640px]">
        <thead>
            <tr class="text-left text-[10px] font-black uppercase tracking-[0.12em] text-[#94a3b8] border-b border-slate-100 bg-slate-50/50">
                @foreach($headers as $header)
                    <th class="py-3 px-3">{{ $header }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody {{ $attributes }}>
            {{ $slot }}
        </tbody>
    </table>
</div>
