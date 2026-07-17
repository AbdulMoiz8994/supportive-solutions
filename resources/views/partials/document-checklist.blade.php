{{--
    Document checklist progress panel. Expects $documentChecklist (array of
    items with key/label/checked/document_id). Items tick automatically when a
    matching document is on file — derived by DocumentChecklistService.
--}}
@php
    $checklist = $documentChecklist ?? [];
    $done = collect($checklist)->where('checked', true)->count();
    $total = count($checklist);
    $pct = $total > 0 ? (int) round($done / $total * 100) : 0;
@endphp
@if($total)
    <div class="rounded-2xl border border-[#e2e8f0] bg-white p-5 mb-4">
        <div class="flex items-center justify-between mb-3">
            <h3 class="text-sm font-bold text-[#0f172a]">Document Checklist</h3>
            <span class="text-xs font-semibold {{ $done === $total ? 'text-[#067647]' : 'text-[#94a3b8]' }}">{{ $done }}/{{ $total }} on file</span>
        </div>
        <div class="h-1.5 w-full rounded-full bg-[#eef2f7] overflow-hidden mb-4">
            <div class="h-full rounded-full {{ $done === $total ? 'bg-[#12b76a]' : 'bg-[#2563eb]' }}" style="width: {{ $pct }}%"></div>
        </div>
        <div class="grid grid-cols-2 md:grid-cols-3 gap-2">
            @foreach($checklist as $item)
                <div class="flex items-center gap-2 text-[13px] {{ $item['checked'] ? 'text-[#0f172a]' : 'text-[#94a3b8]' }}">
                    @if($item['checked'])
                        <svg class="w-4 h-4 text-[#12b76a] shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><polyline points="20 6 9 17 4 12"/></svg>
                    @else
                        <span class="w-4 h-4 rounded-[5px] border border-[#cbd5e1] shrink-0"></span>
                    @endif
                    <span class="font-medium">{{ $item['label'] }}</span>
                </div>
            @endforeach
        </div>
    </div>
@endif
