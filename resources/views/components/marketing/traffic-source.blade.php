@props(['sources' => [], 'title' => 'Top Traffic Source'])

@php
    $defaultSources = [
        [
            'name' => 'Google',
            'icon' => './images/brand/brand-05.svg',
            'percentage' => 79,
        ],
        [
            'name' => 'Youtube',
            'icon' => './images/brand/brand-06.svg',
            'percentage' => 55,
        ],
        [
            'name' => 'Facebook',
            'icon' => './images/brand/brand-02.svg',
            'percentage' => 48,
        ],
        [
            'name' => 'Instagram',
            'icon' => './images/brand/brand-04.svg',
            'percentage' => 48,
        ],
    ];

    $sourcesList = !empty($sources) ? $sources : $defaultSources;
@endphp

<div class="rounded-2xl border border-gray-200 bg-white p-5 dark:border-gray-800 dark:bg-white/[0.03] sm:p-6">
    <div class="mb-6 flex items-center justify-between gap-2">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                {{ $title }}
            </h3>
        </div>

        <!-- Dropdown Menu -->
        <x-common.dropdown-menu />
        <!-- End Dropdown Menu -->
    </div>

    <div>
        @foreach ($sourcesList as $source)
            <div
                class="flex items-center justify-between border-b border-gray-100 py-3 last:border-b-0 dark:border-gray-800">
                <div class="flex items-center gap-4">
                    <div class="w-full max-w-8 items-center rounded-full">
                        <img src="{{ $source['icon'] }}" alt="{{ strtolower($source['name']) }}" />
                    </div>
                    <div>
                        <p class="text-theme-sm font-medium text-gray-700 dark:text-gray-300">
                            {{ $source['name'] }}
                        </p>
                    </div>
                </div>

                <div class="flex w-full max-w-[140px] items-center gap-3">
                    <div class="relative block h-2 w-full max-w-[100px] rounded-sm bg-gray-200 dark:bg-gray-800">
                        <div class="absolute left-0 top-0 flex h-full items-center justify-center rounded-sm bg-brand-500 text-xs font-medium text-white"
                            style="width: {{ $source['percentage'] }}%"></div>
                    </div>
                    <p class="text-theme-sm font-medium text-gray-700 dark:text-gray-400">
                        {{ $source['percentage'] }}%
                    </p>
                </div>
            </div>
        @endforeach
    </div>

    <a href="#"
        class="mt-6 flex items-center justify-center gap-2 rounded-lg border border-gray-300 bg-white p-2.5 text-theme-sm font-medium text-gray-700 shadow-theme-xs hover:bg-gray-50 dark:border-gray-700 dark:bg-gray-800 dark:text-gray-400 dark:hover:bg-white/[0.03]">
        View All
    </a>
</div>
