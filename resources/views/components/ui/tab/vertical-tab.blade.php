

@props([
    'activeTab' => 'overview'
])

@php
    $tabs = [
        [
            'key' => 'overview',
            'title' => 'Overview',
            'content' => 'Overview ipsum dolor sit amet consectetur. Non vitae facilisis urna tortor placerat egestas donec. Faucibus diam gravida enim elit lacus a. Tincidunt fermentum condimentum quis et a et tempus. Tristique urna nisi nulla elit sit libero scelerisque ante.'
        ],
        [
            'key' => 'notification',
            'title' => 'Notification',
            'content' => 'Notification ipsum dolor sit amet consectetur. Non vitae facilisis urna tortor placerat egestas donec. Faucibus diam gravida enim elit lacus a. Tincidunt fermentum condimentum quis et a et tempus. Tristique urna nisi nulla elit sit libero scelerisque ante.'
        ],
        [
            'key' => 'analytics',
            'title' => 'Analytics',
            'content' => 'Analytics ipsum dolor sit amet consectetur. Non vitae facilisis urna tortor placerat egestas donec. Faucibus diam gravida enim elit lacus a. Tincidunt fermentum condimentum quis et a et tempus. Tristique urna nisi nulla elit sit libero scelerisque ante.'
        ],
        [
            'key' => 'customers',
            'title' => 'Customers',
            'content' => 'Customers ipsum dolor sit amet consectetur. Non vitae facilisis urna tortor placerat egestas donec. Faucibus diam gravida enim elit lacus a. Tincidunt fermentum condimentum quis et a et tempus. Tristique urna nisi nulla elit sit libero scelerisque ante.'
        ],
    ];
@endphp

<div
    class="rounded-xl border border-gray-200 p-6 dark:border-gray-800"
    x-data="{ activeTab: '{{ $activeTab }}' }"
>
    <div class="flex flex-col gap-6 sm:flex-row sm:gap-8">
        <!-- Vertical Navigation -->
        <div class="overflow-x-auto pb-2 sm:w-[200px] [&::-webkit-scrollbar-thumb]:rounded-full [&::-webkit-scrollbar-thumb]:bg-gray-100 dark:[&::-webkit-scrollbar-thumb]:bg-gray-600 [&::-webkit-scrollbar-track]:bg-white dark:[&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar]:h-1.5">
            <nav class="flex w-full flex-row sm:flex-col sm:space-y-2">
                @foreach($tabs as $tab)
                    <button
                        class="inline-flex items-center rounded-lg px-3 py-2 text-sm font-medium transition-colors duration-200 ease-in-out sm:p-3 whitespace-nowrap"
                        x-bind:class="activeTab === '{{ $tab['key'] }}' ? 'text-blue-500 dark:bg-blue-400/20 dark:text-blue-400 bg-blue-50' : 'bg-transparent text-gray-500 border-transparent hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200'"
                        x-on:click="activeTab = '{{ $tab['key'] }}'"
                    >
                        {{ $tab['title'] }}
                    </button>
                @endforeach
            </nav>
        </div>

        <!-- Content Area -->
        <div class="flex-1">
            <div>
                @foreach($tabs as $tab)
                    <div x-show="activeTab === '{{ $tab['key'] }}'">
                        <h3 class="mb-1 text-xl font-medium text-gray-800 dark:text-white/90">
                            {{ $tab['title'] }}
                        </h3>
                        <p class="text-sm text-gray-500 dark:text-gray-400">
                            {{ $tab['content'] }}
                        </p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
