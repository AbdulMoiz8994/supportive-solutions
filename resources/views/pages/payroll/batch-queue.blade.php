@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div>
        <p class="text-[12px] font-semibold text-[#2563eb] mb-1">Financial · Payroll</p>
        <div class="flex items-center justify-between">
            <h1 class="text-[28px] font-extrabold text-[#0f172a] tracking-tight leading-tight">Payroll Approval Queue</h1>
            <a href="{{ route('payroll') }}" class="px-4 py-2 text-sm font-semibold bg-white border border-[#e2e8f0] rounded-xl text-[#475569] hover:bg-gray-50 transition">
                ← Back to Payroll
            </a>
        </div>
        <p class="text-sm text-[#64748b] mt-1">Review each batch before it is exported to AccountantsWorld. The billing-hold rule runs automatically on approval.</p>
    </div>

    @if(session('success'))
        <div x-data="{show:true}" x-show="show" class="flex items-center justify-between gap-3 rounded-xl border border-[#d1fadf] bg-[#ecfdf3] px-4 py-3 text-sm font-semibold text-[#067647]">
            <span>{{ session('success') }}</span>
            <button @click="show=false" class="text-[#067647]/60">&times;</button>
        </div>
    @endif

    @if(session('warning'))
        <div x-data="{show:true}" x-show="show" class="flex items-center justify-between gap-3 rounded-xl border border-[#fdecc8] bg-[#fff8eb] px-4 py-3 text-sm font-semibold text-[#b54708]">
            <span>{{ session('warning') }}</span>
            <button @click="show=false" class="text-[#b54708]/60">&times;</button>
        </div>
    @endif

    @forelse($batches as $batch)
        @php
            $isPending  = $batch->approval_status === 'pending_approval';
            $isApproved = $batch->approval_status === 'approved';
            $isExported = $batch->approval_status === 'exported';
            $tone  = $isPending ? 'amber' : ($isApproved ? 'blue' : 'green');
            $label = $isPending ? 'Pending approval' : ($isApproved ? 'Approved — ready to export' : 'Exported');
        @endphp

        <div class="bg-white rounded-[20px] border border-[#e6eef9] overflow-hidden">
            {{-- Batch header --}}
            <div class="px-6 py-4 border-b border-[#eef2f9] flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-3">
                    <span class="text-base font-bold text-[#0f172a]">Batch #{{ $batch->id }} — {{ \Carbon\Carbon::parse($batch->period_key.'-01')->format('F Y') }}</span>
                    <span @class([
                        'inline-flex items-center gap-1 font-semibold rounded-full border text-xs px-2.5 py-0.5',
                        'bg-[#fff8eb] text-[#b54708] border-[#fdecc8]' => $isPending,
                        'bg-[#eff4ff] text-[#2563eb] border-[#dbe6ff]' => $isApproved,
                        'bg-[#ecfdf3] text-[#067647] border-[#d1fadf]' => $isExported,
                    ])>{{ $label }}</span>
                </div>
                <div class="flex items-center gap-4 text-sm text-[#64748b]">
                    <span><span class="font-bold text-[#0f172a]">{{ $batch->record_count }}</span> caregivers</span>
                    <span><span class="font-bold text-[#0f172a]">${{ number_format($batch->total_gross, 2) }}</span> gross</span>
                    <span>Built {{ $batch->built_at?->format('M j, Y g:i A') }} by {{ $batch->builder?->name ?? 'system' }}</span>
                </div>
            </div>

            {{-- Caregiver list --}}
            <div class="px-6 py-4">
                <div class="overflow-x-auto">
                    <table class="w-full min-w-[600px] border-collapse text-sm">
                        <thead>
                            <tr class="border-b border-[#eef2f9]">
                                @foreach(['Caregiver', 'Client', 'Hours', 'Rate', 'Gross', 'Status'] as $col)
                                    <th class="py-2 px-3 text-left text-xs font-bold text-[#94a3b8] uppercase tracking-wide">{{ $col }}</th>
                                @endforeach
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-[#f1f5f9]">
                            @foreach($batch->payRecords as $pr)
                                @php
                                    $isHeld = in_array($pr->status, ['Held - review', 'Held - billing']);
                                @endphp
                                <tr :class="{{ $isHeld ? 'bg-[#fef3f2]' : '' }}" class="{{ $isHeld ? 'bg-[#fef3f2]' : 'hover:bg-[#f7faff]' }} transition-colors">
                                    <td class="py-2.5 px-3 font-semibold text-[#0f172a]">{{ $pr->employee?->first_name }} {{ $pr->employee?->last_name }}</td>
                                    <td class="py-2.5 px-3 text-[#475569]">{{ $pr->client?->first_name }} {{ $pr->client?->last_name }}</td>
                                    <td class="py-2.5 px-3 text-[#475569]">{{ $pr->hours }}</td>
                                    <td class="py-2.5 px-3 text-[#475569]">${{ number_format($pr->rate, 2) }}</td>
                                    <td class="py-2.5 px-3 font-bold text-[#0f172a]">${{ number_format($pr->gross, 2) }}</td>
                                    <td class="py-2.5 px-3">
                                        @if($isHeld)
                                            <span class="inline-flex items-center gap-1 font-semibold rounded-full border text-xs px-2.5 py-0.5 bg-[#fef3f2] text-[#d92d20] border-[#fee4e2]">Held</span>
                                            @if($pr->hold_reason)
                                                <div class="text-xs text-[#94a3b8] mt-0.5">{{ $pr->hold_reason }}</div>
                                            @endif
                                        @else
                                            <span class="inline-flex items-center gap-1 font-semibold rounded-full border text-xs px-2.5 py-0.5 bg-[#ecfdf3] text-[#067647] border-[#d1fadf]">{{ $pr->status }}</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                {{-- Billing hold rule note --}}
                @if($isPending)
                    <div class="mt-4 rounded-xl border border-[#fdecc8] bg-[#fffaf0] px-4 py-3 text-sm text-[#92400e]">
                        <span class="font-bold">Billing-hold rule:</span> When you approve this batch, any caregiver whose billing claim from last month is not yet processed will be automatically held and excluded from the export.
                    </div>
                @endif
            </div>

            {{-- Approval / export actions --}}
            <div class="px-6 py-4 border-t border-[#eef2f9] flex flex-wrap items-center justify-between gap-3">
                @if($isPending)
                    <form action="{{ route('payroll.batch.approve', $batch->id) }}" method="POST" class="flex items-start gap-3 flex-wrap w-full"
                          x-data="{ note: '' }">
                        @csrf
                        <input type="text" name="note" x-model="note"
                            placeholder="Approval note (optional — e.g. 'Reviewed, all hours confirmed')"
                            class="flex-1 min-w-[240px] px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] focus:ring-2 focus:ring-[#2563eb]/10">
                        <button type="submit"
                            class="px-5 py-2.5 rounded-xl bg-[#16a34a] text-white text-sm font-bold hover:bg-[#15803d] transition whitespace-nowrap">
                            Approve &amp; notify accountant
                        </button>
                    </form>
                @elseif($isApproved)
                    <div class="text-sm text-[#64748b]">
                        Approved {{ $batch->approved_at?->format('M j, Y') }} by {{ $batch->approver?->name ?? 'admin' }}
                        @if($batch->approval_note) — <em>{{ $batch->approval_note }}</em>@endif
                    </div>
                    <div class="flex flex-col items-end gap-2">
                        @if($batch->aw_sync_error)
                            <div class="rounded-xl border border-[#fee4e2] bg-[#fef3f2] px-4 py-2 text-sm text-[#b42318] max-w-xl text-right">
                                Last sync failed: {{ $batch->aw_sync_error }}
                            </div>
                        @endif
                        <a href="{{ route('payroll.batch.export', $batch->id) }}"
                            class="px-5 py-2.5 rounded-xl bg-[#2563eb] text-white text-sm font-bold hover:bg-[#1d4ed8] transition whitespace-nowrap">
                            Sync to AccountantsWorld
                        </a>
                    </div>
                @elseif($isExported)
                    <div class="text-sm text-[#64748b]">
                        Synced to AccountantsWorld {{ $batch->aw_synced_at?->format('M j, Y g:i A') ?? $batch->approved_at?->format('M j, Y') }}
                        @if($batch->aw_payroll_id) · AW payroll #{{ $batch->aw_payroll_id }} @endif
                        @if(data_get($batch->aw_payroll_meta, 'payrollDetailsVerified'))
                            · payroll details verified
                        @endif
                        @if(data_get($batch->aw_payroll_meta, 'payStubCount'))
                            · {{ data_get($batch->aw_payroll_meta, 'payStubCount') }} pay stub(s)
                        @endif
                    </div>
                    <div class="flex items-center gap-2">
                        <a href="{{ route('payroll.batch.export', ['batch' => $batch->id, 'format' => 'csv']) }}"
                            class="px-4 py-2 rounded-xl bg-white border border-[#e2e8f0] text-sm font-semibold text-[#475569] hover:bg-gray-50 transition">
                            Download CSV backup
                        </a>
                    </div>
                @endif
            </div>
        </div>

    @empty
        <div class="bg-white rounded-[20px] border border-[#e6eef9] px-6 py-16 text-center">
            <div class="text-sm font-semibold text-[#0f172a]">No payroll batches yet</div>
            <div class="text-sm text-[#94a3b8] mt-1">Build a batch from the <a href="{{ route('payroll') }}" class="text-[#2563eb] hover:underline">Payroll</a> page.</div>
        </div>
    @endforelse

    @include('pages.payroll.partials.accountants-world-setup-queue', [
        'awaitingAwSetup' => $awaitingAwSetup,
        'awQueueFilters' => $awQueueFilters,
    ])

    {{-- Add Caregiver to AccountantsWorld --}}
    <div class="bg-white rounded-[20px] border border-[#e6eef9] overflow-hidden">
        <div class="px-6 py-4 border-b border-[#eef2f9]">
            <h3 class="text-base font-bold text-[#0f172a]">Add Caregiver to AccountantsWorld</h3>
            <p class="text-sm text-[#64748b] mt-0.5">Create a new employee in AccountantsWorld directly from this portal — no need to open the AW site. Failed attempts appear in the setup queue above with a retry option.</p>
        </div>
        <form action="{{ route('payroll.aw.create-employee') }}" method="POST" class="px-6 py-5">
            @csrf
            <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                <div>
                    <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Caregiver</label>
                    <select name="employee_id" required
                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] bg-white">
                        <option value="">Select caregiver</option>
                        @foreach(($awEligibleEmployees ?? collect()) as $emp)
                            <option value="{{ $emp->id }}">
                                {{ $emp->first_name }} {{ $emp->last_name }}
                                @if($emp->isAwaitingAccountantsWorldSetup()) (retry setup) @endif
                            </option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Pay rate ($/hr)</label>
                    <input type="number" name="aw_pay_rate" step="0.01" required placeholder="e.g. 14.50"
                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Pay type</label>
                    <select name="aw_pay_type" required
                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] bg-white">
                        <option value="hourly">Hourly</option>
                        <option value="salary">Salary</option>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">First name (AW)</label>
                    <input type="text" name="aw_first_name" required placeholder="As it should appear in AW"
                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Last name (AW)</label>
                    <input type="text" name="aw_last_name" required
                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb]">
                </div>
                <div>
                    <label class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">SSN (9 digits, no dashes)</label>
                    <input type="text" name="aw_ssn" required placeholder="123456789" maxlength="9" pattern="[0-9]{9}"
                        class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm font-mono outline-none focus:border-[#2563eb]">
                </div>
            </div>
            <div class="mt-4 flex justify-end">
                <button type="submit"
                    class="px-6 py-2.5 rounded-xl bg-[#2563eb] text-white text-sm font-bold hover:bg-[#1d4ed8] transition">
                    Add to AccountantsWorld
                </button>
            </div>
        </form>
    </div>
</div>
@endsection
