@props([
    'title' => 'Product Performance',
    'dailyData' => [],
    'onlineData' => [],
    'newUserData' => [],
])

@php
    $defaultDailyData = [
        'digital' => 790,
        'physical' => 572,
        'average' => '$2,950',
        'change' => '-0.52',
    ];

    $defaultOnlineData = [
        'digital' => 8490,
        'physical' => 950,
        'total' => '$59,410',
        'change' => '+0.52',
    ];

    $defaultNewUserData = [
        'added' => 8490,
        'total' => '5.9K',
        'change' => '+0.52',
        'avatars' => ['/images/user/user-01.jpg', '/images/user/user-02.jpg', '/images/user/user-03.jpg'],
    ];

    $daily = !empty($dailyData) ? $dailyData : $defaultDailyData;
    $online = !empty($onlineData) ? $onlineData : $defaultOnlineData;
    $newUser = !empty($newUserData) ? $newUserData : $defaultNewUserData;
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]"
    x-data="{ selected: 'daily' }">
    <div class="mb-6 flex justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>
        </div>
        <div class="relative inline-block">
            <!-- Dropdown Menu -->
            <x-common.dropdown-menu />
            <!-- End Dropdown Menu -->
        </div>
    </div>

    <!-- Tab Buttons -->
    <div class="flex w-full items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900">
        <button @click="selected = 'daily'"
            :class="[
                'text-sm w-full rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white',
                selected === 'daily' ?
                'shadow-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800' :
                'text-gray-500 dark:text-gray-400'
            ]">
            Daily Sales
        </button>
        <button @click="selected = 'online'"
            :class="[
                'text-sm w-full rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white',
                selected === 'online' ?
                'shadow-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800' :
                'text-gray-500 dark:text-gray-400'
            ]">
            Online Sales
        </button>
        <button @click="selected = 'new'"
            :class="[
                'text-sm w-full rounded-md px-3 py-2 font-medium hover:text-gray-900 dark:hover:text-white',
                selected === 'new' ?
                'shadow-sm text-gray-900 dark:text-white bg-white dark:bg-gray-800' :
                'text-gray-500 dark:text-gray-400'
            ]">
            New Users
        </button>
    </div>

    <!-- Tab Panels -->
    <div class="mt-4">
        <!-- Daily Sales Panel -->
        <div x-show="selected === 'daily'" class="space-y-4">
            <div
                class="grid grid-cols-2 justify-between gap-10 divide-x divide-gray-100 rounded-xl border border-gray-100 bg-white py-4 dark:divide-gray-800 dark:border-gray-800 dark:bg-gray-800/[0.03]">
                <div class="px-5">
                    <span class="block text-sm text-gray-500 dark:text-gray-400">Digital Product</span>
                    <div class="mt-1 flex items-center gap-2">
                        <span
                            class="bg-success-50 dark:bg-success-500/15 text-success-600 inline-flex size-5 items-center justify-center rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12"
                                fill="none">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M5.56462 1.62411C5.70194 1.47091 5.90136 1.37451 6.12329 1.37451C6.1236 1.37451 6.1239 1.37451 6.12421 1.37451C6.3163 1.37434 6.50845 1.4475 6.65505 1.594L9.65514 4.59199C9.94814 4.88478 9.94831 5.35966 9.65552 5.65265C9.36272 5.94565 8.88785 5.94581 8.59486 5.65302L6.87329 3.93267L6.87329 10.1252C6.87329 10.5394 6.53751 10.8752 6.12329 10.8752C5.70908 10.8752 5.37329 10.5394 5.37329 10.1252L5.37329 3.93597L3.65516 5.65301C3.36218 5.94581 2.8873 5.94566 2.5945 5.65267C2.3017 5.35968 2.30185 4.88481 2.59484 4.59201L5.56462 1.62411Z"
                                    fill="currentColor" />
                            </svg>
                        </span>
                        <h4 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ $daily['digital'] }}</h4>
                    </div>
                </div>
                <div class="px-5">
                    <span class="block text-sm text-gray-500 dark:text-gray-400">Physical Product</span>
                    <div class="mt-1 flex items-center gap-2">
                        <span
                            class="bg-error-50 dark:bg-error-500/15 text-error-600 inline-flex size-5 items-center justify-center rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12"
                                fill="none">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M5.31462 10.3761C5.45194 10.5293 5.65136 10.6257 5.87329 10.6257C5.8736 10.6257 5.8739 10.6257 5.87421 10.6257C6.0663 10.6259 6.25845 10.5527 6.40505 10.4062L9.40514 7.4082C9.69814 7.11541 9.69831 6.64054 9.40552 6.34754C9.11273 6.05454 8.63785 6.05438 8.34486 6.34717L6.62329 8.06753L6.62329 1.875C6.62329 1.46079 6.28751 1.125 5.87329 1.125C5.45908 1.125 5.12329 1.46079 5.12329 1.875L5.12329 8.06422L3.40516 6.34719C3.11218 6.05439 2.6373 6.05454 2.3445 6.34752C2.0517 6.64051 2.05185 7.11538 2.34484 7.40818L5.31462 10.3761Z"
                                    fill="currentColor" />
                            </svg>
                        </span>
                        <h4 class="text-xl font-semibold text-gray-800 dark:text-white/90">{{ $daily['physical'] }}</h4>
                    </div>
                </div>
            </div>
            {{-- New User Chart --}}
            <x-sass.new-user-chart :daily="$daily"/>
        </div>

        <!-- Online Sales Panel -->
        <div x-show="selected === 'online'" class="space-y-4">
            <div
                class="grid grid-cols-2 justify-between gap-10 divide-x divide-gray-100 rounded-xl border border-gray-100 py-4 dark:divide-gray-800 dark:border-gray-800">
                <div class="px-5">
                    <span class="block text-sm text-gray-500 dark:text-gray-400">Digital Product</span>
                    <div class="mt-1 flex items-center gap-2">
                        <span
                            class="bg-success-50 dark:bg-success-500/15 text-success-600 inline-flex size-5 items-center justify-center rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12"
                                fill="none">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M5.56462 1.62411C5.70194 1.47091 5.90136 1.37451 6.12329 1.37451C6.1236 1.37451 6.1239 1.37451 6.12421 1.37451C6.3163 1.37434 6.50845 1.4475 6.65505 1.594L9.65514 4.59199C9.94814 4.88478 9.94831 5.35966 9.65552 5.65265C9.36272 5.94565 8.88785 5.94581 8.59486 5.65302L6.87329 3.93267L6.87329 10.1252C6.87329 10.5394 6.53751 10.8752 6.12329 10.8752C5.70908 10.8752 5.37329 10.5394 5.37329 10.1252L5.37329 3.93597L3.65516 5.65301C3.36218 5.94581 2.8873 5.94566 2.5945 5.65267C2.3017 5.35968 2.30185 4.88481 2.59484 4.59201L5.56462 1.62411Z"
                                    fill="currentColor" />
                            </svg>
                        </span>
                        <h4 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                            {{ number_format($online['digital']) }}</h4>
                    </div>
                </div>
                <div class="px-5">
                    <span class="block text-sm text-gray-500 dark:text-gray-400">Physical Product</span>
                    <div class="mt-1 flex items-center gap-2">
                        <span
                            class="bg-error-50 dark:bg-error-500/15 text-error-600 inline-flex size-5 items-center justify-center rounded-full">
                            <svg xmlns="http://www.w3.org/2000/svg" width="12" height="12" viewBox="0 0 12 12"
                                fill="none">
                                <path fill-rule="evenodd" clip-rule="evenodd"
                                    d="M5.31462 10.3761C5.45194 10.5293 5.65136 10.6257 5.87329 10.6257C5.8736 10.6257 5.8739 10.6257 5.87421 10.6257C6.0663 10.6259 6.25845 10.5527 6.40505 10.4062L9.40514 7.4082C9.69814 7.11541 9.69831 6.64054 9.40552 6.34754C9.11273 6.05454 8.63785 6.05438 8.34486 6.34717L6.62329 8.06753L6.62329 1.875C6.62329 1.46079 6.28751 1.125 5.87329 1.125C5.45908 1.125 5.12329 1.46079 5.12329 1.875L5.12329 8.06422L3.40516 6.34719C3.11218 6.05439 2.6373 6.05454 2.3445 6.34752C2.0517 6.64051 2.05185 7.11538 2.34484 7.40818L5.31462 10.3761Z"
                                    fill="currentColor" />
                            </svg>
                        </span>
                        <h4 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                            {{ number_format($online['physical']) }}</h4>
                    </div>
                </div>
            </div>
            {{-- Online Sale Chart --}}
            <x-sass.online-sale-chart :online="$online"/>
        </div>

        <!-- New Users Panel -->
        <div x-show="selected === 'new'" class="space-y-4">
            <div
                class="grid grid-cols-2 justify-between gap-4 rounded-xl border border-gray-100 py-4 dark:border-gray-800">
                <div class="px-5">
                    <div class="flex -space-x-3">
                        @foreach ($newUser['avatars'] as $avatar)
                            <img src="{{ $avatar }}"
                                class="size-12 rounded-full ring-2 ring-white dark:ring-gray-800/90" alt="User"
                                width="48" height="48" />
                        @endforeach
                        <div
                            class="inline-flex size-12 shrink-0 items-center justify-center rounded-full bg-gray-200 text-sm font-medium text-gray-700 ring-2 ring-white dark:ring-gray-800/90">
                            10+
                        </div>
                    </div>
                </div>
                <div class="px-5">
                    <span class="block text-sm text-gray-500 dark:text-gray-400">Added last month</span>
                    <div class="mt-1 flex items-center gap-2">
                        <h4 class="text-xl font-semibold text-gray-800 dark:text-white/90">
                            {{ number_format($newUser['added']) }}
                        </h4>
                    </div>
                </div>
            </div>
           {{-- New User Chart --}}
        <x-sass.new-user-chart :daily="$daily"/>
        </div>
    </div>
</div>
