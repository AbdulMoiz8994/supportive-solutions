@props([
    'title' => 'Portfolio Performance',
    'description' => 'Here is your performance stats of each month',
    'tabs' => [],
    'defaultTab' => 'monthly',
])

@php
    $defaultTabs = [
        ['label' => 'Monthly', 'value' => 'monthly'],
        ['label' => 'Quarterly', 'value' => 'quarterly'],
        ['label' => 'Annually', 'value' => 'annually'],
    ];
    
    $tabsList = !empty($tabs) ? $tabs : $defaultTabs;
@endphp

<div class="rounded-2xl border border-gray-200 bg-white px-5 pb-5 pt-5 dark:border-gray-800 dark:bg-white/[0.03] sm:px-6 sm:pt-5">
    <div class="flex flex-col gap-5 mb-6 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-800 dark:text-white/90">
                {{ $title }}
            </h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ $description }}
            </p>
        </div>

        <div 
            x-data="{ selected: '{{ $defaultTab }}' }"
            class="inline-flex w-fit items-center gap-0.5 rounded-lg bg-gray-100 p-0.5 dark:bg-gray-900"
        >
            @foreach($tabsList as $tab)
                <button
                    @click="selected = '{{ $tab['value'] }}'"
                    :class="selected === '{{ $tab['value'] }}' 
                        ? 'shadow-xs text-gray-900 dark:text-white bg-white dark:bg-gray-800' 
                        : 'text-gray-500 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white'"
                    class="rounded-md px-3 py-2 text-sm font-medium transition-colors"
                >
                    {{ $tab['label'] }}
                </button>
            @endforeach
        </div>
    </div>

    <div class="max-w-full overflow-x-auto custom-scrollbar">
        <div class="-ml-4 min-w-[900px] xl:min-w-full pl-2">
            <div id="chartThirteen"></div>
        </div>
    </div>
</div>
