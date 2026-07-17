@extends('layouts.app')

@section('content')
<div class="max-w-2xl mx-auto space-y-6" x-data="{ showVoid: false }">

    <div>
        <a href="{{ route('forms') }}" class="text-[12px] font-semibold text-[#2563eb]">← Back to Forms</a>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between mt-2">
            <div>
                <h1 class="text-[24px] font-extrabold text-[#0f172a]">{{ $template['name'] ?? 'Form submission' }}</h1>
                <p class="text-[13px] text-[#64748b] mt-1">For {{ $subject['name'] }}</p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if(!$submission['locked'] && auth()->user()?->hasPermission('manage_forms'))
                    <x-ui.btn variant="outline" size="sm" :href="route('forms.submissions.edit', $submission['id'])">Edit</x-ui.btn>
                @endif
                @if($submission['download_url'])
                    <x-ui.btn variant="outline" size="sm" :href="$submission['download_url']">Download</x-ui.btn>
                @endif
                @if(!empty($submission['can_void']) && auth()->user()?->hasPermission('manage_forms'))
                    <x-ui.btn variant="outline" size="sm" type="button" @click="showVoid = !showVoid">Void</x-ui.btn>
                @endif
            </div>
        </div>
    </div>

    @if(!empty($submission['can_void']) && auth()->user()?->hasPermission('manage_forms'))
        <div x-show="showVoid" x-cloak class="rounded-2xl border border-[#fecaca] bg-[#fef2f2] p-5 space-y-3">
            <div class="text-[13px] font-semibold text-[#991b1b]">Void this submission</div>
            <p class="text-[12px] text-[#7f1d1d]">Voided forms stay on record but cannot be edited. Signed PDFs remain filed.</p>
            <form method="POST" action="{{ route('forms.submissions.void', $submission['id']) }}" class="space-y-3">
                @csrf
                <label class="block text-[12px] font-semibold text-[#7f1d1d]">Reason</label>
                <input type="text" name="void_reason" required maxlength="500"
                       class="w-full rounded-lg border border-[#fecaca] px-3 py-2 text-[13px] bg-white"
                       placeholder="e.g. Wrong client, duplicate, superseded">
                <x-ui.btn variant="primary" size="sm" type="submit">Confirm void</x-ui.btn>
            </form>
        </div>
    @endif

    <div class="rounded-2xl border border-[#e6eef9] bg-white p-5 space-y-4">
        <div class="flex flex-wrap gap-3 text-[12px]">
            <span class="inline-flex px-2 py-0.5 rounded-full font-semibold
                @if($submission['status'] === 'signed') bg-[#ecfdf3] text-[#067647]
                @elseif($submission['status'] === 'awaiting_signature') bg-[#fff7ed] text-[#c2410c]
                @elseif($submission['status'] === 'voided') bg-[#fef2f2] text-[#b91c1c]
                @elseif($submission['status'] === 'expired') bg-[#fef3c7] text-[#92400e]
                @else bg-[#f1f5f9] text-[#475569]
                @endif">
                {{ $submission['status_label'] }}
            </span>
            @if($submission['locked'])
                <span class="text-[#94a3b8]">Locked</span>
            @endif
            <span class="text-[#64748b]">Created {{ $submission['created_at'] }}</span>
        </div>

        @if($submission['signed_at'])
            <div class="rounded-xl bg-[#f8fbff] border border-[#e6eef9] px-4 py-3 text-[12px] text-[#64748b] space-y-2">
                @if(!empty($submission['signature_image']))
                    <img src="{{ $submission['signature_image'] }}" alt="Signature" class="max-h-20 border border-[#e2e8f0] rounded-lg bg-white p-1">
                @endif
                <div>
                    Signed {{ $submission['signed_at'] }}
                    @if($submission['signed_by_name'])
                        by <strong class="text-[#0f172a]">{{ $submission['signed_by_name'] }}</strong>
                    @endif
                </div>
            </div>
        @endif

        @if(!empty($submission['voided_at']))
            <div class="rounded-xl bg-[#fef2f2] border border-[#fecaca] px-4 py-3 text-[12px] text-[#7f1d1d]">
                Voided {{ $submission['voided_at'] }}
                @if(!empty($submission['void_reason']))
                    — {{ $submission['void_reason'] }}
                @endif
            </div>
        @endif

        @foreach($fields as $field)
            <div>
                <div class="text-[11px] font-semibold uppercase tracking-wide text-[#64748b] mb-1">{{ $field['label'] }}</div>
                <div class="text-[13px] text-[#0f172a] whitespace-pre-wrap">{{ $field['value'] ?: '—' }}</div>
            </div>
        @endforeach

        @if(empty($fields))
            <p class="text-[13px] text-[#64748b]">No field data recorded for this submission.</p>
        @endif
    </div>
</div>
@endsection
