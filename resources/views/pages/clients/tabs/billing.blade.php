{{-- Billing History --}}
<div x-show="activeTab === 'billing'" x-cloak class="space-y-4"
     x-data
     x-init="@if(request('billing')) $nextTick(() => document.getElementById('billing-{{ (int) request('billing') }}')?.scrollIntoView({ block: 'center', behavior: 'smooth' })) @endif">
    @php
        $highlightBillingId = (int) request('billing');
        $paid = $client->billings->where('status', 'Paid')->sum('total_amount');
        $outstanding = $client->billings->whereIn('status', ['Pending', 'Sent'])->sum('total_amount');
        $billed = $client->billings->sum('total_amount');
    @endphp

    <div class="grid grid-cols-2 md:grid-cols-3 gap-3">
        <x-ui.stat-card label="Total billed" :value="'$'.number_format($billed)" :sub="$client->billings->count().' invoice(s)'" />
        <x-ui.stat-card label="Collected" :value="'$'.number_format($paid)" sub="Paid in full" />
        <x-ui.stat-card label="Outstanding" :value="'$'.number_format($outstanding)" sub="Awaiting payment" />
    </div>

    <x-ui.panel bodyClass="p-0">
        <div class="flex items-center justify-between px-5 pt-5 pb-3">
            <h3 class="text-base font-bold text-[#0f172a]">Billing History</h3>
            <x-ui.btn variant="outline" size="sm" :href="route('billing-claims-audit.index')">Open billing</x-ui.btn>
        </div>
        <div class="w-full overflow-x-auto no-scrollbar">
            <table class="w-full min-w-[760px] border-collapse">
                <thead>
                    <tr class="border-y border-[#eef2f9] bg-[#fafcff]">
                        @foreach(['Invoice #','Period','Amount','Status','EOB'] as $col)
                            <th class="px-5 py-2.5 text-left text-xs font-bold text-[#94a3b8] uppercase tracking-wider whitespace-nowrap">{{ $col }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody class="divide-y divide-[#f1f5f9]">
                    @forelse($client->billings->sortByDesc('created_at') as $bill)
                        <tr id="billing-{{ $bill->id }}"
                            @class([
                                'hover:bg-[#f7faff] transition-colors',
                                'bg-[#eff6ff] ring-1 ring-inset ring-[#2563eb]/25' => $highlightBillingId === $bill->id,
                            ])>
                            <td class="px-5 py-3 text-sm font-bold text-[#0f172a] whitespace-nowrap">{{ $bill->invoice_number ?? '—' }}</td>
                            <td class="px-5 py-3 text-sm text-[#64748b] whitespace-nowrap">{{ $bill->period_start ? \Carbon\Carbon::parse($bill->period_start)->format('M j') : '—' }} – {{ $bill->period_end ? \Carbon\Carbon::parse($bill->period_end)->format('M j, Y') : '—' }}</td>
                            <td class="px-5 py-3 text-sm font-bold text-[#0f172a]">${{ number_format($bill->total_amount, 2) }}</td>
                            <td class="px-5 py-3"><x-ui.pill :variant="$bill->status === 'Paid' ? 'green' : 'amber'">{{ $bill->status }}</x-ui.pill></td>
                            <td class="px-5 py-3 text-xs font-semibold text-[#2563eb] uppercase cursor-pointer hover:underline">View EOB</td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-5 py-12 text-center text-sm text-[#94a3b8] italic">No billing records yet.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <p class="px-5 py-4 text-xs text-[#94a3b8]">No billing while the client is in hospital / nursing home / rehab (except discharge day). Claims follow the verified compliance hours.</p>
    </x-ui.panel>
</div>
