@php
    $navGroups = [
        'Agency' => [
            ['id' => 'agency-profile', 'label' => 'Agency Profile', 'icon' => 'M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4'],
            ['id' => 'programs-rates', 'label' => 'Programs & Rates', 'icon' => 'M3 6l3 1m0 0l-3 9a5.002 5.002 0 006.001 0M6 7l3 9M6 7l6-2m6 2l3-1m-3 1l-3 9a5.002 5.002 0 006.001 0M18 7l3 9m-3-9l-6-2m0-2v2m0 16V5m0 16H9m3 0h3'],
        ],
        'Connections' => [
            ['id' => 'integrations', 'label' => 'Integrations', 'icon' => 'M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1'],
            ['id' => 'billing-claims', 'label' => 'Billing & Claims', 'icon' => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
            ['id' => 'credential-vault', 'label' => 'Credential Vault', 'icon' => 'M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z'],
        ],
        'Governance' => [
            ['id' => 'security-compliance', 'label' => 'Security & Compliance', 'icon' => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
            ['id' => 'access-activation', 'label' => 'Access & Activation', 'icon' => 'M15 5v2m0 4v2m0 4v2M5 5a2 2 0 00-2 2v3a2 2 0 110 4v3a2 2 0 002 2h14a2 2 0 002-2v-3a2 2 0 110-4V7a2 2 0 00-2-2H5z'],
        ],
        'Automation' => [
            ['id' => 'ai-automation', 'label' => 'AI & Automation', 'icon' => 'M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
            ['id' => 'notifications-language', 'label' => 'Notifications & Language', 'icon' => 'M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9'],
        ],
    ];
@endphp

<div class="w-full lg:w-[260px] shrink-0 bg-white border border-slate-100 rounded-[24px] p-3 shadow-[0_8px_30px_rgb(0,0,0,0.03)] sticky top-20">
    @foreach($navGroups as $group => $items)
        <div class="text-[10px] font-black text-[#94a3b8] uppercase tracking-[0.12em] px-3 pt-3 pb-1">{{ $group }}</div>
        <div class="space-y-1">
            @foreach($items as $item)
                <button
                    type="button"
                    @click="switchTab('{{ $item['id'] }}')"
                    :class="activeTab === '{{ $item['id'] }}' ? 'bg-[#2563eb] text-white shadow-[0_8px_18px_rgba(37,99,235,0.22)]' : 'text-[#64748b] hover:bg-slate-50 hover:text-[#1e293b]'"
                    class="w-full flex items-center gap-3 px-3 py-2.5 rounded-xl transition-all duration-300 group text-left">
                    <div :class="activeTab === '{{ $item['id'] }}' ? 'bg-white/20 text-white' : 'bg-slate-50 text-[#94a3b8] group-hover:bg-white group-hover:text-blue-600'"
                         class="w-9 h-9 rounded-lg flex items-center justify-center shrink-0 shadow-sm transition-all duration-300">
                        <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="{{ $item['icon'] }}"></path>
                        </svg>
                    </div>
                    <span class="text-sm tracking-tight truncate" :class="activeTab === '{{ $item['id'] }}' ? 'font-black' : 'font-bold'">{{ $item['label'] }}</span>
                    <svg x-show="activeTab === '{{ $item['id'] }}'" class="ml-auto w-4 h-4 text-white/80 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                </button>
            @endforeach
        </div>
    @endforeach
</div>
