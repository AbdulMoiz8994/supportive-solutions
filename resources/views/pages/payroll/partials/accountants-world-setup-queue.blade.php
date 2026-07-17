<div class="bg-white rounded-[20px] border border-[#fdecc8] overflow-hidden">
    <div class="px-6 py-4 border-b border-[#fdecc8] bg-[#fffaf0] flex flex-wrap items-start justify-between gap-3">
        <div>
            <h3 class="text-base font-bold text-[#0f172a]">AccountantsWorld setup queue</h3>
            <p class="text-sm text-[#92400e] mt-0.5">Caregivers that could not be created or verified via the API. Each row shows the live error from AccountantsWorld for that attempt.</p>
        </div>
        <span class="inline-flex items-center gap-1 font-semibold rounded-full border text-xs px-2.5 py-0.5 bg-[#fff8eb] text-[#b54708] border-[#fdecc8]">
            {{ $awaitingAwSetup->total() }} pending
        </span>
    </div>

    <form method="GET" action="{{ route('payroll.batch-queue') }}" class="px-6 py-4 border-b border-[#eef2f9] bg-[#fafcff]">
        <div class="grid grid-cols-1 md:grid-cols-4 gap-3 items-end">
            <div class="md:col-span-2">
                <label for="aw_search" class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Search caregiver</label>
                <input type="text" id="aw_search" name="aw_search" value="{{ $awQueueFilters['search'] ?? '' }}"
                    placeholder="Name…"
                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] bg-white">
            </div>
            <div>
                <label for="aw_context" class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Failure type</label>
                <select id="aw_context" name="aw_context"
                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] bg-white">
                    @foreach(['all' => 'All failures', 'create' => 'Create failed', 'verify' => 'Verify failed', 'legacy' => 'Needs recheck'] as $value => $label)
                        <option value="{{ $value }}" @selected(($awQueueFilters['context'] ?? 'all') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label for="aw_sort" class="block text-xs font-bold text-[#64748b] uppercase tracking-wide mb-1.5">Sort by</label>
                <select id="aw_sort" name="aw_sort"
                    class="w-full px-3.5 py-2.5 rounded-[9px] border border-[#e2e8f0] text-sm outline-none focus:border-[#2563eb] bg-white">
                    @foreach(['recent' => 'Most recent attempt', 'oldest' => 'Oldest attempt', 'name' => 'Caregiver name'] as $value => $label)
                        <option value="{{ $value }}" @selected(($awQueueFilters['sort'] ?? 'recent') === $value)>{{ $label }}</option>
                    @endforeach
                </select>
            </div>
        </div>
        <div class="mt-3 flex flex-wrap items-center gap-2">
            <button type="submit"
                class="px-4 py-2 rounded-xl bg-[#2563eb] text-white text-sm font-bold hover:bg-[#1d4ed8] transition">
                Apply filters
            </button>
            @if(($awQueueFilters['search'] ?? '') || ($awQueueFilters['context'] ?? 'all') !== 'all' || ($awQueueFilters['sort'] ?? 'recent') !== 'recent')
                <a href="{{ route('payroll.batch-queue') }}"
                    class="px-4 py-2 rounded-xl bg-white border border-[#e2e8f0] text-sm font-semibold text-[#475569] hover:bg-gray-50 transition">
                    Clear filters
                </a>
            @endif
        </div>
    </form>

    @if($awaitingAwSetup->total() > 0)
        <div class="overflow-x-auto">
            <table class="w-full min-w-[860px] border-collapse text-sm">
                <thead>
                    <tr class="border-b border-[#eef2f9]">
                        @foreach(['Caregiver', 'Type', 'Last attempt', 'Error', 'Actions'] as $col)
                            <th class="py-3 px-4 text-left text-xs font-bold text-[#94a3b8] uppercase tracking-wide">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f5f9]">
                    @foreach($awaitingAwSetup as $emp)
                        <tr class="hover:bg-[#f7faff] transition-colors align-top">
                            <td class="py-3 px-4">
                                <a href="{{ route('caregivers.show', $emp->id) }}" class="font-semibold text-[#2563eb] hover:underline">
                                    {{ $emp->first_name }} {{ $emp->last_name }}
                                </a>
                                @if($emp->hourly_wage)
                                    <div class="text-xs text-[#94a3b8] mt-0.5">${{ number_format((float) $emp->hourly_wage, 2) }}/hr on file</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 whitespace-nowrap">
                                <span @class([
                                    'inline-flex items-center font-semibold rounded-full border text-xs px-2.5 py-0.5',
                                    'bg-[#fef3f2] text-[#d92d20] border-[#fee4e2]' => $emp->aw_setup_error_context === 'verify',
                                    'bg-[#fff8eb] text-[#b54708] border-[#fdecc8]' => $emp->aw_setup_error_context === 'create' || ! $emp->aw_setup_error_context,
                                    'bg-[#f8fafc] text-[#64748b] border-[#e2e8f0]' => $emp->aw_setup_error_context === 'legacy',
                                ])>{{ $emp->aw_setup_context_label }}</span>
                            </td>
                            <td class="py-3 px-4 text-[#475569] whitespace-nowrap">
                                {{ $emp->aw_setup_attempted_at?->format('M j, Y g:i A') ?? '—' }}
                                @if($emp->aw_setup_attempted_at)
                                    <div class="text-xs text-[#94a3b8]">{{ $emp->aw_setup_attempted_at->diffForHumans() }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4 text-[#475569] max-w-md">
                                <p class="leading-relaxed">{{ $emp->aw_setup_error_display }}</p>
                                @if($emp->aw_setup_http_status)
                                    <div class="text-xs text-[#94a3b8] mt-1">HTTP {{ $emp->aw_setup_http_status }}</div>
                                @endif
                            </td>
                            <td class="py-3 px-4">
                                <div class="flex flex-col gap-2 min-w-[300px]">
                                    <div class="flex flex-wrap items-center gap-1.5">
                                        <form action="{{ route('payroll.aw.resolve-employee', $emp) }}" method="POST" class="flex flex-wrap items-center gap-1.5">
                                            @csrf
                                            <input type="text" name="aw_employee_id" placeholder="AW ID (optional)"
                                                title="Used for verification by ID. If blank, saved SSN from the setup form is used."
                                                class="w-32 px-2 py-1.5 rounded-lg border border-[#e2e8f0] text-xs outline-none focus:border-[#2563eb]">
                                            @if($emp->aw_setup_payload)
                                                <button type="submit" formaction="{{ route('payroll.aw.retry-employee', $emp) }}" formmethod="POST"
                                                    class="px-3 py-1.5 rounded-lg bg-[#2563eb] text-white text-xs font-bold hover:bg-[#1d4ed8] transition whitespace-nowrap">
                                                    Retry
                                                </button>
                                            @endif
                                            <button type="submit" name="verify" value="1"
                                                class="px-3 py-1.5 rounded-lg bg-[#16a34a] text-white text-xs font-bold hover:bg-[#15803d] transition whitespace-nowrap">
                                                Verify &amp; mark synced
                                            </button>
                                            <button type="submit" name="verify" value="0"
                                                onclick="return confirm('Mark as synced without verifying in AccountantsWorld? Only use this if you already added them in the AW portal.')"
                                                class="px-3 py-1.5 rounded-lg bg-white border border-[#e2e8f0] text-xs font-semibold text-[#475569] hover:bg-gray-50 transition whitespace-nowrap">
                                                Skip verify
                                            </button>
                                        </form>
                                    </div>
                                    @unless($emp->aw_setup_payload)
                                        <span class="text-xs text-[#94a3b8] italic">No saved setup data — re-submit the form below to enable Retry.</span>
                                    @endunless
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="px-6 py-4 border-t border-[#eef2f9] flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <p class="text-sm text-[#64748b]">
                Showing {{ $awaitingAwSetup->firstItem() ?? 0 }}–{{ $awaitingAwSetup->lastItem() ?? 0 }} of {{ $awaitingAwSetup->total() }} pending setup{{ $awaitingAwSetup->total() === 1 ? '' : 's' }}
            </p>
            @if($awaitingAwSetup->hasPages())
                <div>{{ $awaitingAwSetup->links() }}</div>
            @endif
        </div>
    @else
        <div class="px-6 py-12 text-center">
            <div class="text-sm font-semibold text-[#0f172a]">No pending AccountantsWorld setups</div>
            <div class="text-sm text-[#94a3b8] mt-1">
                @if(($awQueueFilters['search'] ?? '') || ($awQueueFilters['context'] ?? 'all') !== 'all')
                    Try clearing filters to see all pending items.
                @else
                    Failed employee creates and verifications will appear here with the exact API error for each caregiver.
                @endif
            </div>
        </div>
    @endif
</div>
