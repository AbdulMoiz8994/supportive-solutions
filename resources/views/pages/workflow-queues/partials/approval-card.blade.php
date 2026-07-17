@props(['card'])

@php
    $slaClass = match($card['sla']['tone'] ?? 'ok') {
        'now' => 'bg-[#fee2e2] text-[#991b1b]',
        'soon' => 'bg-[#fef3c7] text-[#92400e]',
        default => 'bg-[#e2e8f0] text-[#475569]',
    };
    $reasonClass = ($card['reason_tone'] ?? 'info') === 'warn'
        ? 'bg-[#fffbeb] border-[#f59e0b] text-[#92400e]'
        : 'bg-[#f8fafc] border-[#93c5fd] text-[#475569]';
    $borderClass = ($card['urgent'] ?? false) ? 'border-[#fca5a5]' : 'border-[#e2e8f0]';
@endphp

<article class="rounded-[11px] border {{ $borderClass }} bg-white overflow-hidden mb-3.5" data-queue-slug="{{ $card['slug'] }}">
    <div class="px-4 py-3 flex items-start gap-2.5 border-b border-[#f1f5f9]">
        <div class="flex-1 min-w-0">
            <div class="text-[10.5px] uppercase tracking-wide text-[#94a3b8] font-bold">{{ $card['category'] }}</div>
            <h4 class="text-[14.5px] font-bold text-[#0f172a] mt-0.5 leading-snug">{{ $card['title'] }}</h4>
        </div>
        <span class="shrink-0 text-[11px] font-bold px-2 py-1 rounded-full {{ $slaClass }}">{{ $card['sla']['label'] ?? 'Due soon' }}</span>
    </div>

    <div class="px-4 py-3">
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-1.5 mb-2.5">
            @foreach($card['context'] ?? [] as $row)
                <div>
                    <dt class="text-[11px] text-[#94a3b8]">{{ $row['label'] }}</dt>
                    <dd class="text-[12.5px] font-semibold text-[#0f172a]">
                        @if(str_starts_with($row['value'] ?? '', 'badge:'))
                            @php
                                [, $variant, $text] = explode(':', $row['value'], 3);
                                $pill = match($variant) {
                                    'red' => 'bg-[#fee2e2] text-[#991b1b]',
                                    'green' => 'bg-[#d1fae5] text-[#065f46]',
                                    default => 'bg-[#f1f5f9] text-[#475569]',
                                };
                            @endphp
                            <span class="inline-flex px-2 py-0.5 rounded-full text-[11px] font-semibold {{ $pill }}">{{ $text }}</span>
                        @else
                            {{ $row['value'] }}
                        @endif
                    </dd>
                </div>
            @endforeach
        </dl>
        @if(!empty($card['reason']))
            <div class="text-[12.5px] border border-[#f1f5f9] border-l-[3px] rounded-[7px] px-3 py-2 {{ $reasonClass }}">
                @if(($card['reason_tone'] ?? '') === 'warn')⚠ @endif{!! $card['reason'] !!}
            </div>
        @endif
    </div>

    <div class="px-4 py-2.5 border-t border-[#f1f5f9] bg-[#fcfdfe] flex flex-wrap gap-2 items-center">
        @foreach($card['actions'] ?? [] as $action)
            @if(($action['action'] ?? '') === 'review')
                @continue
            @endif
            @php
                $btnClass = match($action['variant'] ?? 'secondary') {
                    'success' => 'bg-[#059669] text-white border-transparent hover:bg-[#047857]',
                    'danger' => 'bg-white text-[#dc2626] border-[#fca5a5] hover:bg-[#fef2f2]',
                    default => 'bg-white text-[#334155] border-[#e2e8f0] hover:bg-[#f8fafc]',
                };
            @endphp
            <form action="{{ route('workflow-queues.action', $card['slug']) }}" method="POST" class="inline queue-action-form" data-queue-slug="{{ $card['slug'] }}">
                @csrf
                <input type="hidden" name="queue_action" value="{{ $action['action'] }}">
                @if(!empty($card['approve_type']))
                    <input type="hidden" name="approve_type" value="{{ $card['approve_type'] }}">
                    <input type="hidden" name="approve_id" value="{{ $card['approve_id'] }}">
                @endif
                <button type="submit" class="inline-flex items-center px-3 py-1.5 rounded-lg text-[12px] font-semibold border transition {{ $btnClass }}">
                    {{ $action['label'] }}
                </button>
            </form>
        @endforeach
        @if(!empty($card['review_url']))
            <a href="{{ $card['review_url'] }}" class="ml-auto text-[12px] text-[#2563eb] font-semibold hover:underline">{{ $card['review_label'] ?? 'Open →' }}</a>
        @elseif(!empty($card['review_label']))
            <span class="ml-auto text-[12px] text-[#94a3b8]">{{ $card['review_label'] }}</span>
        @endif
    </div>
</article>
