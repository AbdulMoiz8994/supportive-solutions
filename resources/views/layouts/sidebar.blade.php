@php
    use App\Helpers\MenuHelper;
    $menuGroups = MenuHelper::getMenuGroups();
@endphp

<aside 
    id="sidebar"
    class="fixed left-0 top-0 h-screen bg-[#EEF4FB] border-r border-[#dce7f5] z-40 transition-all duration-300 flex flex-col overflow-hidden"
    :class="$store.sidebar.expanded ? 'w-[260px]' : 'w-[80px]'">
    
    <!-- Sidebar Branding -->
    <div class="pt-6 pb-4 flex items-center justify-between px-5 flex-shrink-0 relative">
        <div x-cloak x-show="$store.sidebar.expanded" class="flex-shrink-0 sidebar-text-hide" x-transition:enter="delay-100 duration-200 opacity-0" x-transition:enter-end="opacity-100">
            <span class="text-[20px] tracking-tight">
                <span class="text-[#2563eb] font-black">Beydoun</span><span class="text-[#1e293b] font-black">Tech</span>
            </span>
        </div>
        <button @click="$store.sidebar.toggle()" class="w-[34px] h-[34px] rounded-[10px] border border-[#cbd5e1]/80 flex items-center justify-center text-[#1e293b] hover:text-[#2563eb] hover:border-[#2563eb] hover:bg-[#f0f7ff] transition-all bg-white shadow-[0_2px_8px_rgba(0,0,0,0.04)] ml-auto z-10 shrink-0">
            <svg x-show="$store.sidebar.expanded" class="w-4 h-4 ml-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M10 17l-5-5 5-5m-5 5h10m0-5a5 5 0 0 1 0 10"></path></svg>
            <svg x-show="!$store.sidebar.expanded" class="w-4 h-4 mr-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M14 17l5-5-5-5m5 5H9m0-5a5 5 0 0 0 0 10"></path></svg>
        </button>
    </div>

    <!-- Sidebar Search -->
    <div x-cloak x-show="$store.sidebar.expanded" class="px-5 mb-6 sidebar-text-hide" x-transition:enter="delay-100 opacity-0" x-transition:enter-end="opacity-100">
        <div class="relative flex items-center w-full bg-white border border-[#e2e8f0] rounded-[10px] px-3.5 py-2 shadow-[0_2px_10px_rgba(0,0,0,0.02)] focus-within:border-[#2563eb] transition-colors">
            <svg class="w-4 h-4 text-[#94a3b8] mr-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
            <input type="text" placeholder="Search" class="w-full text-[12px] font-bold text-[#1e293b] placeholder-[#94a3b8] bg-transparent outline-none">
        </div>
    </div>

    <!-- Navigation -->
    <div class="flex-1 overflow-y-auto no-scrollbar pb-6 px-4">
        <div class="flex flex-col gap-5 border-t border-transparent">
            @foreach ($menuGroups as $group)
                <div class="{{ $loop->first ? '' : 'pt-2' }}">
                    <h3 x-cloak x-show="$store.sidebar.expanded" 
                        class="px-3 text-[10px] font-black text-[#94a3b8] uppercase tracking-[0.1em] mb-2.5 whitespace-nowrap sidebar-text-hide"
                        x-transition:enter="delay-75 opacity-0" x-transition:enter-end="opacity-100">
                        {{ $group['name'] }}
                    </h3>
                    <ul class="space-y-1">
                        @foreach ($group['items'] as $item)
                            @php
                                $path = ltrim($item['path'] ?? 'NOMATCH', '/');
                                $activePatterns = $item['active'] ?? [$path, $path.'/*'];
                                $isActive = collect($activePatterns)->contains(fn ($pattern) => request()->is($pattern))
                                    || (request()->is('/') && $item['name'] === 'Dashboard');
                            @endphp

                            <li>
                                <a href="{{ $item['path'] ?? '#' }}" 
                                   class="group flex items-center rounded-xl transition-all duration-200 {{ $isActive ? 'bg-[#2563eb] text-white shadow-[0_4px_12px_rgba(37,99,235,0.2)]' : 'text-[#475569] hover:bg-white hover:shadow-sm hover:text-[#1e293b]' }} sidebar-icon-center"
                                   :class="$store.sidebar.expanded ? 'px-3 py-2.5 gap-3.5' : 'justify-center w-[46px] h-[46px] mx-auto rounded-xl'">
                                    
                                    <span class="flex-shrink-0 {{ $isActive ? 'text-white' : 'text-[#64748b] group-hover:text-[#2563eb]' }}">
                                        {!! \App\Helpers\MenuHelper::getIconSvg($item['icon'] ?? '') !!}
                                    </span>

                                    <span x-cloak x-show="$store.sidebar.expanded" class="text-[12.5px] font-bold whitespace-nowrap overflow-hidden sidebar-text-hide" x-transition:enter="delay-100 opacity-0" x-transition:enter-end="opacity-100">
                                        {{ $item['name'] }}
                                    </span>

                                    @if(array_key_exists('badge', $item))
                                        {{-- Badge stays in the DOM so it can live-refresh after approve actions (A9). --}}
                                        <span x-cloak x-show="$store.sidebar.expanded" data-sidebar-badge="{{ $item['path'] }}"
                                            class="ml-auto {{ $isActive ? 'bg-white/25 text-white' : 'bg-[#2563eb] text-white' }} text-[10px] font-bold px-2 py-0.5 rounded-full shadow-sm shrink-0 sidebar-text-hide {{ empty($item['badge']) ? 'hidden' : '' }}">{{ $item['badge'] }}</span>
                                    @elseif($isActive)
                                        <svg x-cloak x-show="$store.sidebar.expanded" class="ml-auto w-3 h-3 text-white shrink-0 sidebar-text-hide" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M9 5l7 7-7 7"></path></svg>
                                    @endif
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    </div>

    <!-- Bottom Actions -->
    <div class="mt-auto px-4 pb-6 flex flex-col gap-4 relative">
        <!-- Dark Mode Toggle -->
        <div x-cloak x-show="$store.sidebar.expanded" class="px-2 flex items-center justify-between sidebar-text-hide" x-transition:enter="opacity-0">
            <div class="flex items-center gap-2 text-[#64748b]">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z"></path></svg>
                <span class="text-[12px] font-bold">Light Mode</span>
            </div>
            <!-- Toggle Switch -->
            <button class="relative inline-flex h-5 w-9 shrink-0 cursor-pointer items-center justify-center rounded-full focus:outline-none focus:ring-2 focus:ring-[#2563eb] focus:ring-offset-2">
                <span aria-hidden="true" class="pointer-events-none absolute mx-auto h-3 w-7 rounded-full bg-white transition-colors duration-200 ease-in-out border border-[#e2e8f0]"></span>
                <span aria-hidden="true" class="pointer-events-none absolute left-0 inline-block h-4 w-4 translate-x-4 transform rounded-full bg-[#10b981] shadow ring-0 transition duration-200 ease-in-out"></span>
            </button>
        </div>

        <!-- User Profile Menu -->
        <x-account.user-menu variant="sidebar" />
    </div>
</aside>

<!-- Mobile Overlay -->
<div x-show="$store.sidebar.isMobileOpen" @click="$store.sidebar.setMobile(false)" class="fixed z-50 h-screen w-full bg-gray-900/50" x-cloak></div>

{{-- Live badge refresh (A9): pages dispatch `sidebar-badges:refresh` after
     approve/resolve actions, and a 60s poll (matching the server cache TTL)
     keeps counts in sync even without an explicit action on the current page. --}}
<script>
    async function refreshSidebarBadges() {
        try {
            const response = await fetch(@js(route('sidebar.badges')), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!response.ok) return;
            const counts = await response.json();

            document.querySelectorAll('[data-sidebar-badge]').forEach((el) => {
                const key = el.dataset.sidebarBadge;
                if (!(key in counts)) return;
                const count = Number(counts[key] ?? 0);
                el.textContent = count;
                el.classList.toggle('hidden', count <= 0);
            });
        } catch (e) { /* badge refresh is best-effort */ }
    }

    window.addEventListener('sidebar-badges:refresh', refreshSidebarBadges);

    if (document.querySelector('[data-sidebar-badge]')) {
        setInterval(refreshSidebarBadges, 60000);
    }
</script>