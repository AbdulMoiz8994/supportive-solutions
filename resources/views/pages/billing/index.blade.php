@extends('layouts.app')

@section('content')
    <x-common.page-breadcrumb pageTitle="Billing & Claims Audit" />

    <div class="mb-8 grid grid-cols-1 md:grid-cols-3 gap-6">
        <!-- Revenue Card -->
        <div class="p-6 bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/5 shadow-theme-xs">
            <div class="flex items-center justify-between mb-4">
                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Total Collected</span>
                <div class="p-2 bg-green-50 dark:bg-green-500/10 rounded-lg text-green-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <h2 class="text-3xl font-black text-gray-800 dark:text-white/90 tracking-tight">
                ${{ number_format($billings->where('status', 'Paid')->sum('total_amount'), 2) }}
            </h2>
            <p class="text-[10px] font-bold text-green-600 uppercase mt-2 italic">Approved & Paid</p>
        </div>

        <!-- Pending Card -->
        <div class="p-6 bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/5 shadow-theme-xs">
            <div class="flex items-center justify-between mb-4">
                <span class="text-[10px] font-black text-gray-400 uppercase tracking-widest">Pending Claims</span>
                <div class="p-2 bg-orange-50 dark:bg-orange-500/10 rounded-lg text-orange-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                </div>
            </div>
            <h2 class="text-3xl font-black text-gray-800 dark:text-white/90 tracking-tight">
                ${{ number_format($billings->where('status', 'Pending')->sum('total_amount'), 2) }}
            </h2>
            <p class="text-[10px] font-bold text-orange-600 uppercase mt-2 italic">Awaiting Processing</p>
        </div>

        <!-- Actions Card -->
        <div class="p-6 bg-brand-600 rounded-2xl shadow-brand-xs flex flex-col justify-between">
            <div>
                <h4 class="text-white font-black text-sm uppercase tracking-widest">Billing Operations</h4>
                <p class="text-brand-100 text-[10px] mt-1 font-bold">Generate invoices for completed visits</p>
            </div>
            <form action="{{ route('billing.run') }}" method="POST">
                @csrf
                <button type="submit" class="w-full mt-4 py-3 px-4 flex items-center justify-center gap-2 text-xs font-black text-brand-600 bg-white rounded-xl hover:bg-brand-50 active:scale-95 transition-all shadow-sm uppercase tracking-widest">
                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                    Run Cycle
                </button>
            </form>
        </div>
    </div>

    <div class="space-y-6">
            <div class="flex items-center justify-between">
                <div>
                    <h3 class="text-xl font-bold text-gray-800 dark:text-white/90">Billing Records</h3>
                    <p class="text-[11px] text-gray-500 font-medium uppercase tracking-tighter mt-1">Showing latest billed care cycles</p>
                </div>
                <div class="flex gap-2">
                    <button class="px-4 py-2 text-[11px] font-bold text-gray-500 uppercase tracking-widest bg-white border border-gray-100 rounded-xl hover:bg-gray-50 transition-all dark:bg-white/5 dark:border-white/10 dark:text-white">Export CSV</button>
                </div>
            </div>

            <div class="bg-white dark:bg-white/[0.03] rounded-2xl border border-gray-100 dark:border-white/5 shadow-theme-xs overflow-hidden">
                <div class="overflow-x-auto">
                    <table class="w-full text-left">
                        <thead>
                            <tr class="border-b border-gray-100 dark:border-white/10">
                                <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest">Client</th>
                                <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest">Invoice #</th>
                                <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest">Period</th>
                                <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest text-right">Amount</th>
                                <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest text-center">Status</th>
                                <th class="px-6 py-4 text-xs font-black text-gray-400 uppercase tracking-widest text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-50 dark:divide-white/[0.02]">
                            @forelse($billings as $bill)
                                <tr class="group hover:bg-gray-50/50 transition-colors">
                                    <td class="px-6 py-5">
                                        <div class="font-bold text-gray-800 dark:text-white/90 uppercase text-xs tracking-tight">{{ $bill->client?->first_name }} {{ $bill->client?->last_name }}</div>
                                        <div class="text-[10px] text-gray-400 font-bold uppercase italic mt-0.5">ID: {{ $bill->client?->member_id }}</div>
                                    </td>
                                    <td class="px-6 py-5 text-xs font-mono text-gray-500 font-bold">{{ $bill->invoice_number }}</td>
                                    <td class="px-6 py-5">
                                        <div class="text-[10px] font-bold text-gray-600 dark:text-white/70 uppercase">
                                            {{ date('M d', strtotime($bill->period_start)) }} - {{ date('M d', strtotime($bill->period_end)) }}
                                        </div>
                                    </td>
                                    <td class="px-6 py-5 text-right font-black text-gray-800 dark:text-white/90 text-sm">
                                        ${{ number_format($bill->total_amount, 2) }}
                                    </td>
                                    <td class="px-6 py-5 text-center">
                                        <span class="px-3 py-1.5 text-[9px] font-black rounded-lg uppercase tracking-widest {{ $bill->status === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' }}">
                                            {{ $bill->status }}
                                        </span>
                                    </td>
                                    <td class="px-6 py-5 text-right">
                                        <a href="{{ route('billing.show', $bill->id) }}" class="inline-flex items-center gap-1.5 text-brand-600 hover:text-brand-700 text-[11px] font-black uppercase tracking-widest group/btn">
                                            Manage
                                            <svg class="w-3 h-3 group-hover/btn:translate-x-0.5 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M9 5l7 7-7 7"></path></svg>
                                        </a>
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="6" class="py-12 text-center text-gray-400 italic">No billing records found for the current organization.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
@endsection
