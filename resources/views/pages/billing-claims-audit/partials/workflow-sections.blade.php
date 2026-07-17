{{-- Workflow audit sections (EMR CRM document flow) --}}
<div class="bg-white rounded-2xl border border-[#e6eef9] p-5 shadow-sm space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h3 class="text-[15px] font-bold text-[#0f172a]">Billing audit workflow</h3>
        @can('update', $claim)
        <form action="{{ route('billing-claims-audit.refresh', $claim) }}" method="POST">
            @csrf
            <button type="submit" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Refresh from auth &amp; visits</button>
        </form>
        @endcan
    </div>

    @if($claim->billing_status === \App\Models\BillingClaimAudit::BILLING_BLOCKED && $claim->hold_reason)
        <div class="p-3 rounded-xl border border-[#fecaca] bg-[#fef2f2] text-[12px] text-[#991b1b]">
            <strong>Billing blocked:</strong> {{ $claim->hold_reason }}
        </div>
    @endif

    @if($claim->hasIssueFlags())
        <div class="flex flex-wrap gap-2">
            @foreach($claim->issueFlagLabels() as $flag)
                <x-ui.pill variant="amber" size="xs">{{ $flag }}</x-ui.pill>
            @endforeach
        </div>
    @endif

    {{-- Client & Coverage --}}
    <div>
        <h4 class="text-[13px] font-semibold text-[#334155] mb-3">Client &amp; coverage</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-[12px]">
            <div><dt class="text-[#94a3b8]">Member ID</dt><dd class="font-medium text-[#0f172a]">{{ $claim->plan_member_id ?? $claim->medicaid_id ?? $claim->client?->member_id ?? '—' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Coverage type</dt><dd class="font-medium text-[#0f172a]">{{ $claim->coverage_type ? ucwords(str_replace('_', ' ', $claim->coverage_type)) : ($claim->client?->coverageType?->name ?? '—') }}</dd></div>
            <div><dt class="text-[#94a3b8]">Insurance / plan</dt><dd class="font-medium text-[#0f172a]">{{ $claim->health_plan_name ?? '—' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Payer</dt><dd class="font-medium text-[#0f172a]">{{ $claim->payer_name ?? '—' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Billing method</dt><dd class="font-medium text-[#0f172a]">{{ $claim->billing_method ? ucwords(str_replace('_', ' ', $claim->billing_method)) : ($claim->submission_channel ?? '—') }}</dd></div>
            <div><dt class="text-[#94a3b8]">Billing route</dt><dd class="font-medium text-[#0f172a]">{{ $claim->billing_route ? ucwords(str_replace('_', ' ', $claim->billing_route)) : '—' }}</dd></div>
        </dl>
    </div>

    {{-- Authorization --}}
    <div>
        <h4 class="text-[13px] font-semibold text-[#334155] mb-3">Authorization / care details</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-[12px]">
            <div><dt class="text-[#94a3b8]">Authorization #</dt><dd class="font-medium text-[#0f172a]">{{ $claim->authorization_number ?? '—' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Status</dt><dd>
                @php
                    $authStatus = $claim->authorization_status ?? 'missing';
                    $authVariant = match ($authStatus) {
                        'active' => 'green',
                        'expiring_soon' => 'amber',
                        'expired', 'missing' => 'red',
                        default => 'gray',
                    };
                @endphp
                <x-ui.pill :variant="$authVariant" size="xs">{{ ucwords(str_replace('_', ' ', $authStatus)) }}</x-ui.pill>
            </dd></div>
            <div><dt class="text-[#94a3b8]">Start date</dt><dd class="font-medium text-[#0f172a]">{{ $claim->authorization_start_date?->format('M j, Y') ?? '—' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Expiration</dt><dd class="font-medium text-[#0f172a]">{{ $claim->authorization_valid_through?->format('M j, Y') ?? '—' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Billing code</dt><dd class="font-medium text-[#0f172a]">{{ $claim->service_code ?? '—' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Approved units</dt><dd class="font-medium text-[#0f172a]">{{ $claim->units ?? '—' }} @if($claim->unit_minutes)({{ $claim->unit_minutes }} min)@endif</dd></div>
            <div><dt class="text-[#94a3b8]">Calculated approved hours</dt><dd class="font-medium text-[#0f172a]">{{ $claim->calculated_approved_hours !== null ? number_format($claim->calculated_approved_hours, 2).' hrs' : '—' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Monthly / weekly / daily</dt><dd class="font-medium text-[#0f172a]">
                @if($claim->approved_monthly_hours){{ number_format($claim->approved_monthly_hours, 1) }} mo /
                @endif
                @if($claim->approved_weekly_hours){{ number_format($claim->approved_weekly_hours, 1) }} wk /
                @endif
                @if($claim->calculated_daily_hours){{ number_format($claim->calculated_daily_hours, 2) }} daily
                @else — @endif
            </dd></div>
        </dl>
    </div>

    {{-- Visit verification --}}
    <div>
        <h4 class="text-[13px] font-semibold text-[#334155] mb-3">Visit verification (EVV)</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-[12px]">
            <div><dt class="text-[#94a3b8]">Caregiver</dt><dd class="font-medium text-[#0f172a]">{{ $claim->employee ? $claim->employee->first_name.' '.$claim->employee->last_name : '—' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Service period</dt><dd class="font-medium text-[#0f172a]">{{ $claim->period_start?->format('M j') }} – {{ $claim->period_end?->format('M j, Y') }}</dd></div>
            <div><dt class="text-[#94a3b8]">Scheduled hours</dt><dd class="font-medium text-[#0f172a]">{{ $claim->scheduled_hours !== null ? number_format($claim->scheduled_hours, 1) : '—' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Completed / verified hours</dt><dd class="font-medium text-[#0f172a]">{{ $claim->verified_hours !== null ? number_format($claim->verified_hours, 1) : number_format($claim->total_hours ?? 0, 1) }}</dd></div>
            <div><dt class="text-[#94a3b8]">EVV status</dt><dd class="font-medium text-[#0f172a]">@php
                $evvLabel = match($claim->evv_status) {
                    'not_connected' => 'EVV verification pending (HHAeXchange not connected)',
                    'verified_local' => 'Verified locally (pending HHA sync)',
                    'pending_sync' => 'Pending HHAeXchange sync',
                    default => ucwords(str_replace('_', ' ', $claim->evv_status ?? 'pending')),
                };
            @endphp{{ $evvLabel }}</dd></div>
            <div><dt class="text-[#94a3b8]">Visit verification</dt><dd class="font-medium text-[#0f172a]">{{ ucwords(str_replace('_', ' ', $claim->visit_verification_status ?? 'pending')) }}</dd></div>
            <div><dt class="text-[#94a3b8]">Clock-in / out</dt><dd class="font-medium text-[#0f172a]">{{ ($claim->clock_in_verified ? 'In ✓' : 'In —') }} / {{ ($claim->clock_out_verified ? 'Out ✓' : 'Out —') }}</dd></div>
        </dl>
    </div>

    {{-- Billing / claim --}}
    <div>
        <h4 class="text-[13px] font-semibold text-[#334155] mb-3">Billing / claim</h4>
        @include('pages.billing-claims-audit.partials.availity-status')
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-[12px] mt-4">
            <div><dt class="text-[#94a3b8]">Invoice / claim #</dt><dd class="font-medium text-[#0f172a]">{{ $claim->invoice_number ?? $claim->claim_number }}</dd></div>
            <div><dt class="text-[#94a3b8]">Billing status</dt><dd><x-ui.pill :variant="$claim->statusBadgeVariant()" size="xs">{{ ucwords(str_replace('_', ' ', $claim->billing_status ?? $claim->claim_status)) }}</x-ui.pill></dd></div>
            <div><dt class="text-[#94a3b8]">Submitted</dt><dd class="font-medium text-[#0f172a]">{{ $claim->submitted_at?->format('M j, Y') ?? '—' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Billed / expected</dt><dd class="font-medium text-[#0f172a]">${{ number_format($claim->total_amount ?? 0, 2) }} / ${{ number_format($claim->expected_amount ?? $claim->total_amount ?? 0, 2) }}</dd></div>
            <div><dt class="text-[#94a3b8]">Audit status</dt><dd class="font-medium text-[#0f172a]">{{ ucwords(str_replace('_', ' ', $claim->audit_status ?? 'not_reviewed')) }}</dd></div>
        </dl>
    </div>

    {{-- EOB / Payment --}}
    <div>
        <h4 class="text-[13px] font-semibold text-[#334155] mb-3">EOB / payment</h4>
        <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 text-[12px] mb-4">
            <div><dt class="text-[#94a3b8]">Paid amount</dt><dd class="font-medium text-[#0f172a]">${{ number_format($claim->paid_amount ?? 0, 2) }}</dd></div>
            <div><dt class="text-[#94a3b8]">Balance</dt><dd class="font-medium text-[#0f172a]">${{ number_format($claim->balance_amount ?? max(($claim->total_amount ?? 0) - ($claim->paid_amount ?? 0), 0), 2) }}</dd></div>
            <div><dt class="text-[#94a3b8]">Payment status</dt><dd class="font-medium text-[#0f172a]">{{ $claim->payment_status ? ucwords(str_replace('_', ' ', $claim->payment_status)) : 'Pending' }}</dd></div>
            <div><dt class="text-[#94a3b8]">Payment date</dt><dd class="font-medium text-[#0f172a]">{{ $claim->payment_date?->format('M j, Y') ?? ($claim->paid_at?->format('M j, Y') ?? '—') }}</dd></div>
            <div><dt class="text-[#94a3b8]">AI extraction</dt><dd class="font-medium text-[#0f172a]">{{ ($claim->ai_extraction_status ?? 'not_connected') === 'not_connected' ? 'AI Extraction Not Connected' : ucwords(str_replace('_', ' ', $claim->ai_extraction_status)) }}</dd></div>
            @if($claim->eob_document_path)
            <div><dt class="text-[#94a3b8]">EOB document</dt><dd><a href="{{ route('billing-claims-audit.eob.download', $claim) }}" class="text-[#2563eb] font-semibold hover:underline">Download EOB</a></dd></div>
            @endif
        </dl>

        @can('update', $claim)
        <form action="{{ route('billing-claims-audit.record-eob', $claim) }}" method="POST" enctype="multipart/form-data" class="p-4 rounded-xl border border-[#e2e8f0] bg-[#f8fafc] space-y-3">
            @csrf
            <p class="text-[12px] font-semibold text-[#475569]">Record EOB / manual payment</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                <div>
                    <label class="text-[11px] text-[#64748b]">Paid amount</label>
                    <input type="number" name="paid_amount" step="0.01" min="0" value="{{ old('paid_amount', $claim->paid_amount) }}" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-lg">
                    @error('paid_amount')<p class="text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="text-[11px] text-[#64748b]">Payment date</label>
                    <input type="date" name="payment_date" value="{{ old('payment_date', $claim->payment_date?->format('Y-m-d')) }}" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-lg">
                </div>
                <div>
                    <label class="text-[11px] text-[#64748b]">Adjustment amount</label>
                    <input type="number" name="adjustment_amount" step="0.01" min="0" value="{{ old('adjustment_amount', $claim->adjustment_amount) }}" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-lg">
                </div>
                <div>
                    <label class="text-[11px] text-[#64748b]">Denial amount</label>
                    <input type="number" name="denial_amount" step="0.01" min="0" value="{{ old('denial_amount', $claim->denial_amount) }}" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-lg">
                </div>
                <div class="sm:col-span-2">
                    <label class="text-[11px] text-[#64748b]">Denial reason</label>
                    <input type="text" name="denial_reason" value="{{ old('denial_reason', $claim->rejection_reason) }}" maxlength="2000" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-lg">
                </div>
                <div>
                    <label class="text-[11px] text-[#64748b]">Payer reference / check #</label>
                    <input type="text" name="payer_reference" value="{{ old('payer_reference', $claim->payer_reference) }}" maxlength="255" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-lg">
                </div>
                <div>
                    <label class="text-[11px] text-[#64748b]">Upload EOB (PDF/image)</label>
                    <input type="file" name="eob_document" accept=".pdf,.jpg,.jpeg,.png" class="w-full text-[12px]">
                    @error('eob_document')<p class="text-[11px] text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-[#2563eb] rounded-lg">Save payment data</button>
        </form>
        @endcan
    </div>

    {{-- Override --}}
    @if($claim->billing_status === \App\Models\BillingClaimAudit::BILLING_BLOCKED)
        @can('override', $claim)
        <div>
            <h4 class="text-[13px] font-semibold text-[#334155] mb-3">Manual override</h4>
            <form action="{{ route('billing-claims-audit.override', $claim) }}" method="POST" class="space-y-3">
                @csrf
                <textarea name="override_reason" rows="3" placeholder="Reason for overriding billing block (required, min 10 characters)" maxlength="2000" class="w-full px-3 py-2 text-[13px] border border-[#e2e8f0] rounded-lg">{{ old('override_reason') }}</textarea>
                @error('override_reason')<p class="text-[11px] text-red-600">{{ $message }}</p>@enderror
                <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-[#f59e0b] rounded-lg">Override &amp; mark ready to bill</button>
            </form>
        </div>
        @endcan
    @elseif($claim->override_reason)
        <div class="p-3 rounded-xl border border-[#fde68a] bg-[#fffbeb] text-[12px]">
            <strong>Manual override applied:</strong> {{ $claim->override_reason }}
            @if($claim->overrider)
                <span class="text-[#64748b]">by {{ $claim->overrider->name }} {{ $claim->overridden_at?->format('M j, Y') }}</span>
            @endif
        </div>
    @endif

    {{-- Activity log --}}
    @if(!empty($claim->activity_log))
    <div>
        <h4 class="text-[13px] font-semibold text-[#334155] mb-3">Activity / audit history</h4>
        <ul class="space-y-2 text-[12px] text-[#475569]">
            @foreach(array_reverse($claim->activity_log) as $entry)
                <li class="flex gap-2">
                    <span class="text-[#94a3b8] shrink-0">{{ isset($entry['at']) ? \Carbon\Carbon::parse($entry['at'])->format('M j, g:i A') : '' }}</span>
                    <span><strong>{{ $entry['action'] ?? 'Update' }}</strong>@if(!empty($entry['detail'])) — {{ $entry['detail'] }}@endif</span>
                </li>
            @endforeach
        </ul>
    </div>
    @endif
</div>
