@extends('layouts.app')

@section('content')
    <div class="space-y-6 w-full px-2">
         
        <!-- Dashboard Header -->
        <div class="flex flex-col lg:flex-row lg:items-end justify-between gap-6 pt-4 px-2">
            <div class="space-y-1.5">
                <h1 class="text-[28px] font-bold text-[#1e293b] tracking-tight leading-tight">System Overview</h1>
                <div class="flex items-center gap-1.5 text-[11px] font-medium text-[#64748b] tracking-wide">
                    <span>Control tower - Last refreshed 2 min ago - {{ $headerMeta['refreshed_at'] }} • {{ $headerMeta['timezone'] }}</span>
                </div>
            </div>
            
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-2 px-4 py-2 bg-white border border-[#e2e8f0] rounded-xl cursor-pointer hover:bg-gray-50 transition-all shadow-sm">
                    <span class="text-[11px] font-bold text-[#1e293b]">This Month</span>
                    <svg class="w-3 h-3 text-[#94a3b8]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                </div>
                
                <button class="flex items-center gap-2 px-4 py-2 bg-white border border-[#e2e8f0] rounded-xl text-[#1e293b] hover:bg-gray-50 transition-all shadow-sm">
                    <svg class="w-3.5 h-3.5 text-[#64748b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z"></path></svg>
                    <span class="text-[11px] font-bold tracking-wide">Share</span>
                </button>

                <button class="bg-[#2563eb] text-white px-5 py-2 rounded-xl text-[11px] font-bold tracking-wide shadow-md shadow-[#2563eb]/20 hover:bg-[#1d4ed8] transition-all flex items-center gap-2">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"></path></svg>
                    Export
                </button>
            </div>
        </div>

        <!-- Stat Cards Row -->
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 xl:grid-cols-5 gap-4">
            @foreach($statCards as $card)
                <div class="bg-[#eff6ff] rounded-2xl p-5 border border-blue-100/50 shadow-sm hover:shadow-md transition-all flex items-center gap-4 relative">
                    <div class="w-12 h-12 bg-[#e0efff] text-[#2563eb] rounded-[10px] flex items-center justify-center shadow-sm shrink-0">
                        @if($card['icon'] === 'users') <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/></svg> @endif
                        @if($card['icon'] === 'file-text') <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg> @endif
                        @if($card['icon'] === 'dollar-sign') <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> @endif
                        @if($card['icon'] === 'user-check') <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4"></path></svg> @endif
                        @if($card['icon'] === 'check-circle') <svg class="w-5.5 h-5.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg> @endif
                    </div>
                    
                    <div class="flex-1 min-w-0">
                        <p class="text-[11.5px] font-medium text-[#64748b] leading-tight mb-0.5">{{ $card['title'] }}</p>
                        <h3 class="text-[26px] font-bold text-[#1e293b] leading-none">{{ $card['value'] }}</h3>
                    </div>

                    @if(isset($card['change']))
                        @php
                            $changeClass = str_contains($card['change'], '-')
                                ? 'text-[#dc2626]'
                                : (str_contains($card['change'], '+') ? 'text-[#10b981]' : 'text-[#64748b]');
                        @endphp
                        <div class="absolute right-4 bottom-4 flex items-center gap-0.5 {{ $changeClass }} text-[11px] font-semibold bg-white/50 px-1.5 py-0.5 rounded border border-blue-100/10 shadow-sm">
                            <span>{{ $card['change'] }}</span>
                        </div>
                    @else
                        <div class="absolute right-4 bottom-4 text-blue-500 text-[11px] font-semibold">
                            {{ $card['status'] ?? 'Stable' }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>

        <!-- Main Analytics Section -->
        <div class="grid grid-cols-12 gap-6">
            <!-- Revenue Comparison Chart -->
            <div class="col-span-12 xl:col-span-8 bg-[#eff6ff] rounded-3xl border border-blue-100/50 p-8 flex flex-col shadow-sm relative overflow-hidden">
                <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 mb-10">
                    <div class="space-y-1.5">
                        <h3 class="text-[16px] font-bold text-[#1e293b]">Revenue & Billing Overview</h3>
                        <p class="text-[12px] text-[#64748b]">Monthly comparison of authorized vs collected billing</p>
                    </div>
                    <div class="flex flex-wrap items-center gap-5">
                        <div class="flex items-center gap-2">
                             <div class="w-2.5 h-2.5 rounded-full bg-[#1e293b]"></div>
                             <span class="text-[11px] font-bold text-[#1e293b]">Authorized Amount</span>
                        </div>
                        <div class="flex items-center gap-2">
                             <div class="w-2.5 h-2.5 rounded-full bg-[#2563eb]"></div>
                             <span class="text-[11px] font-bold text-[#1e293b]">Collected Revenue</span>
                        </div>
                        <div class="ml-2 bg-white border border-[#e2e8f0] px-3 py-1.5 rounded-lg text-[11px] font-medium text-[#1e293b] flex items-center gap-3 cursor-pointer shadow-sm">
                            Monthly
                            <svg class="w-3.5 h-3.5 text-[#64748b]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path></svg>
                        </div>
                    </div>
                </div>

                <div class="space-y-1 mb-8">
                    <div class="flex items-baseline gap-2.5">
                        <span class="text-[28px] font-bold text-[#1e293b] tracking-tight">{{ $revenueOverview['total'] }}</span>
                        <span class="bg-[#d1fae5] text-[#059669] text-[10px] font-bold px-2 py-0.5 rounded border border-[#a7f3d0]">{{ $revenueOverview['change'] }}</span>
                        <span class="text-[11px] text-[#94a3b8] font-medium ml-1">from last month</span>
                    </div>
                </div>

                <!-- Custom Grid Bar Chart -->
                <div class="flex-1 h-[240px] flex items-end gap-5 px-2 relative mt-4">
                    <!-- Grid Lines -->
                    <div class="absolute inset-0 flex flex-col justify-between pointer-events-none pb-4 text-[10px] text-[#94a3b8] font-medium">
                        <div class="w-full flex items-center gap-3"><span class="w-6">10k</span><div class="flex-1 border-t border-dashed border-[#e2e8f0]"></div></div>
                        <div class="w-full flex items-center gap-3"><span class="w-6">8k</span><div class="flex-1 border-t border-dashed border-[#e2e8f0]"></div></div>
                        <div class="w-full flex items-center gap-3"><span class="w-6">6k</span><div class="flex-1 border-t border-dashed border-[#e2e8f0]"></div></div>
                        <div class="w-full flex items-center gap-3"><span class="w-6">4k</span><div class="flex-1 border-t border-dashed border-[#e2e8f0]"></div></div>
                        <div class="w-full flex items-center gap-3"><span class="w-6">2k</span><div class="flex-1 border-t border-dashed border-[#e2e8f0]"></div></div>
                        <div class="w-full flex items-center gap-3"><span class="w-6">0k</span><div class="flex-1 border-t border-dashed border-[#e2e8f0]"></div></div>
                    </div>

                    <div class="pl-9 w-full flex h-full items-end gap-2 relative z-10">
                    @foreach($monthlyChart as $bar)
                        <div class="flex-1 flex items-end gap-1 relative z-10 group h-full">
                            <div class="w-full h-full relative flex items-end justify-center">
                                <div class="w-3/4 absolute bottom-0 bg-[#93c5fd] rounded-b-sm transition-all shadow-sm" style="height: {{ $bar['h1'] }}%"></div>
                                <div class="w-3/4 absolute bottom-0 bg-[#2563eb] rounded-t-sm transition-all shadow-sm" style="height: {{ $bar['h1'] }}%; transform: translateY(-{{ $bar['h2'] }}%); height: {{ $bar['h2'] }}%"></div>
                            </div>
                            <div class="absolute -bottom-8 left-1/2 -translate-x-1/2 text-[10px] font-medium text-[#64748b] uppercase">
                                {{ $bar['label'] }}
                            </div>
                        </div>
                    @endforeach
                    </div>
                </div>
            </div>

            <!-- Dashboard Donut -->
            <div class="col-span-12 xl:col-span-4 bg-[#eff6ff] rounded-3xl border border-blue-100/50 p-8 flex flex-col shadow-sm">
                <div class="mb-10">
                    <h3 class="text-[16px] font-bold text-[#1e293b] mb-1">Monthly Billing Overview</h3>
                </div>

                <div class="relative w-48 h-48 mx-auto flex items-center justify-center mb-10">
                    <svg class="w-full h-full -rotate-90 transform drop-shadow-sm" viewBox="0 0 100 100">
                        <!-- Grey background arc -->
                        <circle class="text-[#e2e8f0]" stroke-width="14" stroke="currentColor" fill="transparent" r="40" cx="50" cy="50" />
                        <!-- Blue active arc -->
                        <circle class="text-[#2563eb]" stroke-width="14" stroke-dasharray="251" stroke-dashoffset="{{ $billingDonut['collected_offset'] }}" stroke-linecap="butt" stroke="currentColor" fill="transparent" r="40" cx="50" cy="50" />
                        <!-- Yellow active arc -->
                        <circle class="text-[#f59e0b]" stroke-width="14" stroke-dasharray="251" stroke-dashoffset="{{ $billingDonut['remaining_offset'] }}" stroke-linecap="butt" stroke="currentColor" fill="transparent" r="40" cx="50" cy="50" />
                    </svg>
                    <div class="absolute flex flex-col items-center">
                        <span class="text-[12px] font-medium text-[#64748b]">Total</span>
                        <span class="text-[26px] font-bold text-[#1e293b] leading-none mt-1">{{ $billingDonut['total'] }}</span>
                    </div>
                </div>

                <div class="space-y-4 pt-4">
                    @foreach($billingDonut['rows'] as $row)
                        <div class="flex items-center justify-between">
                            <div class="flex items-center gap-3">
                                <div class="w-2.5 h-2.5 rounded-full {{ $row['color'] }}"></div>
                                <span class="text-[12px] font-bold text-[#1e293b]">{{ $row['label'] }}</span>
                            </div>
                            <div class="flex items-center gap-3">
                                @if(isset($row['desc'])) <span class="text-[10px] text-[#94a3b8]">{{ $row['desc'] }}</span> @endif
                                <span class="text-[12px] font-bold text-[#1e293b] w-8 text-right">{{ $row['val'] }}</span>
                                <span class="{{ $row['pctBg'] }} {{ $row['pctText'] }} text-[10px] font-bold px-2 py-0.5 rounded-full w-10 text-center">{{ $row['pct'] }}</span>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Recent Activity Feed -->
        <div class="bg-[#eff6ff] rounded-3xl border border-blue-100/50 p-8 shadow-sm">
            <div class="mb-8">
                <h3 class="text-[16px] font-bold text-[#1e293b] mb-1">Recent Activity</h3>
                <p class="text-[12px] text-[#64748b]">View recent progress, status changes, and important workflow activity.</p>
            </div>

            <div class="space-y-10 pl-2">
                @forelse($recentActivities as $activity)
                    <div class="flex gap-6 relative">
                        @if(!$loop->last) <div class="absolute left-[3px] top-4 bottom-[-24px] w-px bg-[#e2e8f0]"></div> @endif
                        <div class="w-2 h-2 {{ $activity['icon'] }} rounded-full mt-1.5 z-10 relative"></div>
                        <div class="space-y-1.5 flex-1">
                            <p class="text-[11px] font-medium text-[#64748b]">{{ $activity['time'] }}</p>
                            <p class="text-[13px] leading-relaxed text-[#475569]">
                                <span class="font-bold text-[#1e293b]">{{ $activity['title'] }}</span>
                                {{ $activity['desc'] }}
                            </p>
                            <div class="flex items-center gap-2 pt-1">
                                <svg class="w-3.5 h-3.5 text-[#2563eb]" fill="currentColor" viewBox="0 0 24 24"><path d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg>
                                <span class="text-[11px] font-medium text-[#64748b]">{{ $activity['user'] }}</span>
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="flex gap-6 relative">
                        <div class="w-2 h-2 bg-[#94a3b8] rounded-full mt-1.5 z-10 relative"></div>
                        <div class="space-y-1.5 flex-1">
                            <p class="text-[11px] font-medium text-[#64748b]">No recent activity</p>
                            <p class="text-[13px] leading-relaxed text-[#475569]">
                                <span class="font-bold text-[#1e293b]">No activity recorded yet:</span>
                                Recent workflow events will appear here once staff actions are logged.
                            </p>
                        </div>
                    </div>
                @endforelse
            </div>
        </div>
    </div>

    <!-- Organization Table section appended from Figma -->
    <div class="mt-6 bg-[#eff6ff] rounded-3xl border border-blue-100/50 overflow-hidden shadow-sm">
        <!-- Header -->
        <div class="px-8 py-5 flex items-center justify-between border-b border-blue-100/20">
            <h2 class="text-[18px] font-bold text-[#1e293b]">Organization</h2>
            <div class="flex items-center gap-3">
                <div class="relative hidden sm:block">
                    <svg class="w-4 h-4 text-[#94a3b8] absolute left-3 top-2.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                    <input type="text" placeholder="Search agencies" class="pl-9 pr-4 py-2 w-[200px] bg-white border border-[#e2e8f0] rounded-[8px] text-[12px] font-medium text-[#1e293b] placeholder-[#94a3b8] focus:border-[#2563eb] outline-none shadow-sm">
                </div>
                <button class="bg-white border border-[#e2e8f0] px-3.5 py-2 rounded-[8px] text-[12px] font-bold text-[#475569] shadow-sm flex items-center gap-2 hover:bg-[#f8fafc] transition-colors">
                    This Week
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                </button>
            </div>
        </div>

        <!-- Table Data Grid -->
        <div class="w-full overflow-x-auto no-scrollbar">
            <div class="min-w-[900px]">
                <!-- Table Header -->
                <div class="grid grid-cols-[1.5fr_1fr_1fr_1fr_1fr_60px] gap-4 px-8 py-4 bg-blue-50/30 text-[10px] uppercase tracking-wide font-black text-[#94a3b8] border-b border-blue-100/20">
                    <div>Agency Name</div>
                    <div>Clients</div>
                    <div>Billing Cycle</div>
                    <div>Plan</div>
                    <div>Contract Renewal</div>
                    <div class="w-8"></div>
                </div>

                <!-- Alpine Component for Accordion Rows -->
                <div x-data="{ expandedRow: {{ count($organizations) > 0 ? 0 : 'null' }} }" class="flex flex-col">
                    @forelse($organizations as $index => $organization)
                    <div class="flex flex-col border-b {{ $loop->last ? 'border-blue-100/20' : 'border-[#e2e8f0]/60' }} transition-all duration-300" :class="expandedRow === {{ $index }} ? 'bg-white shadow-sm z-10' : 'bg-transparent hover:bg-white/50'">
                        <div @click="expandedRow = expandedRow === {{ $index }} ? null : {{ $index }}" class="grid grid-cols-[1.5fr_1fr_1fr_1fr_1fr_60px] gap-4 px-8 py-4.5 items-center cursor-pointer">
                            <div class="flex items-center gap-3">
                                <div class="w-[26px] h-[26px] rounded-full overflow-hidden shrink-0 border border-[#e2e8f0]">
                                    <img src="https://ui-avatars.com/api/?name={{ urlencode($organization['avatar_name']) }}&background=2563eb&color=fff" class="w-full h-full object-cover">
                                </div>
                                <span class="text-[12.5px] font-bold text-[#1e293b]">{{ $organization['name'] }}</span>
                            </div>
                            <div class="text-[12.5px] font-bold text-[#1e293b]">{{ $organization['clients_count'] }}</div>
                            <div class="text-[12.5px] font-bold text-[#1e293b]">{{ $organization['billing_cycle'] }}</div>
                            <div class="text-[12.5px] font-bold text-[#1e293b]">{{ $organization['plan'] }}</div>
                            <div class="text-[12.5px] font-bold text-[#1e293b]">{{ $organization['contract_renewal'] }}</div>
                            <div class="w-8 flex items-center justify-end ml-auto">
                                <div class="w-6 h-6 rounded-full border border-[#cbd5e1] flex items-center justify-center text-[#64748b] transition-transform duration-200" :class="expandedRow === {{ $index }} ? 'rotate-180 bg-white' : 'bg-white'">
                                    <svg x-show="expandedRow !== {{ $index }}" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 15l7-7 7 7"></path></svg>
                                    <svg x-show="expandedRow === {{ $index }}" class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                                </div>
                            </div>
                        </div>

                        <div x-show="expandedRow === {{ $index }}" x-collapse @if($index > 0) x-cloak @endif>
                            <div class="grid grid-cols-[1.5fr_1fr_1fr_1fr_1fr_auto] gap-4 px-8 pb-6 pt-2 items-end">
                                <div class="space-y-1.5">
                                    <div class="text-[10px] font-black text-[#94a3b8]">Business Address</div>
                                    <div class="text-[12px] font-medium text-[#475569] truncate">{{ $organization['address'] }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="text-[10px] font-black text-[#94a3b8]">State</div>
                                    <div class="text-[12px] font-medium text-[#475569]">{{ $organization['state'] }}</div>
                                </div>
                                <div class="space-y-1.5">
                                    <div class="text-[10px] font-black text-[#94a3b8]">Account Status</div>
                                    <div><span class="bg-[#eff6ff] text-[#3b82f6] text-[10px] font-bold px-3 py-1 rounded-full">{{ $organization['status'] }}</span></div>
                                </div>
                                <div class="space-y-1.5 col-span-2">
                                    <div class="text-[10px] font-black text-[#94a3b8]">Customer Email</div>
                                    <div class="text-[12px] font-bold text-[#1e293b]">{{ $organization['email'] }}</div>
                                </div>
                                <div class="flex items-center gap-2 justify-end">
                                    <button class="w-[32px] h-[32px] rounded-[8px] bg-[#fb7185] hover:bg-[#f43f5e] text-white flex items-center justify-center transition shadow-sm" title="Delete">
                                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path></svg>
                                    </button>
                                    <button class="w-[32px] h-[32px] rounded-[8px] bg-[#818cf8] hover:bg-[#6366f1] text-white flex items-center justify-center transition shadow-sm" title="View">
                                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"></path><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"></path></svg>
                                    </button>
                                    <button class="w-[32px] h-[32px] rounded-[8px] bg-[#4ade80] hover:bg-[#22c55e] text-white flex items-center justify-center transition shadow-sm" title="Trend">
                                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    @empty
                    <div class="px-8 py-10 text-center text-[13px] text-[#64748b]">No organization records available.</div>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
