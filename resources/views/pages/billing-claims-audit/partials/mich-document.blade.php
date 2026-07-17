<div class="bg-white rounded-2xl border border-[#e6eef9] shadow-sm overflow-hidden">
    <div class="flex items-center justify-between px-5 py-4 border-b border-[#e6eef9] bg-[#f8fafc]">
        <h2 class="text-[15px] font-bold text-[#0f172a]">EDI 837P — Professional Claim (rendered)</h2>
        <x-ui.pill variant="gray" size="xs">Stored 7 yrs · v1</x-ui.pill>
    </div>
    <div class="p-6 space-y-6">
        <div>
            <p class="text-[14px] font-bold text-[#0f172a]">Supportive Solutions Home Care</p>
            <p class="text-[12px] text-[#64748b]">837P Professional Claim · submitted via Availity · {{ $claim->submitted_at?->format('M j, Y') ?? $claim->period_end->format('M j, Y') }}</p>
        </div>

        <dl class="grid grid-cols-1 md:grid-cols-2 gap-x-8 gap-y-4 text-[13px]">
            <div><dt class="text-[#94a3b8] text-[11px] uppercase tracking-wide mb-0.5">Member</dt><dd class="font-semibold text-[#0f172a]">{{ $claim->client?->first_name }} {{ $claim->client?->last_name }}</dd></div>
            <div><dt class="text-[#94a3b8] text-[11px] uppercase tracking-wide mb-0.5">Medicaid ID</dt><dd class="font-semibold text-[#0f172a]">{{ $claim->maskedMedicaidId() }}</dd></div>
            <div><dt class="text-[#94a3b8] text-[11px] uppercase tracking-wide mb-0.5">Health plan</dt><dd class="font-semibold text-[#0f172a]">{{ $claim->health_plan_name }} ({{ $claim->program_type }})</dd></div>
            <div><dt class="text-[#94a3b8] text-[11px] uppercase tracking-wide mb-0.5">Plan member ID</dt><dd class="font-semibold text-[#0f172a]">{{ $claim->plan_member_id }}</dd></div>
            <div><dt class="text-[#94a3b8] text-[11px] uppercase tracking-wide mb-0.5">Authorization (PA)</dt><dd class="font-semibold text-[#0f172a]">{{ $claim->authorization_number }}@if($claim->authorization_valid_through) - valid thru {{ $claim->authorization_valid_through->format('M j Y') }}@endif</dd></div>
            <div><dt class="text-[#94a3b8] text-[11px] uppercase tracking-wide mb-0.5">Rendering caregiver</dt><dd class="font-semibold text-[#0f172a]">{{ $claim->employee?->first_name }} {{ $claim->employee?->last_name }}@if($claim->caregiver_relationship) - {{ $claim->caregiver_relationship }}@endif @if($claim->evv_exempt)(EVV-exempt)@endif</dd></div>
            <div class="md:col-span-2"><dt class="text-[#94a3b8] text-[11px] uppercase tracking-wide mb-0.5">Service period</dt><dd class="font-semibold text-[#0f172a]">{{ $claim->period_start->format('M j') }} – {{ $claim->period_end->format('M j, Y') }}</dd></div>
        </dl>

        <div class="overflow-x-auto border border-[#e6eef9] rounded-xl">
            <table class="w-full text-left text-[13px]">
                <thead class="bg-[#f8fafc] border-b border-[#e6eef9]">
                    <tr>
                        <th class="px-4 py-2.5 font-semibold text-[#64748b]">Code</th>
                        <th class="px-4 py-2.5 font-semibold text-[#64748b]">Service</th>
                        <th class="px-4 py-2.5 font-semibold text-[#64748b]">Units</th>
                        <th class="px-4 py-2.5 font-semibold text-[#64748b]">Hours</th>
                        <th class="px-4 py-2.5 font-semibold text-[#64748b]">Rate</th>
                        <th class="px-4 py-2.5 font-semibold text-[#64748b] text-right">Total</th>
                    </tr>
                </thead>
                <tbody>
                    <tr class="border-b border-[#f1f5f9]">
                        <td class="px-4 py-3 font-medium">{{ $claim->service_code }}</td>
                        <td class="px-4 py-3">{{ $claim->service_description }}</td>
                        <td class="px-4 py-3">{{ $claim->units }}</td>
                        <td class="px-4 py-3">{{ number_format($claim->total_hours, 1) }}</td>
                        <td class="px-4 py-3">${{ number_format($claim->hourly_rate, 2) }}/hr</td>
                        <td class="px-4 py-3 text-right font-semibold">${{ number_format($claim->total_amount, 2) }}</td>
                    </tr>
                </tbody>
                <tfoot class="bg-[#f8fafc]">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-[#64748b]">Billed hours: <strong class="text-[#0f172a]">{{ number_format($claim->total_hours, 1) }}</strong></td>
                        <td colspan="3" class="px-4 py-3 text-right text-[#64748b]">Total billed: <strong class="text-[#0f172a]">${{ number_format($claim->total_amount, 2) }}</strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>
