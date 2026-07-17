<header class="sticky top-0 z-30 bg-[#eff6ff] flex flex-col transition-all duration-300">
    <!-- Top Global Row -->
    <div class="h-[60px] px-8 flex items-center justify-between border-b border-transparent">
        <!-- Left: Toggle & Search Area -->
        <div class="flex items-center gap-4 flex-1">
            <!-- Sidebar Split Window Toggle Icon -->
            <button @click="$store.sidebar.toggle()" class="w-[30px] h-[30px] rounded-[8px] border border-[#cbd5e1] flex items-center justify-center text-[#64748b] hover:text-[#2563eb] hover:border-[#2563eb] transition-all shrink-0">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M4 5a1 1 0 011-1h14a1 1 0 011 1v14a1 1 0 01-1 1H5a1 1 0 01-1-1V5zM4 5h5v14H4z"></path></svg>
            </button>
             
            <div class="hidden md:flex flex-1 items-center relative group max-w-[280px]">
                <div class="absolute left-3 text-[#94a3b8] transition-colors">
                    <svg class="w-[18px] h-[18px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path></svg>
                </div>
                <input type="text" placeholder="Search..." 
                       class="w-full bg-transparent border border-[#cbd5e1]/60 rounded-[10px] py-1.5 pl-9 pr-4 text-[12px] font-medium text-[#1e293b] placeholder-[#94a3b8] focus:border-[#2563eb] outline-none transition-all">
            </div>
        </div>

        <!-- Right: Actions -->
        <div class="flex items-center gap-1 md:gap-3">
            <!-- Location Switcher -->
            @php
                $user = auth()->user();
                $locations = $user->isSuperAdmin() ? \App\Models\Location::where('is_active', true)->get() : $user->locations;
            @endphp
            <div class="hidden lg:block relative" x-data="{ 
                open: false, 
                selectedName: '{{ session('selected_location_name', 'Company Wide') }}',
                switchLocation(id) {
                    $refs.locationIdInput.value = id;
                    $refs.locationForm.submit();
                }
            }">
                <button @click="open = !open" @click.away="open = false"
                    class="flex items-center gap-2 bg-[#e2e8f0]/50 border border-transparent hover:bg-[#e2e8f0] px-3 py-1.5 rounded-[8px] text-[11px] font-bold text-[#475569] transition">
                    <span x-text="selectedName"></span>
                    <svg class="w-3.5 h-3.5 text-[#94a3b8] transition-transform" :class="open ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"></path></svg>
                </button>

                <form x-ref="locationForm" action="{{ route('location.switch') }}" method="POST" class="hidden">
                    @csrf
                    <input type="hidden" name="location_id" x-ref="locationIdInput">
                </form>

                <div x-show="open" x-cloak
                    x-transition:enter="transition ease-out duration-100"
                    x-transition:enter-start="opacity-0 scale-95"
                    x-transition:enter-end="opacity-100 scale-100"
                    class="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-blue-50 p-2 z-50">
                    <div class="px-3 py-2 text-[10px] font-black text-[#94a3b8] uppercase tracking-widest border-b border-gray-50 mb-1">Select Context</div>
                    
                    <button @click="switchLocation('all')" 
                        class="w-full text-left px-3 py-2 text-[12px] font-bold rounded-lg transition-all {{ !session('selected_location_id') ? 'text-blue-600 bg-blue-50' : 'text-[#64748b] hover:bg-gray-50' }}">
                        Company Wide
                    </button>

                    <div class="my-1 border-t border-gray-50"></div>

                    @foreach($locations as $loc)
                        <button @click="switchLocation('{{ $loc->id }}')" 
                            class="w-full text-left px-3 py-2 text-[12px] font-bold rounded-lg transition-all {{ session('selected_location_id') == $loc->id ? 'text-blue-600 bg-blue-50' : 'text-[#64748b] hover:bg-gray-50' }}">
                            {{ $loc->name }}
                        </button>
                    @endforeach
                </div>
            </div>

            <div class="flex items-center ml-2">
                <!-- Notifications -->
                <div class="relative" x-data="{ open: false }">
                    <button @click="open = !open" @click.away="open = false" class="relative p-2 text-[#64748b] hover:text-[#2563eb] transition-colors">
                        <svg class="w-[20px] h-[20px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                        <span class="absolute top-[5px] right-[5px] w-[14px] h-[14px] flex items-center justify-center bg-[#3b82f6] border border-white text-white text-[8px] font-bold rounded-full">6</span>
                    </button>

                    <div x-show="open" x-cloak
                        x-transition:enter="transition ease-out duration-200"
                        x-transition:enter-start="opacity-0 scale-95 translate-y-[-10px]"
                        x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                        class="absolute right-0 mt-3 w-[360px] bg-white rounded-2xl shadow-[0_20px_50px_rgba(0,0,0,0.15)] border border-blue-50 z-50 overflow-hidden">
                        
                        <!-- Header -->
                        <div class="px-5 py-4 border-b border-gray-100 flex justify-between items-center bg-white">
                            <div class="flex items-center gap-2">
                                <div class="w-8 h-8 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9"></path></svg>
                                </div>
                                <span class="text-[14px] font-black text-[#1e293b] whitespace-nowrap">Notifications</span>
                            </div>
                            <button class="text-[11px] font-bold text-blue-600 hover:text-blue-700 whitespace-nowrap bg-blue-50/50 px-2.5 py-1 rounded-md transition-colors">Mark all read</button>
                        </div>

                        <!-- Content -->
                        <div class="max-h-[400px] overflow-y-auto no-scrollbar">
                            @foreach(range(1, 4) as $n)
                                <div class="px-5 py-4 border-b border-gray-50 hover:bg-blue-50/20 transition-all cursor-pointer group">
                                    <div class="flex gap-3">
                                        <div class="w-10 h-10 rounded-full bg-gray-100 shrink-0 flex items-center justify-center text-[#94a3b8] group-hover:bg-blue-100 group-hover:text-blue-600 transition-colors">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path></svg>
                                        </div>
                                        <div class="flex-1">
                                            <p class="text-[12.5px] font-bold text-[#1e293b] leading-snug">New client record added</p>
                                            <p class="text-[11px] text-[#64748b] mt-1 leading-relaxed">A new client was successfully added to the registry for the upcoming billing cycle.</p>
                                            <div class="flex items-center gap-2 mt-2">
                                                <span class="w-1.5 h-1.5 rounded-full bg-blue-500"></span>
                                                <p class="text-[10px] font-bold text-[#94a3b8]">2 mins ago</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            @endforeach
                        </div>

                        <!-- Footer -->
                        <div class="p-3 bg-gray-50/50">
                            <button class="w-full py-2.5 text-[12px] font-black text-[#64748b] hover:text-[#1e293b] hover:bg-white rounded-xl border border-transparent hover:border-gray-100 transition-all shadow-sm">
                                View All Activity
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Cog Settings -->
                <a href="{{ \App\Helpers\SettingsHelper::homeUrl() }}" class="p-2 text-[#64748b] hover:text-[#2563eb] transition-colors mx-0.5" title="Settings">
                    <svg class="w-[20px] h-[20px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"></path><circle cx="12" cy="12" r="3" stroke-width="2"></circle></svg>
                </a>

                <!-- User Dropdown -->
                <x-account.user-menu variant="header" />

                <!-- Logout Form -->
                <form method="POST" action="{{ route('logout') }}" class="m-0 ml-2 border-l border-[#e2e8f0] pl-2">
                    @csrf
                    <button type="submit" class="p-1.5 text-[#64748b] hover:text-[#dc2626] transition-colors">
                        <svg class="w-[22px] h-[22px]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"></path></svg>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Sub-Navigation Row (Dashboard Only) -->
    @if(Route::is('dashboard'))
    <div class="h-[52px] px-8 flex items-center gap-2 overflow-x-auto no-scrollbar pb-1.5 border-b border-blue-100/30 bg-[#eff6ff]">
        @php
            $subItems = [
                ['name' => 'Live Dashboard', 'path' => '/dashboard'],
                ['name' => 'Visit Reports', 'path' => '/reports/visit'],
                ['name' => 'Forms', 'path' => '/forms'],
                ['name' => 'Client Intake', 'path' => '/intakes'],
                ['name' => 'Data Exploration 2.0', 'path' => '/exploration'],
                ['name' => 'Tasks', 'path' => '/tasks'],
            ];
        @endphp

        @foreach($subItems as $subItem)
            @php
                $isActive = request()->is(ltrim($subItem['path'], '/')) || (request()->is('/') && $subItem['name'] === 'Live Dashboard');
            @endphp
            <a href="{{ $subItem['path'] }}" 
               class="px-3.5 py-1.5 text-[12px] font-bold whitespace-nowrap rounded-[8px] transition-all duration-200 border
               {{ $isActive ? 'bg-[#3b82f6] text-white border-[#3b82f6] shadow-sm shadow-[#3b82f6]/20' : 'bg-transparent text-[#64748b] border-[#cbd5e1]/60 hover:bg-white hover:text-[#1e293b] hover:border-[#94a3b8]' }}">
                {{ $subItem['name'] }}
            </a>
        @endforeach
    </div>
    @endif

</header>
