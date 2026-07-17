@if($claim->usesAvaility())
<div class="rounded-xl border border-[#dbeafe] bg-[#f0f7ff] p-4 space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h4 class="text-[13px] font-semibold text-[#1e40af]">Availity claim status</h4>
            <p class="text-[11px] text-[#64748b] mt-0.5">HIPAA 276 inquiry — polls payer status for this MICH claim.</p>
        </div>
        @can('update', $claim)
        <form action="{{ route('billing-claims-audit.refresh-availity-status.claim', $claim) }}" method="POST">
            @csrf
            <button type="submit" class="inline-flex items-center px-3 py-1.5 text-[12px] font-semibold text-white bg-[#2563eb] rounded-lg hover:bg-[#1d4ed8] transition">
                Check Availity status
            </button>
        </form>
        @endcan
    </div>

    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-[12px]">
        <div>
            <dt class="text-[#94a3b8]">Availity status</dt>
            <dd class="font-medium text-[#0f172a]">
                @if($claim->availity_status)
                    <x-ui.pill variant="blue" size="xs">{{ $claim->availityStatusLabel() }}</x-ui.pill>
                @else
                    <span class="text-[#64748b]">Not checked yet</span>
                @endif
            </dd>
        </div>
        <div>
            <dt class="text-[#94a3b8]">Reference ID</dt>
            <dd class="font-medium text-[#0f172a] font-mono text-[11px]">{{ $claim->availity_reference_id ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-[#94a3b8]">Last checked</dt>
            <dd class="font-medium text-[#0f172a]">{{ $claim->availity_status_checked_at?->format('M j, Y g:i A') ?? '—' }}</dd>
        </div>
        <div>
            <dt class="text-[#94a3b8]">Environment</dt>
            <dd class="font-medium text-[#0f172a]">{{ config('services.availity.env') === 'production' ? 'Production' : 'Demo' }}</dd>
        </div>
    </dl>

    @if($claim->availity_status_payload && ($claim->availity_status_payload['totalCount'] ?? null))
        <p class="text-[11px] text-[#64748b]">
            Availity returned {{ $claim->availity_status_payload['totalCount'] }} matching status record(s).
        </p>
    @endif
</div>
@endif
