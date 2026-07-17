@props(['lifecycle' => []])

<div class="bg-white rounded-2xl border border-[#e6eef9] p-5 shadow-sm">
    <h3 class="text-[14px] font-bold text-[#0f172a] mb-4">Payout lifecycle</h3>
    <ol class="space-y-0">
        @foreach($lifecycle as $i => $step)
            @php
                $stateClasses = match($step['state']) {
                    'completed' => 'bg-green-500 border-green-500 text-white',
                    'in_progress' => 'bg-[#2563eb] border-[#2563eb] text-white',
                    default => 'bg-gray-200 border-gray-200 text-gray-500',
                };
                $textClass = match($step['state']) {
                    'completed' => 'text-[#0f172a] font-semibold',
                    'in_progress' => 'text-[#2563eb] font-semibold',
                    default => 'text-[#94a3b8]',
                };
            @endphp
            <li class="flex gap-3 {{ !$loop->last ? 'pb-4' : '' }}">
                <div class="flex flex-col items-center">
                    <span class="w-6 h-6 rounded-full border-2 flex items-center justify-center text-[10px] font-bold {{ $stateClasses }}">
                        @if($step['state'] === 'completed') ✓ @else {{ $i + 1 }} @endif
                    </span>
                    @if(!$loop->last)
                        <span class="w-0.5 flex-1 bg-[#e2e8f0] mt-1 min-h-[20px]"></span>
                    @endif
                </div>
                <div class="pt-0.5">
                    <span class="text-[12px] {{ $textClass }}">{{ $step['label'] }}</span>
                </div>
            </li>
        @endforeach
    </ol>
</div>
