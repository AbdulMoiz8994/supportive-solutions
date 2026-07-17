@props(['activities' => [], 'title' => 'Activities'])

@php
    $defaultActivities = [
        [
            'name' => 'Francisco Grbbs',
            'avatar' => '/images/user/user-01.jpg',
            'action' => 'created invoice',
            'reference' => 'PQ-4491C',
            'time' => 'Just Now',
            'isNew' => true,
        ],
        [
            'name' => 'Courtney Henry',
            'avatar' => '/images/user/user-03.jpg',
            'action' => 'created invoice',
            'reference' => 'HK-234G',
            'time' => '15 minutes ago',
            'isNew' => false,
        ],
        [
            'name' => 'Bessie Cooper',
            'avatar' => '/images/user/user-04.jpg',
            'action' => 'created invoice',
            'reference' => 'LH-2891C',
            'time' => '5 months ago',
            'isNew' => false,
        ],
        [
            'name' => 'Theresa Web',
            'avatar' => '/images/user/user-05.jpg',
            'action' => 'created invoice',
            'reference' => 'CK-125NH',
            'time' => '2 weeks ago',
            'isNew' => false,
        ],
    ];

    $activityList = !empty($activities) ? $activities : $defaultActivities;
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-6 dark:border-gray-800 dark:bg-white/[0.03]">
    <div class="mb-6 flex justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">{{ $title }}</h3>
        </div>
        <div class="relative h-fit">
            <div x-data="{ openDropDown: false }" class="relative">
                <button
                    @click="openDropDown = !openDropDown"
                    :class="openDropDown ? 'text-gray-700 dark:text-white' : 'text-gray-400 hover:text-gray-700 dark:hover:text-white'"
                >
                    <svg width="24" height="24" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                        <path fill-rule="evenodd" clip-rule="evenodd" d="M10.2441 6C10.2441 5.0335 11.0276 4.25 11.9941 4.25H12.0041C12.9706 4.25 13.7541 5.0335 13.7541 6C13.7541 6.9665 12.9706 7.75 12.0041 7.75H11.9941C11.0276 7.75 10.2441 6.9665 10.2441 6ZM10.2441 18C10.2441 17.0335 11.0276 16.25 11.9941 16.25H12.0041C12.9706 16.25 13.7541 17.0335 13.7541 18C13.7541 18.9665 12.9706 19.75 12.0041 19.75H11.9941C11.0276 19.75 10.2441 18.9665 10.2441 18ZM11.9941 10.25C11.0276 10.25 10.2441 11.0335 10.2441 12C10.2441 12.9665 11.0276 13.75 11.9941 13.75H12.0041C12.9706 13.75 13.7541 12.9665 13.7541 12C13.7541 11.0335 12.9706 10.25 12.0041 10.25H11.9941Z" fill="currentColor" />
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

    <div class="relative">
        <!-- Timeline line -->
        <div class="absolute top-6 bottom-10 left-5 w-px bg-gray-200 dark:bg-gray-800"></div>

        @foreach($activityList as $index => $activity)
            <div class="relative flex {{ $loop->last ? '' : 'mb-6' }}">
                <div class="z-10 flex-shrink-0">
                    <img
                        src="{{ $activity['avatar'] }}"
                        alt="{{ $activity['name'] }}"
                        class="size-10 rounded-full object-cover ring-4 ring-white dark:ring-gray-800"
                    />
                </div>
                <div class="ml-4">
                    @if($activity['isNew'])
                        <div class="mb-1 flex items-center gap-1">
                            <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
                                <path d="M9 5.0625H14.0625L12.5827 8.35084C12.4506 8.64443 12.4506 8.98057 12.5827 9.27416L14.0625 12.5625H10.125C9.50368 12.5625 9 12.0588 9 11.4375V10.875M3.9375 10.875H9M3.9375 3.375H7.875C8.49632 3.375 9 3.87868 9 4.5V10.875M3.9375 15.9375V2.0625" stroke="#12B76A" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" />
                            </svg>
                            <p class="text-theme-xs text-success-500 font-medium">New invoice</p>
                        </div>
                    @endif
                    <div class="flex items-baseline">
                        <h3 class="text-theme-sm font-{{ $activity['isNew'] ? 'medium' : 'semibold' }} text-gray-800 dark:text-white/90">
                            {{ $activity['name'] }}
                        </h3>
                        <span class="text-theme-sm ml-2 font-normal text-gray-500 dark:text-gray-400">
                            {{ $activity['action'] }}
                        </span>
                    </div>
                    <p class="text-theme-sm font-normal text-gray-500 dark:text-gray-400">
                        {{ $activity['reference'] }}
                    </p>
                    <p class="text-theme-xs mt-1 text-gray-400">
                        {{ $activity['time'] }}
                    </p>
                </div>
            </div>
        @endforeach
    </div>
</div>
