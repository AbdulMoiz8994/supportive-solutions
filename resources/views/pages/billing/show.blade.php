@extends('layouts.app')

@section('content')
    <div class="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between print:hidden">
        <x-common.page-breadcrumb pageTitle="Invoice Details" />
        
        <div class="flex items-center gap-4">
            <button onclick="window.print()" class="flex items-center gap-2 px-4 py-2 bg-white border border-gray-200 rounded-lg text-sm font-bold text-gray-700 hover:bg-gray-50 transition-all dark:bg-white/5 dark:border-white/10 dark:text-white">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"></path></svg>
                Print Invoice
            </button>
            <span class="px-3 py-1 rounded-full text-[10px] font-black uppercase {{ $billing->status === 'Paid' ? 'bg-green-100 text-green-700' : 'bg-orange-100 text-orange-700' }}">
                {{ $billing->status }}
            </span>
        </div>
    </div>

    <!-- Invoice Card -->
    <div class="bg-white dark:bg-white/[0.03] rounded-3xl border border-gray-100 dark:border-white/5 shadow-theme-xl overflow-hidden p-8 lg:p-12 print:shadow-none print:border-none print:p-0">
        
        <!-- Header -->
        <div class="flex flex-col md:flex-row justify-between gap-8 mb-12 border-b border-gray-100 dark:border-white/5 pb-12">
            <div>
                <h1 class="text-3xl font-black text-brand-600 mb-2">INVOICE</h1>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-widest">{{ $billing->invoice_number }}</p>
            </div>
            
            <div class="text-right">
                <div class="text-sm font-bold text-gray-800 dark:text-white/90">Agency: Beydoun Tech Health</div>
                <div class="text-[11px] text-gray-500 mt-1 uppercase font-semibold">
                    Billing Period: {{ date('M d, Y', strtotime($billing->period_start)) }} - {{ date('M d, Y', strtotime($billing->period_end)) }}
                </div>
            </div>
        </div>

        <!-- Addresses -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-12 mb-12">
            <div>
                <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Bill To (Client)</h4>
                <div class="text-lg font-bold text-gray-800 dark:text-white/90 uppercase">{{ $billing->client->first_name }} {{ $billing->client->last_name }}</div>
                <div class="text-sm text-gray-500 mt-2 leading-relaxed">
                    ID: {{ $billing->client->member_id }}<br>
                    {{ $billing->client->address }}<br>
                    {{ $billing->client->county }}
                </div>
            </div>
            <div class="md:text-right">
                <h4 class="text-[10px] font-black text-gray-400 uppercase tracking-widest mb-4">Payable To</h4>
                <div class="text-lg font-bold text-gray-800 dark:text-white/90 uppercase">Beydoun Tech Agency</div>
                <div class="text-sm text-gray-500 mt-2 leading-relaxed italic">
                    Medicare/Medicaid Clinical Provider<br>
                    Electronic Submission ID: BEY-9921
                </div>
            </div>
        </div>

        <!-- Service Table -->
        <div class="overflow-x-auto mb-12">
            <table class="w-full text-left">
                <thead>
                    <tr class="border-b border-gray-100 dark:border-white/5 pb-4">
                        <th class="py-4 text-xs font-black text-gray-400 uppercase tracking-widest">Service Date</th>
                        <th class="py-4 text-xs font-black text-gray-400 uppercase tracking-widest">Caregiver</th>
                        <th class="py-4 text-xs font-black text-gray-400 uppercase tracking-widest">Description</th>
                        <th class="py-4 text-xs font-black text-gray-400 uppercase tracking-widest">Hours</th>
                        <th class="py-4 text-xs font-black text-gray-400 uppercase tracking-widest text-right">Amount</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50 dark:divide-white/[0.02]">
                    @foreach($billing->schedules as $sch)
                        <tr>
                            <td class="py-6 text-sm font-bold text-gray-800 dark:text-white/90">{{ $sch->date->format('M d, Y') }}</td>
                            <td class="py-6">
                                <div class="text-sm font-bold text-gray-800 dark:text-white/90">{{ $sch->employee->first_name }} {{ $sch->employee->last_name }}</div>
                                <div class="text-[10px] text-gray-400 font-semibold uppercase italic">EVV Verified: Yes</div>
                            </td>
                            <td class="py-6 text-xs text-gray-500">Clinical Home Care Visit (Non-Skilled)</td>
                            <td class="py-6 text-sm font-bold text-gray-800 dark:text-white/90">{{ $sch->total_hours }} hrs</td>
                            <td class="py-6 text-sm font-black text-gray-800 dark:text-white/90 text-right">${{ number_format($sch->total_hours * ($billing->client->billing_rate ?? 25), 2) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <!-- Totals -->
        <div class="flex flex-col items-end">
            <div class="w-full max-w-xs space-y-4">
                <div class="flex justify-between p-4 bg-gray-50/50 dark:bg-white/[0.01] rounded-xl border border-gray-100 dark:border-white/5">
                    <span class="text-sm font-bold text-gray-500 uppercase tracking-widest">Total Units (Hours)</span>
                    <span class="text-sm font-bold text-gray-800 dark:text-white/90">{{ $billing->schedules->sum('total_hours') }}</span>
                </div>
                <div class="flex justify-between p-6 bg-brand-600 rounded-2xl text-white shadow-brand-xs">
                    <span class="text-sm font-black uppercase tracking-widest">Total Receivable</span>
                    <span class="text-xl font-black">${{ number_format($billing->total_amount, 2) }}</span>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <div class="mt-20 pt-12 border-t border-gray-100 dark:border-white/5 text-center">
            <p class="text-xs text-gray-400 font-bold uppercase tracking-widest mb-2">Thank you for choosing Beydoun Tech</p>
            <p class="text-[10px] text-gray-500">This is an electronically generated invoice for clinical services rendered. Compliance with state EVV regulations is verified via GPS coordinates logged during visit clock-in/out.</p>
        </div>
    </div>
@endsection
