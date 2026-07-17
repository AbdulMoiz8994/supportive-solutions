@props([
    'title' => 'Traffic Stats',
    'stats' => [],
    'tabs' => ['today', 'week', 'month'],
    'defaultTab' => 'today',
])

@php
    $defaultStats = [
        [
            'label' => 'New Subscribers',
            'value' => '567K',
            'change' => '+3.85%',
            'change_type' => 'success',
            'comparison' => 'then last Week',
            'chart_class' => 'chartNine chartNine-01',
        ],
        [
            'label' => 'Conversion Rate',
            'value' => '276K',
            'change' => '-5.39%',
            'change_type' => 'error',
            'comparison' => 'then last Week',
            'chart_class' => 'chartTen chartTen-01',
        ],
        [
            'label' => 'Page Bounce Rate',
            'value' => '285',
            'change' => '+12.74%',
            'change_type' => 'success',
            'comparison' => 'then last Week',
            'chart_class' => 'chartNine chartNine-02',
        ],
    ];

    $statsList = !empty($stats) ? $stats : $defaultStats;
@endphp

<div
    class="rounded-2xl border border-gray-200 bg-white px-5 pb-1 pt-5 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6 sm:pt-6">
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

    <div x-data="{ selected: '{{ $defaultTab }}' }" class="flex items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900">
        @foreach ($tabs as $tab)
            <button @click="selected = '{{ $tab }}'"
                :class="selected === '{{ $tab }}' ?
                    'shadow-theme-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' :
                    'text-gray-500 dark:text-gray-400'"
                class="block w-full rounded-md px-3 py-2 text-theme-sm font-medium hover:text-gray-900 dark:hover:bg-gray-800 dark:hover:text-white">
                {{ ucfirst($tab) }}
            </button>
        @endforeach
    </div>

    <div>
        @foreach ($statsList as $index => $stat)
            <!-- Stats item -->
            <div
                class="flex items-end justify-between py-5 {{ $index > 0 && $index < count($statsList) - 1 ? 'border-y border-gray-100 dark:border-gray-800' : '' }} {{ $index === 1 ? 'border-y border-gray-100 dark:border-gray-800' : '' }}">
                <div>
                    <p class="mb-1 text-theme-sm text-gray-500 dark:text-gray-400">
                        {{ $stat['label'] }}
                    </p>
                    <h4 class="mb-1 text-2xl font-semibold text-gray-800 dark:text-white/90">
                        {{ $stat['value'] }}
                    </h4>
                    <span class="flex items-center gap-1.5">
                        <span class="text-{{ $stat['change_type'] }}-600">
                            {{ $stat['change'] }}
                        </span>
                        <span class="text-theme-xs text-gray-500 dark:text-gray-400">
                            {{ $stat['comparison'] }}
                        </span>
                    </span>
                </div>

                <div class="w-full max-w-[150px]">
                    <div class="{{ $stat['chart_class'] }}"></div>
                </div>
            </div>
        @endforeach
    </div>
</div>
