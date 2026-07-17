@extends('layouts.app')

@section('content')
@php
    $transcript = $presenter->transcript();
    $concern = $presenter->concern();
    $acknowledgments = $presenter->acknowledgments();
    $linkedRecords = $presenter->linkedRecords();
    $at = $communication->sent_at ?? $communication->created_at;
@endphp

<div class="space-y-5" x-data="{ bilingual: true }">
    {{-- Breadcrumb + header --}}
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
        <div class="min-w-0">
            <div class="flex items-center gap-2 text-[12px] text-[#64748b] mb-2">
                <a href="{{ route('communications.index', ['period' => $periodLabel ? \Carbon\Carbon::parse($periodLabel)->format('Y-m') : null]) }}" class="hover:text-[#2563eb]">Communications</a>
                <span>›</span>
                <span>{{ $periodLabel ?? 'Detail' }}</span>
            </div>
            <div class="flex flex-wrap items-center gap-3">
                <h1 class="text-[24px] font-extrabold text-[#0f172a] tracking-tight">
                    {{ $presenter->partyName() }} — {{ $presenter->isWellnessCall() ? 'wellness call' : strtolower($presenter->channelLabel()) }}
                </h1>
                @if($presenter->concernFlagged())
                    <span class="inline-flex items-center rounded-full bg-[#fff7ed] text-[#c2410c] border border-[#fed7aa] px-3 py-1 text-[11px] font-bold">
                        Concern flagged → your review
                    </span>
                @endif
            </div>
            <p class="text-[13px] text-[#64748b] mt-1.5">{{ $presenter->contextLine() }}</p>
        </div>
        <div class="flex flex-wrap items-center gap-2 shrink-0">
            @if($presenter->durationLabel() || $communication->channel === \App\Models\Communication::CHANNEL_CALL)
                <button type="button" class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc]">
                    Play recording
                </button>
            @endif
            @if(count($transcript) > 0 || $canViewBody)
                <button type="button" class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc]">
                    Transcript
                </button>
            @endif
            @if($communication->related_type === \App\Models\Client::class && $communication->related)
                <a href="{{ route('clients.show', $communication->related_id) }}" class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc]">
                    Open {{ $communication->related->first_name }}'s chart
                </a>
            @endif
            @if(in_array(data_get($communication->metadata, 'handled_by'), ['needs_review', 'concern'], true))
                @can('update', $communication)
                    <form method="POST" action="{{ route('communications.mark-handled', $communication->id) }}">
                        @csrf
                        <button type="submit" class="inline-flex items-center gap-1.5 px-4 py-2 text-[12px] font-semibold text-white bg-[#16a34a] rounded-xl hover:bg-[#15803d]">
                            <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                            Mark handled
                        </button>
                    </form>
                @endcan
            @endif
        </div>
    </div>

    <div class="grid grid-cols-1 xl:grid-cols-[minmax(0,1fr)_320px] gap-5 items-start">
        {{-- Main thread --}}
        <div class="space-y-4">
            <div class="rounded-2xl border border-[#e2e8f0] bg-white overflow-hidden shadow-sm">
                <div class="px-5 py-4 border-b border-[#f1f5f9] flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-[15px] font-bold text-[#0f172a]">
                            {{ $presenter->isWellnessCall() ? 'Monthly wellness call' : ($communication->subject ?: 'Communication thread') }}
                        </h2>
                        <p class="text-[12px] text-[#64748b] mt-1">
                            {{ $at?->format('M j, Y') }} · {{ $at?->format('g:i A') }}
                            @if($presenter->durationLabel())
                                · {{ $presenter->durationLabel() }}
                            @endif
                            @if($presenter->hasArabicTag())
                                · conducted in Arabic by VA
                            @endif
                        </p>
                    </div>
                    @if($presenter->hasArabicTag())
                        <button type="button" @click="bilingual = !bilingual"
                                class="inline-flex items-center rounded-full bg-[#eff6ff] text-[#2563eb] border border-[#bfdbfe] px-3 py-1 text-[11px] font-bold">
                            Bilingual AR ⇄ EN
                        </button>
                    @endif
                </div>

                <div class="p-5 space-y-4">
                    @if($presenter->aiSummary())
                        <div class="rounded-xl border border-[#bfdbfe] bg-[#eff6ff] p-4">
                            <div class="flex items-start gap-3">
                                <span class="w-8 h-8 rounded-lg bg-white border border-[#bfdbfe] flex items-center justify-center text-[#2563eb] shrink-0">
                                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/></svg>
                                </span>
                                <div>
                                    <p class="text-[11px] font-bold uppercase tracking-wide text-[#2563eb] mb-1">AI call summary (auto)</p>
                                    <p class="text-[13px] text-[#1e3a8a] leading-relaxed">{{ $presenter->aiSummary() }}</p>
                                </div>
                            </div>
                        </div>
                    @endif

                    @if(count($transcript) > 0)
                        <div class="space-y-3 max-h-[52vh] overflow-y-auto pr-1">
                            @foreach($transcript as $line)
                                @php
                                    $outbound = ($line['direction'] ?? 'in') === 'out';
                                @endphp
                                <div class="flex {{ $outbound ? 'justify-end' : 'justify-start' }}">
                                    <div class="max-w-[85%] rounded-2xl px-4 py-3 {{ $outbound ? 'bg-[#2563eb] text-white rounded-br-md' : 'bg-[#f1f5f9] text-[#0f172a] rounded-bl-md' }}">
                                        <div class="text-[10px] opacity-80 mb-1.5">
                                            {{ $line['sender'] ?? ($outbound ? 'VA' : $presenter->partyName()) }}
                                            · {{ $line['at'] ?? '' }}
                                        </div>
                                        @if(!empty($line['en']))
                                            <p class="text-[13px] leading-relaxed">{{ e($line['en']) }}</p>
                                        @endif
                                        @if($presenter->hasArabicTag() && !empty($line['ar']))
                                            <p class="text-[13px] leading-relaxed mt-1 {{ $outbound ? 'text-blue-100' : 'text-[#64748b]' }}" x-show="bilingual" x-cloak dir="rtl">{{ e($line['ar']) }}</p>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @elseif($canViewBody && $communication->body)
                        <div class="rounded-xl bg-[#f8fafc] border border-[#e2e8f0] p-4">
                            <p class="text-[13px] text-[#334155] whitespace-pre-wrap leading-relaxed">{{ e($communication->body) }}</p>
                        </div>
                    @else
                        <p class="text-[13px] text-[#94a3b8] italic">Message content is not available for your role.</p>
                    @endif
                </div>

                @if($presenter->concernFlagged())
                    <div class="px-5 py-4 border-t border-[#fde68a] bg-[#fffbeb] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-start gap-2 text-[12px] text-[#92400e]">
                            <svg class="w-4 h-4 shrink-0 mt-0.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                            <span>AI raised a concern note for your awareness — confirm no follow-up needed, or send a check-in.</span>
                        </div>
                        <div class="flex items-center gap-2 shrink-0">
                            <button type="button" class="px-3 py-1.5 text-[12px] font-semibold rounded-lg border border-[#e2e8f0] bg-white text-[#475569]">Edit note</button>
                            <button type="button" class="px-3 py-1.5 text-[12px] font-semibold rounded-lg bg-[#2563eb] text-white">Acknowledge</button>
                        </div>
                    </div>
                @endif
            </div>

        </div>

        {{-- Right sidebar --}}
        <aside class="space-y-4 xl:sticky xl:top-4">
            @if($concern)
                <div class="rounded-2xl border border-[#fed7aa] bg-[#fff7ed] p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-[#c2410c] mb-2">AI concern note</p>
                    <p class="text-[13px] text-[#9a3412] leading-relaxed">{{ e($concern['text'] ?? '') }}</p>
                    <div class="mt-3 pt-3 border-t border-[#fed7aa] flex items-center justify-between text-[12px]">
                        <span class="text-[#9a3412]">Billing impact:</span>
                        <span class="inline-flex rounded-full bg-[#ecfdf5] text-[#047857] px-2 py-0.5 text-[10px] font-bold uppercase">{{ e($concern['billing_impact'] ?? 'None') }}</span>
                    </div>
                </div>
            @endif

            @if(count($acknowledgments) > 0)
                <div class="rounded-2xl border border-[#bfdbfe] bg-[#eff6ff] p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-[#2563eb] mb-3">Monthly acknowledgments</p>
                    <ul class="space-y-2.5">
                        @foreach($acknowledgments as $item)
                            <li class="flex items-center justify-between gap-3 text-[12px]">
                                <span class="text-[#1e40af]">{{ e($item['label'] ?? '') }}</span>
                                <span class="inline-flex items-center gap-1 text-[#047857] font-semibold">
                                    <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                                    {{ e($item['value'] ?? 'Confirmed') }}
                                </span>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if(count($linkedRecords) > 0)
                <div class="rounded-2xl border border-[#bfdbfe] bg-[#eff6ff] p-4">
                    <p class="text-[11px] font-bold uppercase tracking-wide text-[#2563eb] mb-3">Linked records</p>
                    <ul class="space-y-2">
                        @foreach($linkedRecords as $record)
                            <li>
                                <a href="{{ $record['url'] ?? '#' }}" class="flex items-center justify-between gap-2 rounded-lg bg-white/70 border border-[#dbeafe] px-3 py-2.5 text-[12px] font-semibold text-[#1d4ed8] hover:bg-white transition">
                                    <span class="truncate">{{ e($record['label'] ?? 'Record') }}</span>
                                    <span class="shrink-0 text-[11px]">{{ ($record['icon'] ?? '') === 'recording' ? 'Play' : 'Open' }} ›</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="rounded-2xl border border-[#e2e8f0] bg-white p-4 text-[12px] text-[#64748b] space-y-2">
                <div class="flex justify-between"><span>Status</span><span class="font-semibold text-[#0f172a] capitalize">{{ $communication->status }}</span></div>
                <div class="flex justify-between"><span>Direction</span><span class="font-semibold text-[#0f172a] uppercase">{{ $presenter->directionLabel() }}</span></div>
                <div class="flex justify-between"><span>Sender</span><span class="font-semibold text-[#0f172a]">{{ $communication->sender?->name ?? 'System' }}</span></div>
                @if($communication->failure_reason)
                    <div class="pt-2 border-t border-[#f1f5f9] text-[#dc2626]">{{ $communication->failure_reason }}</div>
                @endif
            </div>
        </aside>
    </div>
</div>
@endsection
