@props(['variant' => 'sidebar'])

@php
    $user = auth()->user();
    $canSettings = \App\Helpers\SettingsHelper::canAccessHome($user);
    $avatarUrl = 'https://ui-avatars.com/api/?name=' . urlencode($user->name ?? 'User') . '&background=2563eb&color=fff&bold=true';
@endphp

<div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
    @if($variant === 'sidebar')
        <button type="button"
            @click="open = !open"
            @click.away="open = false"
            class="w-full bg-white border border-[#e2e8f0]/80 rounded-[12px] p-2 flex items-center gap-3 shadow-[0_2px_10px_rgba(0,0,0,0.02)] cursor-pointer hover:border-[#cbd5e1] transition-colors overflow-hidden sidebar-icon-center text-left"
            :class="$store.sidebar.expanded ? '' : 'justify-center p-0 w-[46px] h-[46px] mx-auto'">
            <div class="w-8 h-8 rounded-full bg-[#2563eb] shrink-0 overflow-hidden relative">
                <img src="{{ $avatarUrl }}" alt="User" class="w-full h-full object-cover">
            </div>
            <div x-cloak x-show="$store.sidebar.expanded" class="flex-1 min-w-0 sidebar-text-hide" x-transition:enter="opacity-0">
                <div class="text-[11px] font-black text-[#1e293b] truncate leading-tight">{{ $user->name ?? 'User' }}</div>
                <div class="text-[9.5px] font-medium text-[#64748b] truncate leading-tight">{{ $user->role ?? '' }}</div>
            </div>
            <div x-cloak x-show="$store.sidebar.expanded" class="text-[#94a3b8] pr-1 shrink-0 sidebar-text-hide">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l4-4 4 4m0 6l-4 4-4-4"></path>
                </svg>
            </div>
        </button>

        <div x-show="open" x-cloak
            x-transition:enter="transition ease-out duration-100"
            class="absolute left-0 right-0 bottom-full mb-2 bg-white rounded-xl shadow-2xl border border-blue-50 z-50 overflow-hidden">
            @include('components.account.user-menu-items')
        </div>
    @else
        <div @click="open = !open" @click.away="open = false" class="flex items-center cursor-pointer">
            <div class="w-[32px] h-[32px] rounded-full overflow-hidden shrink-0 ml-1.5 shadow-sm border border-[#e2e8f0]">
                <img src="{{ $avatarUrl }}" alt="User" class="w-full h-full object-cover">
            </div>
        </div>

        <div x-show="open" x-cloak
            x-transition:enter="transition ease-out duration-100"
            class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-2xl border border-blue-50 z-50 overflow-hidden">
            <div class="px-4 py-3 border-b border-gray-50 bg-[#f8fafc]">
                <p class="text-[13px] font-bold text-[#1e293b] truncate">{{ $user->name }}</p>
                <p class="text-[11px] text-[#64748b] truncate">{{ $user->email }}</p>
            </div>
            @include('components.account.user-menu-items')
        </div>
    @endif
</div>
