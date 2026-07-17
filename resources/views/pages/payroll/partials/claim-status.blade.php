@php
    $claim = $payrollClaim ?? null;
    $isMich = ($record->program_tag ?? null) === 'MICH';
@endphp

@if($isMich || $claim)
@php
    $status = $claim?->status;
@endphp

<div class="bg-white rounded-2xl border border-[#e6eef9] p-5 shadow-sm">
    <div class="flex items-center justify-between gap-3 mb-3">
        <h3 class="text-[14px] font-bold text-[#0f172a]">Billing claim (Availity)</h3>
        @if($status)
            @include('pages.payroll.partials.claim-status-badge', ['status' => $status])
        @elseif($isMich)
            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[11px] font-bold border bg-gray-100 text-gray-500 border-gray-200">Not submitted</span>
        @endif
    </div>

    @if($claim)
        <dl class="space-y-2 text-[12px] text-[#64748b]">
            @if($claim->claim_reference_id)
                <div class="flex justify-between gap-3">
                    <dt class="font-semibold">Claim reference</dt>
                    <dd class="font-mono text-[#0f172a] text-right break-all">{{ $claim->claim_reference_id }}</dd>
                </div>
            @endif
            @if($claim->submitted_at)
                <div class="flex justify-between gap-3">
                    <dt class="font-semibold">Last submitted</dt>
                    <dd class="text-[#0f172a] text-right">{{ $claim->submitted_at->format('M j, Y g:i A') }}</dd>
                </div>
            @endif
            @if($claim->error_message && in_array($status, ['failed', 'rejected'], true))
                <div class="mt-2 rounded-lg border border-[#fecaca] bg-[#fef2f2] px-3 py-2 text-[12px] text-[#b91c1c]">
                    {{ e($claim->error_message) }}
                </div>
            @endif
        </dl>
    @elseif($isMich)
        <p class="text-[12px] text-[#94a3b8]">Claim will be submitted to Availity when the payroll batch is approved.</p>
    @endif
</div>
@endif
