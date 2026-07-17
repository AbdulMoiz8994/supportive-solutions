@props(['items' => [], 'title' => 'My Watchlist'])

@php
    $defaultItems = [
        [
            'symbol' => 'AAPL',
            'name' => 'Apple, Inc',
            'logo' => '/images/brand/brand-07.svg',
            'price' => '$4,008.65',
            'change' => 11.01,
        ],
        [
            'symbol' => 'SPOT',
            'name' => 'Spotify.com',
            'logo' => '/images/brand/brand-11.svg',
            'price' => '$11,689.00',
            'change' => 9.48,
        ],
        [
            'symbol' => 'ABNB',
            'name' => 'Airbnb, Inc',
            'logo' => '/images/brand/brand-12.svg',
            'price' => '$32,227.00',
            'change' => -0.29,
        ],
        [
            'symbol' => 'ENVT',
            'name' => 'Envato, Inc',
            'logo' => '/images/brand/brand-13.svg',
            'price' => '$13,895.00',
            'change' => 3.79,
        ],
        [
            'symbol' => 'AAPL',
            'name' => 'Apple, Inc',
            'logo' => '/images/brand/brand-07.svg',
            'price' => '$4,008.65',
            'change' => 11.01,
        ],
        [
            'symbol' => 'SPOT',
            'name' => 'Spotify.com',
            'logo' => '/images/brand/brand-11.svg',
            'price' => '$11,689.00',
            'change' => 9.48,
        ],
        [
            'symbol' => 'QIWI',
            'name' => 'qiwi.com, Inc',
            'logo' => '/images/brand/brand-14.svg',
            'price' => '$4,008.65',
            'change' => 4.52,
        ],
    ];

    $watchlistItems = !empty($items) ? $items : $defaultItems;
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>

        <div class="relative">
            <div x-data="{ openDropDown: false }" class="relative">
                <button
                    @click="openDropDown = !openDropDown"
                    :class="openDropDown ? 'text-gray-700 dark:text-white' : 'text-gray-400 hover:text-gray-700 dark:hover:text-white'"
                >
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path
                            fill-rule="evenodd"
                            clip-rule="evenodd"
                            d="M10.2441 6C10.2441 5.0335 11.0276 4.25 11.9941 4.25H12.0041C12.9706 4.25 13.7541 5.0335 13.7541 6C13.7541 6.9665 12.9706 7.75 12.0041 7.75H11.9941C11.0276 7.75 10.2441 6.9665 10.2441 6ZM10.2441 18C10.2441 17.0335 11.0276 16.25 11.9941 16.25H12.0041C12.9706 16.25 13.7541 17.0335 13.7541 18C13.7541 18.9665 12.9706 19.75 12.0041 19.75H11.9941C11.0276 19.75 10.2441 18.9665 10.2441 18ZM11.9941 10.25C11.0276 10.25 10.2441 11.0335 10.2441 12C10.2441 12.9665 11.0276 13.75 11.9941 13.75H12.0041C12.9706 13.75 13.7541 12.9665 13.7541 12C13.7541 11.0335 12.9706 10.25 12.0041 10.25H11.9941Z"
                            fill="currentColor"
                        />
                    </svg>
                </button>
                <div
                    x-show="openDropDown"
                    @click.outside="openDropDown = false"
                    x-transition
                    class="absolute right-0 top-full z-40 w-40 space-y-1 rounded-2xl border border-gray-200 bg-white p-2 shadow-theme-lg dark:border-gray-800 dark:bg-gray-dark"
                >
                    <button class="flex w-full rounded-lg px-3 py-2 text-left text-theme-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                        View More
                    </button>
                    <button class="flex w-full rounded-lg px-3 py-2 text-left text-theme-xs font-medium text-gray-500 hover:bg-gray-100 hover:text-gray-700 dark:text-gray-400 dark:hover:bg-white/5 dark:hover:text-gray-300">
                        Delete
                    </button>
                </div>
            </div>
        </div>
    </div>

    <div class="flex h-[372px] flex-col">
        <div class="flex flex-col h-auto pr-3 overflow-y-auto custom-scrollbar">
            @foreach($watchlistItems as $item)
                <div class="flex items-center justify-between pt-4 pb-4 border-b border-gray-200 first:pt-0 last:border-b-0 last:pb-0 dark:border-gray-800">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10">
                            <img src="{{ $item['logo'] }}" alt="{{ $item['name'] }}" />
                        </div>
                        <div>
                            <h3 class="text-base font-semibold text-gray-800 dark:text-white/90">{{ $item['symbol'] }}</h3>
                            <span class="block text-gray-500 text-theme-xs dark:text-gray-400">{{ $item['name'] }}</span>
                        </div>
                    </div>
                    <div>
                        <div>
                            <h4 class="mb-1 font-medium text-right text-gray-700 text-theme-sm dark:text-gray-400">
                                {{ $item['price'] }}
                            </h4>
                        </div>
                        <span class="flex items-center justify-end gap-1 font-medium text-theme-xs {{ $item['change'] >= 0 ? 'text-success-600 dark:text-success-500' : 'text-error-600 dark:text-error-500' }}">
                            @if($item['change'] >= 0)
                                <svg class="fill-current" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        fill-rule="evenodd"
                                        clip-rule="evenodd"
                                        d="M5.56462 1.62394C5.70193 1.47073 5.90135 1.37433 6.12329 1.37433C6.1236 1.37433 6.12391 1.37433 6.12422 1.37433C6.31631 1.37416 6.50845 1.44732 6.65505 1.59381L9.65514 4.59181C9.94814 4.8846 9.94831 5.35947 9.65552 5.65247C9.36273 5.94546 8.88785 5.94563 8.59486 5.65284L6.87329 3.93248L6.87329 10.125C6.87329 10.5392 6.53751 10.875 6.12329 10.875C5.70908 10.875 5.37329 10.5392 5.37329 10.125L5.37329 3.93579L3.65516 5.65282C3.36218 5.94562 2.8873 5.94547 2.5945 5.65249C2.3017 5.3595 2.30185 4.88463 2.59484 4.59183L5.56462 1.62394Z"
                                        fill=""
                                    />
                                </svg>
                            @else
                                <svg class="fill-current" width="12" height="12" viewBox="0 0 12 12" fill="none" xmlns="http://www.w3.org/2000/svg">
                                    <path
                                        fill-rule="evenodd"
                                        clip-rule="evenodd"
                                        d="M6.43538 10.3761C6.29807 10.5293 6.09865 10.6257 5.87671 10.6257C5.8764 10.6257 5.87609 10.6257 5.87578 10.6257C5.68369 10.6258 5.49155 10.5527 5.34495 10.4062L2.34486 7.40819C2.05186 7.1154 2.05169 6.64053 2.34448 6.34753C2.63727 6.05454 3.11215 6.05437 3.40514 6.34716L5.12671 8.06752L5.12671 1.875C5.12671 1.46079 5.46249 1.125 5.87671 1.125C6.29092 1.125 6.62671 1.46079 6.62671 1.875L6.62671 8.06421L8.34484 6.34718C8.63782 6.05438 9.1127 6.05453 9.4055 6.34751C9.6983 6.6405 9.69815 7.11537 9.40516 7.40817L6.43538 10.3761Z"
                                        fill=""
                                    />
                                </svg>
                            @endif
                            {{ number_format(abs($item['change']), 2) }}%
                        </span>
                    </div>
                </div>
            @endforeach
        </div>
    </div>
</div>
