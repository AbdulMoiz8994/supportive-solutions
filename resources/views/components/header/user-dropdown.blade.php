@php
    $user = auth()->user();
    $initials = collect(explode(' ', $user->name))->map(fn($w) => strtoupper($w[0]))->take(2)->join('');
    $roleColors = [
        'Super Administrator' => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
        'Administrator'       => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
        'Operations Staff'    => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
        'Employee'            => 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400',
    ];
    $roleColor = $roleColors[$user->role] ?? 'bg-gray-100 text-gray-700';
@endphp

<div class="relative" x-data="{
    dropdownOpen: false,
    toggleDropdown() {
        this.dropdownOpen = !this.dropdownOpen;
    },
    closeDropdown() {
        this.dropdownOpen = false;
    }
}" @click.away="closeDropdown()">
    <!-- User Button -->
    <button class="flex items-center gap-2 text-gray-700 dark:text-gray-400 hover:opacity-90 transition-opacity" @click.prevent="toggleDropdown()" type="button">
        <!-- Avatar -->
        <span class="flex items-center justify-center overflow-hidden rounded-full h-10 w-10 bg-brand-500 text-white font-semibold text-sm shadow-sm ring-2 ring-white dark:ring-gray-800">
            {{ $initials }}
        </span>
        <!-- Name + Role -->
        <span class="hidden sm:flex flex-col items-start">
            <span class="block font-semibold text-sm text-gray-800 dark:text-white leading-tight">{{ $user->name }}</span>
            <span class="block text-xs text-gray-400 dark:text-gray-500 leading-tight">{{ $user->role }}</span>
        </span>
        <!-- Chevron Icon -->
        <svg class="w-4 h-4 text-gray-400 transition-transform duration-200" :class="{ 'rotate-180': dropdownOpen }" fill="none"
            stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"></path>
        </svg>
    </button>

    <!-- Dropdown -->
    <div x-show="dropdownOpen" x-transition:enter="transition ease-out duration-150"
        x-transition:enter-start="transform opacity-0 scale-95 -translate-y-2" x-transition:enter-end="transform opacity-100 scale-100 translate-y-0"
        x-transition:leave="transition ease-in duration-100" x-transition:leave-start="transform opacity-100 scale-100"
        x-transition:leave-end="transform opacity-0 scale-95"
        class="absolute right-0 mt-3 flex w-[280px] flex-col rounded-2xl border border-gray-200 bg-white p-3 shadow-xl dark:border-gray-800 dark:bg-gray-dark z-50"
        style="display: none;">

        <!-- User Info Header -->
        <div class="flex items-center gap-3 px-2 pb-3 border-b border-gray-100 dark:border-gray-800">
            <div class="flex-shrink-0 flex items-center justify-center w-12 h-12 rounded-full bg-brand-500 text-white font-bold text-base shadow">
                {{ $initials }}
            </div>
            <div class="flex-1 min-w-0">
                <span class="block font-semibold text-gray-800 text-sm dark:text-white truncate">{{ $user->name }}</span>
                <span class="block text-xs text-gray-500 dark:text-gray-400 truncate">{{ $user->email }}</span>
                <span class="inline-block mt-1 px-2 py-0.5 rounded-full text-[10px] font-semibold {{ $roleColor }}">
                    {{ $user->role }}
                </span>
            </div>
        </div>

        <!-- Menu Items -->
        <ul class="flex flex-col gap-0.5 pt-2 pb-2 border-b border-gray-100 dark:border-gray-800">
            <!-- Profile -->
            <li>
                <a href="{{ route('profile') }}"
                    class="flex items-center gap-3 px-3 py-2.5 font-medium text-gray-700 rounded-xl group text-sm hover:bg-gray-50 hover:text-brand-600 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-200 transition-colors">
                    <span class="text-gray-400 group-hover:text-brand-500 transition-colors">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 3.5C7.30558 3.5 3.5 7.30558 3.5 12C3.5 14.1526 4.3002 16.1184 5.61936 17.616C6.17279 15.3096 8.24852 13.5955 10.7246 13.5955H13.2746C15.7509 13.5955 17.8268 15.31 18.38 17.6167C19.6996 16.119 20.5 14.153 20.5 12C20.5 7.30558 16.6944 3.5 12 3.5ZM17.0246 18.8566V18.8455C17.0246 16.7744 15.3457 15.0955 13.2746 15.0955H10.7246C8.65354 15.0955 6.97461 16.7744 6.97461 18.8455V18.856C8.38223 19.8895 10.1198 20.5 12 20.5C13.8798 20.5 15.6171 19.8898 17.0246 18.8566ZM2 12C2 6.47715 6.47715 2 12 2C17.5228 2 22 6.47715 22 12C22 17.5228 17.5228 22 12 22C6.47715 22 2 17.5228 2 12ZM11.9991 7.25C10.8847 7.25 9.98126 8.15342 9.98126 9.26784C9.98126 10.3823 10.8847 11.2857 11.9991 11.2857C13.1135 11.2857 14.0169 10.3823 14.0169 9.26784C14.0169 8.15342 13.1135 7.25 11.9991 7.25ZM8.48126 9.26784C8.48126 7.32499 10.0563 5.75 11.9991 5.75C13.9419 5.75 15.5169 7.32499 15.5169 9.26784C15.5169 11.2107 13.9419 12.7857 11.9991 12.7857C10.0563 12.7857 8.48126 11.2107 8.48126 9.26784Z" fill="currentColor"/></svg>
                    </span>
                    My Profile
                </a>
            </li>

            <!-- User Management (Super Admin Only) -->
            @if(auth()->user()->isSuperAdmin())
            <li>
                <a href="{{ route('users.index') }}"
                    class="flex items-center gap-3 px-3 py-2.5 font-medium text-gray-700 rounded-xl group text-sm hover:bg-purple-50 hover:text-purple-700 dark:text-gray-400 dark:hover:bg-purple-900/10 dark:hover:text-purple-300 transition-colors">
                    <span class="text-gray-400 group-hover:text-purple-500 transition-colors">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none"><path fill-rule="evenodd" clip-rule="evenodd" d="M8 7a4 4 0 118 0A4 4 0 018 7zm-4 9a6 6 0 0112 0H4z" fill="currentColor" opacity=".4"/><path d="M21 20h1a1 1 0 000-2v-2a3 3 0 00-3-3h-2" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/><path d="M17 13a3 3 0 100-6 3 3 0 000 6z" fill="currentColor" opacity=".7"/></svg>
                    </span>
                    User Management
                    <span class="ml-auto px-1.5 py-0.5 rounded-full bg-purple-100 text-purple-600 text-[9px] font-bold dark:bg-purple-900/30 dark:text-purple-400">SA</span>
                </a>
            </li>
            @endif
        </ul>

        <!-- Sign Out -->
        <form method="POST" action="{{ route('logout') }}" class="mt-1">
            @csrf
            <button type="submit"
                class="flex items-center w-full gap-3 px-3 py-2.5 font-medium text-red-500 rounded-xl group text-sm hover:bg-red-50 hover:text-red-600 dark:text-red-400 dark:hover:bg-red-900/10 transition-colors"
                @click="closeDropdown()">
                <span class="text-red-400 group-hover:text-red-500 transition-colors">
                    <svg width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg>
                </span>
                Sign out
            </button>
        </form>
    </div>
    <!-- Dropdown End -->
</div>
