@props(['items' => [], 'title' => 'Upcoming Schedule'])

@php
    $defaultItems = [
        [
            'id' => 1,
            'checked' => false,
            'date' => 'Wed, 11 jan',
            'time' => '09:20 AM',
            'title' => 'Business Analytics Press',
            'description' => 'Exploring the Future of Data-Driven +6 more',
        ],
        [
            'id' => 2,
            'checked' => false,
            'date' => 'Fri, 15 feb',
            'time' => '10:35 AM',
            'title' => 'Business Sprint',
            'description' => 'Techniques from Business Sprint +2 more',
        ],
        [
            'id' => 3,
            'checked' => false,
            'date' => 'Thu, 18 mar',
            'time' => '1:15 AM',
            'title' => 'Customer Review Meeting',
            'description' => 'Insights from the Customer Review Meeting +8 more',
        ],
    ];

    $itemsList = !empty($items) ? $items : $defaultItems;
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>

        <div class="relative">
            <x-common.dropdown-menu />
        </div>
    </div>

    <div class="max-w-full overflow-x-auto custom-scrollbar">
        <div class="min-w-[500px]">
            <div class="flex flex-col gap-2">
                @foreach($itemsList as $item)
                    <div
                        x-data="{ checked: {{ $item['checked'] ? 'true' : 'false' }} }"
                        @click="checked = !checked"
                        class="flex cursor-pointer items-center gap-9 rounded-lg p-3 hover:bg-gray-50 dark:hover:bg-white/[0.03]"
                    >
                        <div class="flex items-start gap-3">
                            <div
                                class="flex h-5 w-5 items-center justify-center rounded-md border-[1.25px]"
                                :class="checked
                                    ? 'border-brand-500 dark:border-brand-500 bg-brand-500'
                                    : 'bg-white dark:bg-white/0 border-gray-300 dark:border-gray-700'"
                            >
                                <svg
                                    x-show="checked"
                                    width="14"
                                    height="14"
                                    viewBox="0 0 14 14"
                                    fill="none"
                                    xmlns="http://www.w3.org/2000/svg"
                                >
                                    <path
                                        d="M11.6668 3.5L5.25016 9.91667L2.3335 7"
                                        stroke="white"
                                        stroke-width="1.94437"
                                        stroke-linecap="round"
                                        stroke-linejoin="round"
                                    />
                                </svg>
                            </div>
                            <div>
                                <span class="mb-0.5 block text-theme-xs text-gray-500 dark:text-gray-400">
                                    {{ $item['date'] }}
                                </span>
                                <span class="font-medium text-gray-700 text-theme-sm dark:text-gray-400">
                                    {{ $item['time'] }}
                                </span>
                            </div>
                        </div>
                        <div>
                            <span class="block mb-1 font-medium text-gray-700 text-theme-sm dark:text-gray-400">
                                {{ $item['title'] }}
                            </span>
                            <span class="text-gray-500 text-theme-xs dark:text-gray-400">
                                {{ $item['description'] }}
                            </span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>
    </div>
</div>
