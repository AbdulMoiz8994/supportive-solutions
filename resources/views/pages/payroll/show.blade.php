@extends('layouts.app')

@section('content')
@php
    $emp = $record->employee;
    $graceDays = $graceDaysRemaining;
    $monthLabel = explode(' ', (string) $periodLabel)[0] ?? $periodLabel;
@endphp
<div class="space-y-6" x-data="{ showWageModal: false, wage: '{{ number_format((float)($record->rate ?? $emp?->hourly_wage ?? 15), 2, '.', '') }}' }">
    {{-- Breadcrumb & header --}}
    <div>
        <nav class="text-[12px] text-[#64748b] mb-2">
            <a href="{{ route('payroll', ['period' => $record->period_key]) }}" class="text-[#2563eb] hover:underline">Payroll</a>
            <span class="mx-1">›</span>
            <span>{{ $periodLabel }}</span>
        </nav>
        <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap items-center gap-3 min-w-0">
                <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight">{{ $emp?->name }} — {{ $monthLabel }} pay</h1>
                @include('pages.payroll.partials.status-badge', ['status' => $record->status, 'daysRemaining' => $graceDays])
            </div>
            <div class="flex items-center justify-end gap-2 shrink-0 flex-nowrap">
                @can('downloadStub', $record)
                    @if($stubAvailable)
                        <a href="{{ route('payroll.stub', $record) }}"
                           class="inline-flex items-center justify-center h-9 px-4 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] whitespace-nowrap">
                            Pay Stub PDF
                        </a>
                    @else
                        <span class="inline-flex items-center justify-center h-9 px-4 text-[12px] font-semibold text-[#94a3b8] bg-[#f8fafc] border border-[#e2e8f0] rounded-xl cursor-default whitespace-nowrap">
                            No pay stub stored yet
                        </span>
                    @endif
                @endcan
                @if(config('payroll.accountants_world_url'))
                    <a href="{{ config('payroll.accountants_world_url') }}" target="_blank" rel="noopener"
                       class="inline-flex items-center justify-center h-9 px-4 text-[12px] font-semibold text-[#475569] bg-white border border-[#e2e8f0] rounded-xl hover:bg-[#f8fafc] whitespace-nowrap">
                        Open in AccountantsWorld
                    </a>
                @endif
            </div>
        </div>
        @if($headerSubtitle)
            <p class="text-[13px] text-[#64748b] mt-1.5">{{ $headerSubtitle }}</p>
        @endif
    </div>

    @if(session('success'))
        <x-ui.alert variant="success">{{ session('success') }}</x-ui.alert>
    @endif

    <div class="grid grid-cols-1 xl:grid-cols-3 gap-6">
        {{-- Pay calculation --}}
        <div class="xl:col-span-2 space-y-4">
            <div class="bg-white rounded-2xl border border-[#e6eef9] p-6 shadow-sm">
                <div class="flex flex-wrap items-center gap-2 mb-4">
                    <h2 class="text-[16px] font-bold text-[#0f172a]">Pay calculation — {{ $periodLabel }}</h2>
                    <span class="px-2.5 py-0.5 text-[10px] font-bold uppercase tracking-wide text-[#64748b] bg-[#f8fafc] border border-[#e2e8f0] rounded-full">W-2 · AccountantsWorld</span>
                </div>
                <p class="text-[13px] text-[#475569] mb-4">
                    <span class="font-semibold text-[#0f172a]">{{ $emp?->name }}</span>
                    · Pay period {{ $monthLabel }} 1 — {{ $monthLabel }} 31, {{ \Carbon\Carbon::createFromFormat('Y-m', $record->period_key)->format('Y') }}
                    @if(isset($batchDates['pay_date']))
                        · paid in the {{ $batchDates['pay_date']->format('M j') }} batch
                    @endif
                </p>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-6 gap-y-2 mb-5 text-[12px] text-[#64748b]">
                    <div><span class="font-semibold">Employment type:</span> W-2 employee</div>
                    <div><span class="font-semibold">Caregiver type:</span> {{ $caregiverType }}</div>
                    <div><span class="font-semibold">Live-in status:</span> {{ $emp?->live_in ? 'Yes — EVV-exempt (hours from compliance form)' : 'No — EVV required' }}</div>
                    @if($record->client)
                        <div><span class="font-semibold">Client served:</span> {{ $record->client->first_name }} {{ $record->client->last_name }}@if($record->program_tag) · {{ $record->program_tag }}@endif</div>
                    @endif
                    <div class="sm:col-span-2"><span class="font-semibold">Hours source:</span> {{ $hoursSource !== '—' ? $hoursSource : $periodLabel.' compliance form (verified)' }}</div>
                </dl>
                <table class="w-full text-[13px]">
                    <thead>
                        <tr class="text-[10px] font-black text-[#94a3b8] uppercase border-b border-[#f1f5f9]">
                            <th class="py-2 text-left">Earnings</th>
                            <th class="py-2 text-left">Hours</th>
                            <th class="py-2 text-left">Wage</th>
                            <th class="py-2 text-right">Amount</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="border-b border-[#f1f5f9]">
                            <td class="py-3 text-[#0f172a] font-medium">Personal care regular</td>
                            <td class="py-3 text-[#475569]">{{ $record->hours !== null ? number_format($record->hours, 1) : '—' }}</td>
                            <td class="py-3 text-[#475569]">${{ number_format((float)($record->rate ?? 0), 2) }}/hr</td>
                            <td class="py-3 text-right font-bold text-[#0f172a]">{{ $record->gross !== null ? '$'.number_format($record->gross, 2) : '—' }}</td>
                        </tr>
                    </tbody>
                </table>
                <div class="mt-4 pt-4 border-t border-[#f1f5f9] flex flex-wrap gap-6 text-[13px]">
                    <div><span class="text-[#64748b]">Verified hours</span> <strong class="text-[#0f172a]">{{ $record->hours !== null ? number_format($record->hours, 1) : '—' }}</strong></div>
                    <div><span class="text-[#64748b]">Gross pay</span> <strong class="text-[#0f172a]">{{ $record->gross !== null ? '$'.number_format($record->gross, 2) : '—' }}</strong></div>
                </div>
                <p class="text-[11px] text-[#94a3b8] mt-4">Hourly wage is independent of billing rates. Taxes and withholdings are calculated in AccountantsWorld.</p>
            </div>
        </div>

        {{-- Sidebar cards --}}
        <div class="space-y-4">
            {{-- Wage & pay --}}
            <div class="bg-white rounded-2xl border border-[#e6eef9] p-5 shadow-sm">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-[14px] font-bold text-[#0f172a]">Wage &amp; pay</h3>
                    @can('updateWage', $record)
                        <button type="button" @click="showWageModal = true" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Edit</button>
                    @endcan
                </div>
                <dl class="space-y-3 text-[13px]">
                    <div class="flex justify-between"><dt class="text-[#64748b]">Hourly wage</dt><dd class="font-semibold text-[#0f172a]">${{ number_format((float)($record->rate ?? $emp?->hourly_wage ?? 0), 2) }} / hr</dd></div>
                    <div class="flex justify-between"><dt class="text-[#64748b]">Verified hours</dt><dd class="font-semibold text-[#0f172a]">{{ $record->hours !== null ? number_format($record->hours, 1) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#64748b]">Gross pay</dt><dd class="font-semibold text-[#0f172a]">{{ $record->gross !== null ? '$'.number_format($record->gross, 2) : '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-[#64748b]">Pay method</dt><dd class="font-semibold text-[#0f172a]">{{ $emp?->direct_deposit_last4 ? 'Direct deposit ••'.$emp->direct_deposit_last4 : '—' }}</dd></div>
                </dl>
            </div>

            {{-- Grace window --}}
            <div class="bg-[#fffbeb] rounded-2xl border border-[#fde68a] p-5">
                <h3 class="text-[14px] font-bold text-[#92400e] mb-3">Pay grace window</h3>
                @if($complianceForm?->submitted_at)
                    <dl class="space-y-2 text-[12px] text-[#b45309]">
                        <div><span class="font-semibold">Compliance form received</span> {{ $complianceForm->submitted_at->format('M j, Y') }}</div>
                        <div class="inline-flex items-center px-2 py-1 rounded-lg bg-[#ecfdf5] border border-[#a7f3d0] text-[#047857] font-semibold">
                            Grace window (+{{ config('payroll.grace_days') }} days)
                        </div>
                        @if($graceEndDate)
                            <div><span class="font-semibold">Clears:</span> {{ \Carbon\Carbon::parse($graceEndDate)->format('M j, Y') }}</div>
                        @endif
                        @if($graceDays !== null && $record->status === \App\Models\PayRecord::STATUS_IN_GRACE)
                            <div class="font-bold text-orange-700">{{ $graceDays }} day(s) remaining</div>
                        @endif
                    </dl>
                    <p class="text-[11px] text-[#b45309] mt-3">Anti-fraud hold between compliance form receipt and payout.</p>
                @else
                    <p class="text-[12px] text-[#b45309]">Awaiting compliance form submission.</p>
                @endif
            </div>

            {{-- Eligibility --}}
            <div class="bg-white rounded-2xl border border-[#e6eef9] p-5 shadow-sm">
                <h3 class="text-[14px] font-bold text-[#0f172a] mb-3">Pay eligibility</h3>
                <dl class="space-y-2 text-[12px] text-[#64748b]">
                    <div><span class="font-semibold">DHS/MICH case start:</span> {{ $caseStart?->format('M j, Y') ?? '—' }}</div>
                    <div><span class="font-semibold">CHAMPS Association Date:</span> {{ $champsDate?->format('M j, Y') ?? '—' }}</div>
                    @if($eligibleFrom)
                        <div class="mt-2 px-3 py-2 rounded-lg bg-[#eff6ff] border border-[#bfdbfe] text-[#1e40af] font-semibold">
                            Eligible from {{ $eligibleFrom->format('M j, Y') }}
                        </div>
                    @endif
                </dl>
            </div>

            {{-- Lifecycle --}}
            @include('pages.payroll.partials.lifecycle-timeline', ['lifecycle' => $lifecycle])

            @include('pages.payroll.partials.claim-status', ['record' => $record, 'payrollClaim' => $payrollClaim ?? null])

            {{-- Documents --}}
            <div class="bg-white rounded-2xl border border-[#e6eef9] p-5 shadow-sm">
                <h3 class="text-[14px] font-bold text-[#0f172a] mb-3">Documents</h3>
                @if(count($documents))
                    <ul class="space-y-3">
                        @foreach($documents as $doc)
                            <li class="flex items-center justify-between gap-3">
                                <span class="text-[12px] font-semibold text-[#0f172a]">{{ $doc['label'] }}</span>
                                @if(($doc['type'] ?? '') === 'stub')
                                    @can('downloadStub', $record)
                                        @if($doc['available'] && ! empty($doc['route']))
                                            <a href="{{ $doc['route'] }}" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Open ›</a>
                                        @else
                                            <span class="text-[11px] font-medium text-[#94a3b8]">{{ $doc['status'] ?? 'No pay stub stored yet' }}</span>
                                        @endif
                                    @endcan
                                @elseif($doc['available'] ?? false)
                                    <a href="#" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Open ›</a>
                                @else
                                    <span class="text-[11px] font-medium text-[#94a3b8]">{{ $doc['status'] ?? 'On file' }}</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @else
                    <p class="text-[12px] text-[#94a3b8]">No documents on file for this pay period.</p>
                @endif
            </div>

            @if($record->hold_reason)
                <div class="bg-red-50 rounded-2xl border border-red-200 p-5">
                    <h3 class="text-[14px] font-bold text-red-700 mb-2">Hold — review required</h3>
                    <p class="text-[12px] text-red-600">{{ e($record->hold_reason) }}</p>
                    @can('releaseHold', $record)
                        <form action="{{ route('payroll.release-hold', $record) }}" method="POST" class="mt-3">
                            @csrf
                            <button type="submit" class="text-[12px] font-semibold text-[#2563eb] hover:underline">Release hold</button>
                        </form>
                    @endcan
                </div>
            @endif
        </div>
    </div>

    {{-- Wage edit modal --}}
    @can('updateWage', $record)
    <div x-show="showWageModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40">
        <div @click.away="showWageModal = false" class="bg-white rounded-2xl border border-[#e6eef9] p-6 w-full max-w-md shadow-xl">
            <h3 class="text-[16px] font-bold text-[#0f172a] mb-4">Edit hourly wage</h3>
            <form action="{{ route('payroll.update-wage', $record) }}" method="POST">
                @csrf
                @method('PATCH')
                <label class="block text-[12px] font-semibold text-[#64748b] mb-1">Hourly wage ($/hr)</label>
                <input type="number" name="hourly_wage" step="0.01" min="1" max="999.99" x-model="wage" required
                       class="w-full px-3.5 py-2 text-[13px] border border-[#e2e8f0] rounded-xl mb-4 focus:ring-2 focus:ring-[#2563eb]/20 focus:border-[#2563eb] outline-none">
                <div class="flex justify-end gap-2">
                    <button type="button" @click="showWageModal = false" class="px-4 py-2 text-[12px] font-semibold text-[#64748b] rounded-xl border border-[#e2e8f0]">Cancel</button>
                    <button type="submit" class="px-4 py-2 text-[12px] font-semibold text-white bg-[#2563eb] rounded-xl hover:bg-[#1d4ed8]">Save</button>
                </div>
            </form>
        </div>
    </div>
    @endcan
</div>
@endsection
