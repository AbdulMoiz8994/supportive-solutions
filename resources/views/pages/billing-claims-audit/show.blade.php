@extends('layouts.app')

@section('content')
<div class="space-y-6" x-data="{ editingRate: false, rate: {{ $claim->hourly_rate }} }">
    <div>
        <nav class="text-[12px] text-[#2563eb] font-medium mb-2">
            <a href="{{ route('billing-claims-audit.index', ['period' => $claim->billing_period->format('Y-m')]) }}" class="hover:underline">Billing & Claims</a>
            <span class="text-[#94a3b8] mx-1">&gt;</span>
            <span class="text-[#64748b]">{{ $periodLabel }}</span>
        </nav>
        <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div>
                <div class="flex flex-wrap items-center gap-3">
                    <h1 class="text-[26px] font-extrabold text-[#0f172a] tracking-tight">
                        {{ $claim->client?->first_name }} {{ $claim->client?->last_name }} — {{ $claim->isMich() ? 'MICH claim' : 'DHS Home Help' }}
                    </h1>
                    <x-ui.pill :variant="$claim->statusBadgeVariant()">{{ $claim->statusLabel() }}</x-ui.pill>
                </div>
                <p class="text-[13px] text-[#64748b] mt-2">
                    {{ $claim->isDhs() ? 'Invoice' : 'Claim' }} #{{ $claim->claim_number }}
                    @if($claim->health_plan_name)
                        · {{ $claim->isDhs() && $claim->authorizing_worker_name ? 'ASW: '.Str::after($claim->authorizing_worker_name, 'ASW ') : $claim->health_plan_name }}
                    @endif
                    @if($claim->employee)
                        · caregiver {{ $claim->employee->first_name }} {{ $claim->employee->last_name }}
                        @if($claim->caregiver_relationship)
                            ({{ $claim->caregiver_relationship }})
                        @endif
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap gap-2">
                @if($claim->pdf_path)
                    <a href="{{ route('billing-claims-audit.documents.download', [$claim, 0]) }}"
                       class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc]">
                        Download PDF
                    </a>
                @endif
                @if($claim->claim_status === \App\Models\BillingClaimAudit::STATUS_REJECTED)
                    <span class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl">Re-submit</span>
                @endif
                @if($claim->isDhs())
                    @can('update', $claim)
                        @if(!$claim->submitted_at)
                            <form action="{{ route('billing-claims-audit.submit', $claim) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8] transition">
                                    Submit to ASW
                                </button>
                            </form>
                        @else
                            <form action="{{ route('billing-claims-audit.submit', $claim) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition">
                                    Re-send to ASW
                                </button>
                            </form>
                        @endif
                    @endcan
                    <a href="{{ route('billing-claims-audit.sigma-portal', $claim) }}" target="_blank" rel="noopener"
                       class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] transition">
                        Open Sigma Portal
                    </a>
                @else
                    @can('update', $claim)
                        @if($claim->usesAvaility() && !$claim->submitted_at)
                            <form action="{{ route('billing-claims-audit.submit', $claim) }}" method="POST" class="inline">
                                @csrf
                                <button type="submit" class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8] transition">
                                    Submit to Availity
                                </button>
                            </form>
                        @endif
                    @endcan
                    <span class="inline-flex items-center px-4 py-2 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl">View EOB</span>
                @endif
            </div>
        </div>
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif
    @if(session('warning'))
        <x-ui.alert variant="warning">{{ session('warning') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        <div class="xl:col-span-2 space-y-6">
            @if($claim->isMich())
                @include('pages.billing-claims-audit.partials.mich-document')
            @else
                @include('pages.billing-claims-audit.partials.dhs-document')
            @endif
            @if($claim->isDhs())
                @include('pages.billing-claims-audit.partials.mich-dhs-info')
            @endif
            @include('pages.billing-claims-audit.partials.workflow-sections')
        </div>

        <div class="space-y-4">
            <div class="bg-white rounded-2xl border border-[#e6eef9] p-5 shadow-sm">
                <h3 class="text-[14px] font-bold text-[#0f172a] mb-4">Billing rate</h3>
                <div class="flex flex-wrap gap-2 mb-4">
                    <x-ui.pill variant="blue">{{ $claim->program_type }}</x-ui.pill>
                    @if($claim->healthPlanShortName())
                        <x-ui.pill variant="blue">{{ $claim->healthPlanShortName() }}</x-ui.pill>
                    @endif
                </div>

                @can('update', $claim)
                <div x-show="!editingRate">
                    <div class="flex items-center justify-between mb-3">
                        <span class="text-[12px] text-[#64748b]">{{ $claim->isMich() ? 'Contracted rate' : 'Standard rate' }}</span>
                        <button type="button" @click="editingRate = true" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Edit</button>
                    </div>
                    <p class="text-[22px] font-extrabold text-[#0f172a]">${{ number_format($claim->hourly_rate, 2) }} / hr</p>
                </div>
                <form x-show="editingRate" x-cloak action="{{ route('billing-claims-audit.update-rate', $claim) }}" method="POST" class="space-y-3">
                    @csrf
                    @method('PATCH')
                    <label class="text-[12px] text-[#64748b]">{{ $claim->isMich() ? 'Contracted rate' : 'Standard rate' }}</label>
                    <input type="number" name="hourly_rate" step="0.01" min="0" max="9999.99" x-model="rate"
                           class="w-full px-3 py-2 border border-[#e2e8f0] rounded-xl text-[14px]">
                    @error('hourly_rate')<p class="text-[12px] text-red-600">{{ $message }}</p>@enderror
                    <div class="flex gap-2">
                        <button type="submit" class="px-3 py-1.5 text-[12px] font-semibold text-white bg-[#2563eb] rounded-lg">Save</button>
                        <button type="button" @click="editingRate = false" class="px-3 py-1.5 text-[12px] font-semibold text-[#64748b]">Cancel</button>
                    </div>
                </form>
                @else
                <p class="text-[22px] font-extrabold text-[#0f172a]">${{ number_format($claim->hourly_rate, 2) }} / hr</p>
                @endcan

                <dl class="mt-4 space-y-2 text-[13px]">
                    <div class="flex justify-between"><dt class="text-[#64748b]">{{ $claim->isMich() ? 'Units/hours' : 'Verified hours' }}</dt><dd class="font-semibold text-[#0f172a]">{{ number_format($claim->total_hours, 1) }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#64748b]">Billed amount</dt><dd class="font-semibold text-[#0f172a]">${{ number_format($claim->total_amount, 2) }}</dd></div>
                </dl>
                <p class="text-[11px] text-[#94a3b8] mt-4 leading-relaxed">
                    {{ $claim->isMich()
                        ? 'Rate is editable per program; editing recomputes the amount instantly.'
                        : 'DHS auto-sets $27/hr; editable here for future rate increases. Caregiver wage is separate (Payroll).' }}
                </p>
            </div>

            <div class="bg-white rounded-2xl border border-[#e6eef9] p-5 shadow-sm">
                <h3 class="text-[14px] font-bold text-[#0f172a] mb-4">{{ $claim->isMich() ? 'Payment lifecycle' : 'Submission & payment lifecycle' }}</h3>
                <ol class="space-y-4">
                    @foreach($claim->lifecycle_events ?? [] as $event)
                        @php
                            $dotColor = match($event['status'] ?? 'pending') {
                                'completed' => 'bg-[#10b981]',
                                'current' => 'bg-[#2563eb]',
                                default => 'bg-[#cbd5e1]',
                            };
                        @endphp
                        <li class="flex gap-3">
                            <span class="mt-1.5 w-2.5 h-2.5 rounded-full {{ $dotColor }} shrink-0"></span>
                            <div>
                                <p class="text-[13px] font-medium text-[#0f172a]">{{ $event['title'] ?? '' }}</p>
                                @if(!empty($event['date']))
                                    <p class="text-[11px] text-[#94a3b8]">{{ $event['date'] }}@if(!empty($event['detail'])) · {{ $event['detail'] }}@endif</p>
                                @elseif(!empty($event['detail']))
                                    <p class="text-[11px] text-[#94a3b8]">{{ $event['detail'] }}</p>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ol>
            </div>

            <div class="bg-white rounded-2xl border border-[#e6eef9] p-5 shadow-sm">
                <h3 class="text-[14px] font-bold text-[#0f172a] mb-4">Documents (7-yr record)</h3>
                <ul class="space-y-3">
                    @foreach($claim->documents ?? [] as $index => $doc)
                        <li class="flex items-center justify-between gap-2">
                            <span class="text-[13px] text-[#475569]">{{ $doc['name'] ?? 'Document' }}</span>
                            <div class="flex items-center gap-2">
                                @if(($doc['status'] ?? '') === 'pending')
                                    <x-ui.pill variant="gray" size="xs">pending</x-ui.pill>
                                @endif
                                @if(!empty($doc['path']))
                                    <a href="{{ route('billing-claims-audit.documents.download', [$claim, $index]) }}" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Open &gt;</a>
                                @endif
                            </div>
                        </li>
                    @endforeach
                </ul>
            </div>
        </div>
    </div>
</div>
@endsection
